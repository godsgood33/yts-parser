<?php

require_once dirname(__DIR__).'/vendor/autoload.php';

use YTS\YTS;
use YTS\DotEnv;
use YTS\TransServer;

DotEnv::$DEFINE = false;
DotEnv::$LOAD_ENV = false;
DotEnv::load(dirname(__DIR__).'/.env');

$action = $_POST['action'] ?? null;
$yts = new YTS();

if ($action == 'updateDownload' || $action == 'download') {
    $title = urldecode($_POST['title']);
    $year = (int) urldecode($_POST['year']);
    $res = $yts->updateDownload($title, $year);

    if ($res) {
        $ts = new TransServer();
        $movie = $yts->getMovie($title, $year);
        $tor = $ts->checkForDownload($movie);
        $remainingSpace = $ts->freeSpace - ($ts->downloadSize + (int) $tor?->getSize());
        if ($remainingSpace > 0 /*&&
            (
                $tor->getStatus() != Status::STATUS_DOWNLOAD || $tor->getStatus() != Status::STATUS_DOWNLOAD_WAIT
            )*/) {
            $ts->start($tor);
        }

        $match = [];
        $resolution = '720p';
        if (preg_match("/(720p|1080p|2160p)/", $tor->getName(), $match)) {
            $resolution = $match[1];
        }

        $class = 'havehd';
        if ($resolution == '1080p') {
            $movie->hdComplete = true;
            $movie->fhdComplete = true;
            $class = 'havefhd';
        } elseif ($resolution == '2160p') {
            $movie->hdComplete = true;
            $movie->fhdComplete = true;
            $movie->uhdComplete = true;
            $class = 'have4k';
        }

        $yts->updateMovie($movie);

        $ret = [
            'remainingSpace' => $remainingSpace,
            'humanReadableSpace' => $ts->humanReadableSize($remainingSpace),
            'torrentName' => $tor->getName(),
            'resolution' => $resolution,
            'class' => $class,
        ];

        print header('Content-Type: application/json').
            json_encode($ret);
    }
} elseif ($action == 'search') {
    print $yts->search($_POST['term']);
}
