<?php

namespace core\controller;

use DateTime;
use core\base\controller\BaseController;
use core\base\exception\ControllerException;

class OnCalendarEntryUpdateController extends BaseController
{
    protected array $event_info;
    protected array $task_info;
    protected array $calendar_settings;
    
    protected function start(): void
    {
        $event_id = $_POST['data']['id'];
        $task = $this->model->getTaskByEventID($event_id);
        $this->event_info = $this->model->getEventByID($event_id);
        $this->task_info = $this->model->getTaskByID($task['task_id']);
        $this->toLog('Изменено Событие '
            . $this->event_info['ID'] . ' ' . $this->event_info['NAME'], null, 'ONCALENDARENTRYUPDATE',true);
        
        $this->calendar_settings = $this->model->getCalendarSettings();
        $this->checkChanges();
    }
    
    protected function checkChanges(): void
    {
        $time = $this->getTimeArrayTask($this->task_info);
        if (($time['DATE_FROM'] !== $this->event_info['DATE_FROM']) || ($time['DATE_TO'] !== $this->event_info['DATE_TO']))
        {
            $time_event = $this->getTimeArrayEvent($this->event_info);
            $fields = [
                'taskId' => $this->task_info['id'],
                'fields' => [
                    'START_DATE_PLAN' => $time_event['start_date_plan'],
                    'END_DATE_PLAN' => $time_event['end_date_plan'],
                ]
            ];
            $result = $this->model->letsREST('tasks.task.update', $fields);
    
            if (isset($result['error']))
                throw new ControllerException('Не получилось обновить Задачу ' . $this->task_info['id']
                    . '. REST API отвечает => ' . $result['error_description']);
            
            $this->toLog('В Задаче ' . $this->task_info['id'] . ' установлено время События ' . $this->event_info['ID']);
    
            $this->task_info['startDatePlan'] = $time_event['start_date_plan'];
            $this->task_info['endDatePlan'] = $time_event['end_date_plan'];
            $time = $this->getTimeArrayTask($this->task_info);
            
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
    
            $this->model->updateEvent($this->event_info);
        }
        else
            $this->toLog('Поля [DATE_FROM] и [DATE_TO] в Событии ' . $this->event_info['ID'] . ' не изменились.');
    }
    
    protected function shiftTasks(array $tasks, array $time_new_task): void
    {
        foreach ($tasks as $i => $task)
        {
            if ($this->isCollision($time_new_task, $task))
            {
                $this->toLog($task['ID'], null, 'Нашли коллизию');
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
                $this->toLog($task['ID'], null, 'Переносим задачу');
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