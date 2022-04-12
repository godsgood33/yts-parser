<?php

require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/YTS.php';
require_once __DIR__.'/Movie.php';
require_once __DIR__.'/TransServer.php';
require_once __DIR__.'/DotEnv.php';

use YTS\YTS;
use Godsgood33\DotEnv;
use YTS\TransServer;

$action = $_POST['action'];
$yts = new YTS();

DotEnv::load(__DIR__.'/.env');

if ($action == 'updateDownload') {
    $title = urldecode($_POST['title']);
    $year = urldecode($_POST['year']);
    $res = $yts->updateDownload($title, $year);

    if ($res) {
        $ts = new TransServer(
            getenv('TRANSMISSION_DOWNLOAD_DIR'),
            getenv('TRANSMISSION_URL'),
            getenv('TRANSMISSION_PORT'),
            getenv('TRANSMISSION_USER'),
            getenv('TRANSMISSION_PASSWORD')
        );
        $movie = $yts->getMovie($title, $year);
        $ts->checkForDownload($movie);
    }
} elseif ($action == 'autoComplete') {
    $yts->autoComplete($_POST['search']);
}
