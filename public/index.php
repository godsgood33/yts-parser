<?php

require_once dirname(__DIR__).'/vendor/autoload.php';

use YTS\DotEnv;

DotEnv::$DEFINE = false;
DotEnv::$LOAD_ENV = false;
DotEnv::load(dirname(__DIR__).'/.env');

define('ROOT', dirname(__DIR__));

require_once dirname(__DIR__).'/config/routes.php';
