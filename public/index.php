<?php

require_once dirname(__DIR__).'/vendor/autoload.php';

use YTS\DotEnv;

DotEnv::$DEFINE = false;
DotEnv::$LOAD_ENV = false;
DotEnv::load(dirname(__DIR__).'/.env');

require_once dirname(__DIR__).'/config/routes.php';
