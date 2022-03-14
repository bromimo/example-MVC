<?php

namespace core\base\controller;

use DateTime;
use DateInterval;

trait BaseMethods
{
    /** Сохраняет $message в лог.
     *
     * @param                   $message
     * @param string|mixed|null $file_name
     * @param string|null       $event
     */
    protected function toLog(/*mixed*/ $message, ?string $file_name = '', ?string $event = '', ?bool $paragraph = false): void
    {
        $file_name = $file_name ? : LOG_FILE['name'];
        $this->cutLog((string) $file_name);
        $event = $event ? ' [' . $event . '] ' : ' ';
        $dateTime = new DateTime();
        $str = $dateTime->format(TIME_FORMAT['logs']) . $event . print_r($message, true) . "\n";
        $str = $paragraph ? "\n" . $str : $str;
        file_put_contents(LOG_FILE['path'] . $file_name, $str, FILE_APPEND);
    }
    
    /** Обрезает лог по заданным в константе LOG_FILE параметрам.
     *
     * @param string $file_name
     */
    protected function cutLog(string $file_name): void
    {
        if (!file_exists(LOG_FILE['path'] . $file_name))
            return;
        
        if (filesize(LOG_FILE['path'] . $file_name) < LOG_FILE['size'])
            return;
        
        if (!$file = file(LOG_FILE['path'] . $file_name))
            return;
        
        $i = -1;
        $new_file = [];
        $is_piece_of_old_text = true;
        $today = (new DateTime())->setTime(0, 0);
        $from_date = $today->modify('-' . LOG_FILE['term']);
        
        foreach ($file as $row)
        {
            if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}\s[0-9]{2}:[0-9]{2}:[0-9]{2}/', $row, $matches))
            {
                $row_date = DateTime::createFromFormat(TIME_FORMAT['logs'], $matches[0]);
                if ($row_date < $from_date)
                    continue;
                $new_file[++$i] = $row;
                $is_piece_of_old_text = false;
            }
            else
            {
                if ($is_piece_of_old_text)
                    continue;
                $new_file[$i] .= $row;
            }
        }
        file_put_contents(LOG_FILE['path'] . $file_name, implode($new_file), LOCK_EX);
    }
    
    /** Проверяет, пришел ли запрос POST.
     * @return bool
     */
    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] == 'POST';
    }

    protected function getTimeArrayTask(array $task_info): array
    {
        $time['DATE_FROM'] = DateTime::createFromFormat(TIME_FORMAT['iso'], $task_info['startDatePlan'])
            ->format(TIME_FORMAT['native']);
        $time['DATE_TO'] = DateTime::createFromFormat(TIME_FORMAT['iso'], $task_info['endDatePlan'])
            ->format(TIME_FORMAT['native']);
        
        return $time;
    }
    
    protected function getTimeArrayEvent(array $event_info): array
    {
        $time['start_date_plan'] = DateTime::createFromFormat(TIME_FORMAT['native'], $event_info['DATE_FROM'])
            ->format(TIME_FORMAT['iso']);
        $end_date = DateTime::createFromFormat(TIME_FORMAT['native'], $event_info['DATE_TO']);
        $time['end_date_plan'] = $end_date->format(TIME_FORMAT['iso']);
        
        if ($event_info['DT_SKIP_TIME'] === 'Y')
        {
            $add_dead_time = $end_date->modify('+ 1 days');
            $time['time_estimate'] = '28800';
        }
        else
        {
            $add_dead_time = $end_date->modify('+ 5 hours');
            $time['time_estimate'] = $event_info['DATE_TO_TS_UTC'] - $event_info['DATE_FROM_TS_UTC'];
        }
        $time['dead_line'] = $add_dead_time->format(TIME_FORMAT['iso']);
        $time['event_date'] = $event_info['DATE_FROM'];
        
        return $time;
    }

    protected function nativeToIsoTime(array $time): array
    {
        foreach ($time as $i => $t)
            $time[$i] = DateTime::createFromFormat(TIME_FORMAT['native'], $t)
                ->format(TIME_FORMAT['iso']);
        return $time;
    }

    protected function isoToNativeTime(array $time): array
    {
        foreach ($time as $i => $t)
            $time[$i] = DateTime::createFromFormat(TIME_FORMAT['iso'], $t)
                ->format(TIME_FORMAT['native']);
        return $time;
    }
    
    /** Проверяет массив $haystack на наличие ключей из массива $needle и наличие значений у ключей.
     *
     * @param array $haystack
     * @param array $needle
     *
     * @return bool
     */
    protected function isArrValid(array $haystack, array $needle): bool
    {
        foreach ($needle as $key)
            if (!isset($haystack[$key]) || !$haystack[$key])
                return false;
        
        return true;
    }
    
    protected function isCollision(array $time_task, array $time_calendar): bool
    {
        foreach ($time_task as $key => $time)
            $time_task[$key] = DateTime::createFromFormat(TIME_FORMAT['native'], $time_task[$key]);
        foreach ($time_calendar as $key => $time)
            $time_calendar[$key] = DateTime::createFromFormat(TIME_FORMAT['native'], $time_calendar[$key]);
        if (($time_task['DATE_FROM'] < $time_calendar['DATE_TO']) &&
            ($time_task['DATE_TO'] > $time_calendar['DATE_FROM']))
            return true;
        
        return false;
    }
    
    protected function getInterval(array $time_task, array $time_calendar): DateInterval
    {
        $time_task_last = DateTime::createFromFormat(TIME_FORMAT['native'], $time_task['DATE_TO']);
        $time_calendar_first = DateTime::createFromFormat(TIME_FORMAT['native'], $time_calendar['DATE_FROM']);
        $interval = $time_calendar_first->diff($time_task_last);
        
        return $interval;
    }
    
    protected function getTaskIdFromEventName(string $event_name): int
    {
        preg_match('/\([0-9]+\)$/', $event_name, $matches);
        if ($matches[0])
            return (int) substr($matches[0], 1,  - 1);
        else
            return 0;
    }

    protected function getCalendarTaskList(array $busyness, array $busytasks): array
    {
        $arr = [];

        foreach ($busyness as $i => $task)
        {
            if ($task_id = $this->getTaskIdFromEventName($task['NAME']))
            {
                $arr[$i]['ID'] = $task_id;
                $arr[$i]['DATE_FROM'] = $task['DATE_FROM'];
                $arr[$i]['DATE_TO'] = $task['DATE_TO'];
            }
        }
        
        $offset = count($arr);
        
        foreach ($busytasks as $i => $task)
        {
            $arr[$offset + $i]['ID'] = $task['id'];
            $arr[$offset + $i]['DATE_FROM'] = DateTime::createFromFormat(TIME_FORMAT['iso'], $task['startDatePlan'])
                ->format(TIME_FORMAT['native']);
            $arr[$offset + $i]['DATE_TO'] = DateTime::createFromFormat(TIME_FORMAT['iso'], $task['endDatePlan'])
                ->format(TIME_FORMAT['native']);
        }        
        return $arr;
    }

    protected function sortTaskArrAsc(array $tasks): array
    {
        $arr = [];
        
        foreach ($tasks as $i => $task)
        {
            $less = $i;
            $less_time = DateTime::createFromFormat(TIME_FORMAT['native'], $tasks[$i]['DATE_FROM']);
            
            for ($j = $i + 1; $j < count($tasks); $j++)
            {
                $current_time = DateTime::createFromFormat(TIME_FORMAT['native'], $tasks[$j]['DATE_FROM']);
                if ($less_time > $current_time)
                {
                    $less_time = $current_time;
                    $less = $j;
                }
            }
            
            foreach ($tasks[$i] as $key => $value)
                $arr[$key] = $value;
            
            foreach ($tasks[$less] as $key => $value)
                $tasks[$i][$key] = $value;
            
            foreach ($arr as $key => $value)
                $tasks[$less][$key] = $value;
        }
        
        return $tasks;
    }

    protected function unsetDuplicateTasks(array $tasks): array
    {
        $arr = $tasks;
        foreach ($tasks as $i => $task)
            for ($j = $i + 1; $j < count($tasks); $j++)
                if ($tasks[$i]['ID'] == $tasks[$j]['ID'])
                    unset($arr[$j]);
        return $arr;
    }
}