<?php

namespace core\base\settings;

use core\base\controller\Singleton;

class Settings
{
    use Singleton;
    
    private $routes = [
        'ONTASKADD'    => [
            'path'       => 'core/controller/',
            'controller' => 'OnTaskAdd'
        ],
        'ONTASKUPDATE' => [
            'path'       => 'core/controller/',
            'controller' => 'OnTaskUpdate'
        ],
        'ONTASKDELETE' => [
            'path'       => 'core/controller/',
            'controller' => 'OnTaskDelete'
        ],
        'ONCALENDARENTRYUPDATE' => [
            'path'       => 'core/controller/',
            'controller' => 'OnCalendarEntryUpdate'
        ]
    ];
    
    static public function get($property)
    {
        return self::instance()->$property;
    }
}