<?php

use Bramus\Router\Router;

use YTS\YTS;
use YTS\TransServer;
use jc21\Util\Size;

$router = new Router();

$router->get('/', function () {
    require_once(dirname(__DIR__).'/pages/home.php');
});

$router->get('/movie/{title}/year/{year}/', function ($encodedTitle, $year) {
    $yts = new YTS();
    $title = urldecode($encodedTitle);
    $movie = $yts->getMovie($title, $year);

    $movie->getDetails();
});

$router->get('/new-movies', function () {
    require_once(dirname(__DIR__).'/pages/new-movies.php');
});

$router->get('/duplicates', function () {
    require_once(dirname(__DIR__).'/pages/duplicates.php');
});

$router->post('/search', function () {
    $query = urldecode($_POST['term']);
    $yts = new YTS();
    print $yts->search($query);
});

$router->post('/download', function () {
    die(print_r($_POST, true));
    $yts = new YTS();
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
});

$router->post('/deleteMovie', function () {
    $yts = new YTS();
    $title = urldecode($_POST['title']);
    $year = filter_var($_POST['year'], FILTER_VALIDATE_INT);
    $res = $yts->deleteMovie($title, $year);
    print header('Content-type: application/json').
        json_encode([
            'success' => $res,
            'title' => $title,
            'year' => $year,
        ]);
});

$router->post('/status', function () {
    $ts = new TransServer();
    $totalDownloadSize = new Size($ts->downloadSize);
    $totalFreeSpace = new Size($ts->freeSpace);
    $torrentCount = 0;
    $activeTorrent = 0;
    $torrentComplete = 0;

    foreach ($ts->all() as $tor) {
        $torrentCount++;
        if ($tor->isDownloading()) {
            $activeTorrent++;
        }
        if ($tor->isFinished()) {
            $torrentComplete++;
        }
    }

    print header('Content-type: application/json').
        json_encode([
            'downloadSize' => $totalDownloadSize->GB(),
            'freeSpace' => $totalFreeSpace->GB(),
            'torrents' => $torrentCount,
            'active' => $activeTorrent,
            'complete' => $torrentComplete
        ]);
});

$router->run();
