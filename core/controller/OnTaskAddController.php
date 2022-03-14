<?php

namespace core\controller;

use DateTime;
use core\base\controller\BaseController;
use core\base\exception\ControllerException;

class OnTaskAddController extends BaseController
{
    protected array $task_info;
    protected array $calendar_settings;
    protected int $event_id;
    
    /** Точка входа контроллера.
     * @throws \core\base\exception\ControllerException
     * @throws \core\base\exception\ModelException
     */
    protected function start()
    {
        $task_id = $_POST['data']['FIELDS_AFTER']['ID'];
        
        $this->calendar_settings = $this->model->getCalendarSettings();
        $this->task_info = $this->model->getTaskByID($task_id);
        
        $this->toLog('Создана Задача '
            . $this->task_info['id'] . ' ' . $this->task_info['title'], null, 'ONTASKADD',true);

//---------------------------------------------------------------------------------------------
        preg_match(PREG_TASK_NUMBER, $this->task_info['title'], $matches);
        if ($matches[0])
            throw new ControllerException('Задача создана скриптом для События ' . substr($matches[0], 2, - 1));
//--------------------------------------------------------------------------------------------- 

        if (!$this->isTaskMeetConditions())
            throw new ControllerException('Задача не соответствует требованиям заказчика.');
        
        $user = $this->model->getUser($this->task_info['responsible']['id']);
        
        $department = $this->model->getDepartment($user['UF_DEPARTMENT'][0]);
        
        $uf_head = $department['UF_HEAD'];
        
        $event_id = $this->createEvent();
    }
    
    /** Проверка задачи на соответствие требованиям заказчика.
     * @return bool
     */
    protected function isTaskMeetConditions(): bool
    {
        if ($this->task_info['startDatePlan'] &&
            $this->task_info['endDatePlan'])
            return true;
        else
            return false;
    }
    
    /** Создаем событие к полученой задаче.
     * @return array
     * @throws \core\base\exception\ModelException
     */
    protected function createEvent(): void
    {
        $time_new_task = $this->getTimeArrayTask($this->task_info);
        $time_new_task = $this->model->addOneHourCrutch($time_new_task);
        $description = $this->task_info['description']
            . TASK_URL[0]
            . $this->task_info['createdBy']
            . TASK_URL[1]
            . $this->task_info['id']
            . TASK_URL[2];
        
        $fields = [
            'from'        => $time_new_task['DATE_FROM'],
            'to'          => $time_new_task['DATE_TO'],
            'name'        => $this->task_info['title'] . ' (' . $this->task_info['id'] . ')',
            'ownerId'     => $this->task_info['responsible']['id'],
            'type'        => 'user',
            'section'     => $this->model->getCalendarSection($this->task_info['responsible']['id'])[0]['ID'],
            'description' => $description
        ];
        $result = $this->model->letsREST('calendar.event.add', $fields);
        
        if (isset($result['error']))
            throw new ControllerException('Не получилось создать Событие для Задачи ' . $this->task_info['id']
                . '. REST API отвечает => ' . $result['error_description']);
        
        $this->event_id = $result['result'];

        $this->toLog('Создано Событие ' . $this->event_id . ' к Задаче ' . $this->task_info['id']);
        
        $this->model->createEvent(
            [
                'task_id'  => $this->task_info['id'],
                'event_id' => $result['result'],
                'owner_id' => $this->task_info['responsible']['id']
            ]
        );

        $this->updateTask($this->event_id);
    
        $time_new_task = $this->model->subOneHourCrutch($time_new_task);
        
        $busyness = $this->model->checkUserBusy($time_new_task, $this->task_info['responsible']['id'])[$this->task_info['responsible']['id']];
        $busytasks = $this->model->checkUserTasks($time_new_task, $this->task_info['responsible']['id'])['tasks'];
        $tasks = $this->getCalendarTaskList($busyness, $busytasks);
        
        $tasks = $this->unsetDuplicateTasks($tasks);
    
        foreach ($tasks as $i => $task)
            if ($task['ID'] == $this->task_info['id'])
                unset($tasks[$i]);
        
        $tasks = array_values($tasks);

        $tasks = $this->sortTaskArrAsc($tasks);
        
        if ($tasks)
            $this->shiftTasks($tasks, $time_new_task);
    }
    
    protected function updateTask(int $event_id): void
    {
        $event_info = $this->model->getEventByID($event_id);
        $time = $this->getTimeArrayEvent($event_info);
        $description = $this->task_info['description']
            . EVENT_URL[0]
            . $this->task_info['responsible']['id']
            . EVENT_URL[1]
            . http_build_query([
                'EVENT_ID'   => $event_info['ID'],
                'EVENT_DATE' => $time['event_date']
            ])
            . EVENT_URL[2];
        
        $fields = [
            'taskId' => $this->task_info['id'],
            'fields' => [
                'DESCRIPTION' => $description
            ]
        ];
        $result = $this->model->letsREST('tasks.task.update', $fields);
        
        if (isset($result['error']))
            throw new ControllerException('Не получилось обновить Задачу ' . $this->task_info['id']
                . '. REST API отвечает => ' . $result['error_description']);
        
        $this->toLog('В Задачу ' . $this->task_info['id'] . ' добавлена ссылка на Событие ' . $event_id);
    }
    
    protected function shiftTasks(array $tasks, array $time_new_task): void
    {
        foreach ($tasks as $i => $task)
        {
            if ($this->isCollision($time_new_task, $task))
            {
                $interval = $this->getInterval($time_new_task, $task);
                
                $tmp['DATE_FROM'] = DateTime::createFromFormat(TIME_FORMAT['native'], $task['DATE_FROM'])->add($interval);
                $tmp['DATE_TO'] = DateTime::createFromFormat(TIME_FORMAT['native'], $task['DATE_TO'])->add($interval);
                $t_s = clone $tmp['DATE_TO'];
    
                $t_s->setTime($this->calendar_settings['work_time_end'], '00');
                if ($tmp['DATE_TO'] > $t_s)
                {
                    $int = $tmp['DATE_FROM']->diff($tmp['DATE_TO']);
                    $tmp['DATE_TO'] = clone $tmp['DATE_FROM']->modify('+ 1 day')
                        ->setTime($this->calendar_settings['work_time_start'], '00');
                    $tmp['DATE_TO']->add($int);
                }
                
                $tasks[$i]['DATE_FROM'] = $tmp['DATE_FROM']->format(TIME_FORMAT['native']);
                $tasks[$i]['DATE_TO'] = $tmp['DATE_TO']->format(TIME_FORMAT['native']);
                
                $tasks[$i]['SHIFTED'] = true;
                $time_new_task['DATE_TO'] = $tasks[$i]['DATE_TO'];
            }
            else
                $tasks[$i]['SHIFTED'] = false;
        }

        foreach ($tasks as $task)
        {
            if ($task['SHIFTED'])
            {
                $time = $this->getTimeArrayEvent($task);
                $this->model->shiftTask($task['ID'], $time);
            }
        }
    }
}