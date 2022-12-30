<?php

ini_set("error_reporting", "~DEPRECATED");

require_once dirname(__DIR__).'/vendor/autoload.php';

use YTS\DotEnv;

define('ROOT', dirname(__DIR__));

DotEnv::$DEFINE = false;
DotEnv::$LOAD_ENV = false;
DotEnv::load(ROOT.'/.env');

require_once ROOT.'/config/routes.php';
