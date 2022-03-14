<?php

namespace core\base\model;

use DateTime;
use core\base\controller\BaseMethods;
use core\base\exception\ModelException;

abstract class BaseModel
{
    protected int $timezone;
    
    use BaseMethods;
    
    /** Отправляет запрос к REST API.
     *
     * @param string     $method
     * @param array|null $fields
     *
     * @return array
     * @throws \core\base\exception\ModelException
     */
    public function letsREST(string $method, ?array $fields = []): array
    {
        usleep(USLEEP);
        $url = REST_API_WEBHOOK_URL . $method;
        $queryData = http_build_query($fields);
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POST           => true,
            CURLOPT_HEADER         => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL            => $url,
            CURLOPT_POSTFIELDS     => $queryData,
        ]);
        
        $result = curl_exec($curl);
        curl_close($curl);
        
        return json_decode($result, true);
    }

    public function getCalendarSettings(): array
    {
        $result = $this->letsREST('calendar.settings.get');
        if ($result['result'])
            return $result['result'];
        else
            throw new ModelException('Не могу найти настройки Календаря');
    }
    
    /** Проверяет настройки доступа к REST API.
     * @throws \core\base\exception\ModelException
     */
    public function testREST(): void
    {
        $result = $this->letsREST('profile');
//        $this->toLog($result);
        
        if (isset($result['error']))
            throw new ModelException('Константа REST_API_WEBHOOK_URL содержит некорректный токен ' . REST_API_WEBHOOK_URL);
        
        $server = (new DateTime('now'))->format('Z');
        $portal = DateTime::createFromFormat(TIME_FORMAT['iso'], $result['time']['date_start'])->format('Z');
        $this->timezone = $portal - $server;
    }
    
    public function addOneHourCrutch(array $time): array
    {
        foreach ($time as $i => $t)
            $time[$i] = DateTime::createFromFormat(TIME_FORMAT['native'], $t)
                ->modify('+' . $this->timezone . ' sec')
                ->format(TIME_FORMAT['native']);
        return $time;
    }
    
    public function subOneHourCrutch(array $time): array
    {
        foreach ($time as $i => $t)
            $time[$i] = DateTime::createFromFormat(TIME_FORMAT['native'], $t)
                ->modify('-' . $this->timezone . ' sec')
                ->format(TIME_FORMAT['native']);
        
        return $time;
    }
    
    /** Ищем задачу по ID.
     *
     * @param int $task_id
     *
     * @return array
     * @throws \core\base\exception\ModelException
     */
    public function getTaskByID(int $task_id): array
    {
        $result = $this->letsREST('tasks.task.get', ['id' => $task_id]);
        if ($result['result'])
            return $result['result']['task'];
        else
            throw new ModelException('Не могу найти Задачу ' . $task_id);
    }
    
    /** Ищем секцию календаря по ID пользователя.
     *
     * @param string $user_id
     *
     * @return int
     * @throws \core\base\exception\ModelException
     */
    public function getCalendarSection(int $user_id): array
    {
        $result = $this->letsREST('calendar.section.get',
            [
                'type'    => 'user',
                'ownerId' => $user_id
            ]);
        
        if ($result['result'])
            return $result['result'];
        else
            throw new ModelException('Не могу найти Календарь пользователя ' . $user_id);
    }
    
    /** Ищем событие по его ID.
     *
     * @param string $event_id
     *
     * @return array
     * @throws \core\base\exception\ModelException
     */
    public function getEventByID(string $event_id): array
    {
        $result = $this->letsREST('calendar.event.getbyid', ['id' => $event_id]);
        
        if ($result['result'])
            return $result['result'];
        else
            throw new ModelException('Не могу найти Событие ' . $event_id);
    }
    
    /** Возвращает массив всех пользователей.
     * @return array
     * @throws \core\base\exception\ModelException
     */
    public function getUsers(): array
    {
        $result = $this->letsREST('user.get');
        if (!$result['next'])
            return $result;
        
        $batch = [];
        
        for ($i = 0; $i < $result['total']; $i += $result['next'])
            $batch[] = 'user.get?' . http_build_query(['start' => $i]);
        
        $result = $this->letsREST('batch', ['cmd' => $batch]);
        
        return $result['result'];
    }
    
    public function getUser(int $user_id): array
    {
        $fields = [
            'ID' => $user_id
        ];
        
        $result = $this->letsREST('user.get', $fields);
        
        return $result['result'][0];
    }
    
    public function getDepartment(int $department_id): array
    {
        $fields = [
            'ID' => $department_id
        ];
        
        $result = $this->letsREST('department.get', $fields);
        
        return $result['result'][0];
    }
    
    public function deleteEvent(array $event): void
    {
        $fields = [
            'type'    => 'user',
            'ownerId' => $event['owner_id'],
            'id'      => $event['event_id']
        ];
        
        $result = $this->letsREST('calendar.event.delete', $fields);
        
        if (isset($result['error']))
            throw new ModelException('Не получилось удалить Событие ' . $event['event_id']
                . '. REST API отвечает => ' . $result['error_description']);
        
        $this->toLog('Удалено Событие ' . $event['event_id']);
    }
    
    public function checkUserBusy(array $time, int $user_id): array
    {
        $time_from = DateTime::createFromFormat(TIME_FORMAT['native'], $time['DATE_FROM'])
            ->setTime(0, 0)
            ->format(TIME_FORMAT['native']);
    
        $time_to = DateTime::createFromFormat(TIME_FORMAT['native'], $time['DATE_FROM'])
            ->setTime(0, 0)
            ->modify('+ 1 day')
            ->format(TIME_FORMAT['native']);
        
        $fields = [
            'users' => [$user_id],
            'from'  => $time_from,
            'to'    => $time_to
        ];
        
        $result = $this->letsREST('calendar.accessibility.get', $fields);
        
        return $result['result'];
    }

    public function checkUserTasks(array $time, int $user_id): array
    {
        $time = $this->nativeToIsoTime($time);
    
        $time_from = DateTime::createFromFormat(TIME_FORMAT['iso'], $time['DATE_FROM'])
            ->setTime(0, 0)
            ->format(TIME_FORMAT['iso']);
    
        $time_to = DateTime::createFromFormat(TIME_FORMAT['iso'], $time['DATE_FROM'])
            ->setTime(0, 0)
            ->modify('+ 1 day')
            ->format(TIME_FORMAT['iso']);
        
        $fields = [
            'filter' => [
                '>=START_DATE_PLAN' => $time_from,
                '<END_DATE_PLAN' => $time_to,
                '=RESPONSIBLE_ID' => $user_id
            ],
            'select' => [],
            'order' => [
                'START_DATE_PLAN' => 'asc'
            ]
        ];
    
        $result = $this->letsREST('tasks.task.list', $fields);

        if (isset($result['error']))
            throw new ModelException('Не удалось получить список Задач '
                . '. REST API отвечает => ' . $result['error_description']);
    
        return $result['result'];
    }
    
    public function shiftTask(int $task_id, array $time): void
    {
        $fields = [
            'taskId' => $task_id,
            'fields' => [
                'START_DATE_PLAN' => $time['start_date_plan'],
                'END_DATE_PLAN'   => $time['end_date_plan']
            ]
        ];
        $result = $this->letsREST('tasks.task.update', $fields);
        
        if (isset($result['error']))
            throw new ModelException('Не получилось перенести Задачу ' . $task_id
                . '. REST API отвечает => ' . $result['error_description']);
        
        $this->toLog('Задача ' . $task_id . ' перенесена');
    }
    
    public function updateEvent(array $event_info): void
    {
        $fields = [
            'id'      => $event_info['ID'],
            'type'    => $event_info['CAL_TYPE'],
            'ownerId' => $event_info['OWNER_ID'],
            'section' => $event_info['SECTION_ID'],
            'name'    => $event_info['NAME']
        ];
    
        $result = $this->letsREST('calendar.event.update', $fields);
    
        if (isset($result['error']))
            throw new ModelException('Не получилось обновить Событие ' . $event_info['ID']
                . '. REST API отвечает => ' . $result['error_description']);
    }
}