<?php

namespace core\controller;

use core\base\controller\BaseController;

class OnTaskDeleteController extends BaseController
{
    protected int $task_id;
    
    protected function start()
    {
        $this->task_id = $_POST['data']['FIELDS_BEFORE']['ID'];
        $this->toLog('Удалена Задача ' . $this->task_id, null, 'ONTASKDELETE', true);
    
        $event = $this->model->getEventByTaskID($this->task_id);
        
        $this->model->deleteEvent($event);
        
    }
    
}