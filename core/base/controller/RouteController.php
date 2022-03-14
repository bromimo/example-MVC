<?php

namespace core\base\controller;

use core\base\settings\Settings;
use core\base\exception\RouteException;

class RouteController extends BaseController
{
    use Singleton;
    
    protected $routes;
    
    private function __construct()
    {
        if (!$this->isPost())
            throw new RouteException('Попытка прямого запроса без $_POST.');
        
        if (!isset($_POST['event']))
            throw new RouteException('Отсутствует поле [event] в $_POST.');
        
        if (!$_POST['event'])
            throw new RouteException('Поле [event] в $_POST пустое.');
        
        $this->routes = Settings::get('routes');
        
        if (!$this->routes)
            throw new RouteException('Отсутствуют маршруты в базовых настройках', 1);
        
        $this->controller = $this->routes[$_POST['event']]['path'];
        $this->controller .= $this->routes[$_POST['event']]['controller'] . 'Controller';
    }
}