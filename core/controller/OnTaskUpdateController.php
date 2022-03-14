<?php

namespace core\controller;

use DateTime;
use core\base\controller\BaseController;
use core\base\exception\ControllerException;

class OnTaskUpdateController extends BaseController
{
    protected array $task_info;
    protected array $event_info;
    protected array $calendar_settings;
    
    protected function start()
    {
        $task_id = $_POST['data']['FIELDS_AFTER']['ID'];

        $this->calendar_settings = $this->model->getCalendarSettings();
        
        $this->task_info = $this->model->getTaskByID($task_id);

        $this->toLog('Изменена Задача '
            . $this->task_info['id'] . ' ' . $this->task_info['title'], null, 'ONTASKUPDATE',true);
        
        $event = $this->model->getEventByTaskID($this->task_info['id']);
        
        $this->event_info = $this->model->getEventByID($event['event_id']);
        
        $this->checkChanges();
    }
    
    /** Проверяем соответствие DateTime и названий в Задаче и Событии.
     * @throws \core\base\exception\ControllerException
     */
    protected function checkChanges(): void
    {
        $time = $this->getTimeArrayTask($this->task_info);
        
        if (($time['DATE_FROM'] !== $this->event_info['DATE_FROM']) || ($time['DATE_TO'] !== $this->event_info['DATE_TO']))
        {
            if ($this->model->isHandMovedTask($this->task_info['id']))
            {
                $busyness = $this->model->checkUserBusy($time, $this->task_info['responsible']['id'])[$this->task_info['responsible']['id']];
                $busytasks = $this->model->checkUserTasks($time, $this->task_info['responsible']['id'])['tasks'];
                $tasks = $this->getCalendarTaskList($busyness, $busytasks);
    
                $tasks = $this->unsetDuplicateTasks($tasks);
                
                foreach ($tasks as $i => $task)
                    if ($task['ID'] == $this->task_info['id'])
                        unset($tasks[$i]);
                
                $tasks = array_values($tasks);    
                $tasks = $this->sortTaskArrAsc($tasks);
                
                if ($tasks)
                    $this->shiftTasks($tasks, $time);
            }
            $this->model->deleteMoveTask($this->task_info['id']);
            $this->updateEvent($time);
        }
        else
            $this->toLog('Поля [DATE_FROM] и [DATE_TO] в Задаче ' . $this->task_info['id'] . ' не изменились.');
        
        $task_title = $this->task_info['title'];
        $event_name = $this->event_info['NAME'];
        
        $event_name = preg_split(PREG_TASK_NUMBER, $event_name)[0];
        if ($event_name != $task_title)
            $this->updateEventName($task_title . ' (' . $this->task_info['id'] . ')');
    }
    
    /** Обновляем DateTime в Событии.
     *
     * @param array $time
     *
     * @throws \core\base\exception\ControllerException
     * @throws \core\base\exception\ModelException
     */
    protected function updateEvent(array $time): void
    {
        $time = $this->model->addOneHourCrutch($time);
        $fields = [
            'id'      => $this->event_info['ID'],
            'type'    => 'user',
            'ownerId' => $this->event_info['OWNER_ID'],
            'from'    => $time['DATE_FROM'],
            'to'      => $time['DATE_TO'],
            'section' => $this->event_info['SECTION_ID'],
            'name'    => $this->event_info['NAME']
        ];
        
        $result = $this->model->letsREST('calendar.event.update', $fields);
        
        if (isset($result['error']))
            throw new ControllerException('Не получилось обновить Событие для Задачи ' . $this->task_info['id']
                . '. REST API отвечает => ' . $result['error_description']);
        
        $this->event_info = $this->model->getEventByID($this->event_info['ID']);
        $this->updateTask($this->event_info['ID']);
        
        $this->toLog('Изменено Событие ' . $result['result']);
    }
    
    protected function updateTask(int $event_id): void
    {
        $event_info = $this->model->getEventByID($event_id);
        $time = $this->getTimeArrayEvent($event_info);
        $regexp = '/' . preg_quote(EVENT_URL[0], '/') . '/';
        $str = preg_split($regexp, $this->task_info['description']);
        $description = $str[0]
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
            if ($task['ID'] == $this->event_info['ID'])
            {
                $tasks[$i]['SHIFTED'] = false;
                continue;
            }
            if ($this->isCollision($time_new_task, $task))
            {
                $this->toLog('Нашли коллизию');
                $interval = $this->getInterval($time_new_task, $task);
                
                $tmp['DATE_FROM'] = DateTime::createFromFormat(TIME_FORMAT['native'], $task['DATE_FROM'])
                    ->add($interval);
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
                $this->toLog($task['NAME'], null, 'Переносим задачу');
                // $time_new_task['DATE_FROM'] = $tasks[$i]['DATE_FROM'];
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
    
    protected function updateEventName(string $name): void
    {
        $fields = [
            'type'    => $this->event_info['CAL_TYPE'],
            'ownerId' => $this->event_info['OWNER_ID'],
            'id'      => $this->event_info['ID'],
            'section' => $this->event_info['SECTION_ID'],
            'name'    => $name
        ];
        
        $result = $this->model->letsREST('calendar.event.update', $fields);
        
        if (isset($result['error']))
            throw new ControllerException('Не получилось переименовать Событие ' . $this->event_info['ID']
                . '. REST API отвечает => ' . $result['error_description']);
        
        $this->toLog('Изменено название События ' . $this->event_info['ID']);
    }
}