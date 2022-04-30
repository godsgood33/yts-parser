<?php

require_once dirname(__DIR__).'/vendor/autoload.php';

use Transmission\Model\Status;
use YTS\YTS;
use YTS\DotEnv;
use YTS\TransServer;

$action = $_POST['action'] ?? null;
$yts = new YTS();

DotEnv::load(dirname(__DIR__).'/.env');

if ($action == 'updateDownload' || $action == 'download') {
    $title = urldecode($_POST['title']);
    $year = (int) urldecode($_POST['year']);
    $res = $yts->updateDownload($title, $year);

    if ($res) {
        $ts = new TransServer();
        $movie = $yts->getMovie($title, $year);
        $tor = $ts->checkForDownload($movie);
        $yts->updateMovie($movie);
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
            $class = 'havefhd';
        } elseif ($resolution == '2160p') {
            $class = 'have4k';
        }

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
