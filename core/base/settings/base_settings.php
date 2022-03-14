<?php

defined('ACCESS') or die('Доступ к файлу запрещен!');

const LOG_FILE = [
    'name' => 'log.txt',
    'path' => 'logs/',
    'size' => 100000,
    'term' => '30 days'
];

const TIME_FORMAT = [
    'logs' => 'Y-m-d H:i:s',
    'iso' => 'Y-m-d\TH:i:sP',
    'native' => 'd.m.Y H:i:s'
];

const PREG_TASK_NUMBER = '/\s\([0-9]+\)$/';
const USLEEP = 1000000;
const MESSAGES_PATH = 'core/base/messages/';
const SQLITE_DB = [
    'name' => 'bondar.db',
    'path' => 'core/model/'
];

// const REST_API_WEBHOOK_URL = 'https://';
const REST_API_WEBHOOK_URL = 'https://';

const TASK_URL = [
    '[P][URL=https://beta.webhub.one/company/personal/user/',
    '/tasks/task/view/',
    '/]Задача[/URL][/P]'
];

const EVENT_URL = [
    '[P][URL=https://beta.webhub.one/company/personal/user/',
    '/calendar/?',
    '/]Событие[/URL][/P]'
];