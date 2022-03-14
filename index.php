<?php

const ACCESS = true;

require_once 'config.php';
require_once 'core/base/settings/base_settings.php';

use core\base\controller\RouteController;
use core\base\exception\RouteException;
use core\base\exception\ControllerException;
use core\base\exception\ModelException;

try
{
    
    RouteController::instance()->route();
}
catch (RouteException $e)
{
    exit($e->getMessage());
}
catch (ControllerException $e)
{
    exit($e->getMessage());
}
catch (ModelException $e)
{
    exit($e->getMessage());
}