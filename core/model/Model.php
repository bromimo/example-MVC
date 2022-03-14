<?php

namespace core\model;

use core\base\exception\ModelException;
use core\base\controller\Singleton;
use core\base\model\BaseModel;
use SQLite3;

class Model extends BaseModel
{
    use Singleton;
    
    protected $db;
    
    protected function __construct()
    {
        if (!file_exists(SQLITE_DB['path'] . SQLITE_DB['name']))
        {
            $this->db = new SQLite3(SQLITE_DB['path'] . SQLITE_DB['name']);
            $query = "DROP TABLE IF EXISTS task_event";
            $this->db->query($query);
            $query = "CREATE TABLE task_event(task_id INTEGER PRIMARY KEY, event_id INTEGER, owner_id INTEGER)";
            $result = $this->db->query($query);
            if (!$result)
                throw new ModelException('Не удалось создать таблицу task_event в БД <' . $query . '>');
            
            $query = "CREATE TABLE task_move(task_id INTEGER PRIMARY KEY)";
            $result = $this->db->query($query);
            if (!$result)
                throw new ModelException('Не удалось создать таблицу task_move в БД <' . $query . '>');
        }
        else
            $this->db = new SQLite3(SQLITE_DB['path'] . SQLITE_DB['name']);
    }
    
    public function getEventByTaskID(int $task_id): array
    {
        $query = "SELECT event_id, owner_id FROM task_event WHERE task_id = $task_id";
        $result = $this->db->query($query);
        
        if (!$result)
            throw new ModelException('Не удалось найти событие в БД <' . $query . '>');
        
        $result = $result->fetchArray();
        if (!$result)
            throw new ModelException('Не найдено событие к задаче ' . $task_id);
        
        return $result;
    }
    
    public function getTaskByEventID(int $event_id): array
    {
        $query = "SELECT task_id, owner_id FROM task_event WHERE event_id = $event_id";
        $result = $this->db->query($query);
        
        if (!$result)
            throw new ModelException('Не удалось найти задачу в БД <' . $query . '>');
        
        $result = $result->fetchArray();
        if (!$result)
            throw new ModelException('Не найдена задача к событию ' . $event_id);
        
        return $result;
    }
    
    public function createEvent(array $parameters): void
    {
        if (!$this->isArrValid($parameters, ['task_id', 'event_id', 'owner_id']))
            throw new ModelException('Некорректный массив параметров ' . print_r($parameters));
        
        $query = "INSERT OR IGNORE INTO task_event VALUES ({$parameters['task_id']}, {$parameters['event_id']}, {$parameters['owner_id']})";
        $result = $this->db->query($query);
        
        if (!$result)
            throw new ModelException('Не удалось создать событие в БД <' . $query . '>');
    }
    
    public function deleteEvent(array $event): void
    {
        if (!$this->isArrValid($event, ['event_id', 'owner_id']))
            throw new ModelException('Некорректный массив параметров ' . print_r($event));
        
        $query = "DELETE FROM task_event WHERE event_id = {$event['event_id']}";
        $result = $this->db->query($query);
        
        if (!$result)
            throw new ModelException('Не удалось удалить событие в БД <' . $query . '>');
        
        parent::deleteEvent($event);
    }
    
    public function shiftTask(int $task_id, array $time): void
    {
        $i = 10;
        do
        {
            usleep(USLEEP);
            $query = "INSERT OR IGNORE INTO task_move VALUES ($task_id)";
            $result = $this->db->query($query);
    
            if (!$i--)
                throw new ModelException('Не удалось создать запись о перемещаемой задаче в БД <' . $query . '>' . $this->db->lastErrorMsg());
        }
        while (!$result);
        
        $this->toLog('Установлена метка для задачи ' . $task_id);
        parent::shiftTask($task_id, $time);
    }
    
    public function deleteMoveTask(int $task_id): void
    {
        $query = "DELETE FROM task_move WHERE task_id = $task_id";
        $result = $this->db->query($query);
        
        if (!$result)
            throw new ModelException('Не удалось создать запись о перемещаемой задаче в БД <' . $query . '>');
        
        $this->toLog('Удалена метка для задачи ' . $task_id);
    }
    
    public function isHandMovedTask(int $task_id): bool
    {
        $i = 10;
        do
        {
            usleep(USLEEP);
            $query = "SELECT task_id FROM task_move WHERE task_id = $task_id";
            $result = $this->db->query($query);
        
            if (!$i--)
                throw new ModelException('Не удалось найти перемещаемую задачу в БД <' . $query . '>' . $this->db->lastErrorMsg());
        }
        while (!$result);
        
        $result = $result->fetchArray();
        
        if (!$result)
            return true;
        
        $this->toLog('Найдена метка для задачи ' . $task_id);
        
        return false;
    }
}