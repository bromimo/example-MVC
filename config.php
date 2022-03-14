<?php

defined('ACCESS') or die('Доступ к файлу запрещен!');

date_default_timezone_set('Europe/Kiev');

function autoloadMainClasses(string $class_name): void
{
    $class_name = str_replace('\\', '/', $class_name);
    @include_once $class_name . '.php';
}

spl_autoload_register('autoloadMainClasses');

