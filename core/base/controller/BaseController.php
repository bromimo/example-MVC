<?php

namespace core\base\controller;

use ReflectionMethod;
use ReflectionException;
use core\model\Model;
use core\base\exception\RouteException;

abstract class BaseController
{
    use BaseMethods;
    
    protected Model $model;
    protected string $controller;
    
    /** Пытается создать найденый контроллер.
     * @throws \core\base\exception\RouteException
     */
    public function route(): void
    {
        $controller = str_replace('/', '\\', $this->controller);
        try
        {
            $object = new ReflectionMethod($controller, 'request');
            $object->invoke(new $controller);
        }
        catch (ReflectionException $e)
        {
            throw new RouteException($e->getMessage());
        }
    }
    
    /** Инициализируем и запускаем контроллер.
     * @throws \core\base\exception\ModelException
     */
    public function request(): void
    {
        sleep(1);
        $this->model = Model::instance();
        $this->model->testREST();
        $start = 'start';
        $this->$start();
    }
}