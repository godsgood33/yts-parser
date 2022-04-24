<?php

require_once dirname(__DIR__).'/vendor/autoload.php';

use Godsgood33\YTS\YTS;
use Godsgood33\YTS\DotEnv;
use Godsgood33\YTS\TransServer;

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
        if ($remainingSpace > 0) {
            $ts->start($tor);
        }

        $match = [];
        $resolution = 'HD';
        if (preg_match("/(720p|1080p|2160p)/", $tor->getName(), $match)) {
            $resolution = $match[1];
        }

        $ret = [
            'remainingSpace' => $remainingSpace,
            'humanReadableSpace' => $ts->humanReadableSize($remainingSpace),
            'torrentName' => $tor->getName(),
            'resolution' => $resolution
        ];

        print header('Content-Type: application/json').
            json_encode($ret);
    }
} elseif (isset($_POST['term']) && $_POST['term']) {
    print $yts->autoComplete($_POST['term']);
}
