<?php

use Bramus\Router\Router;

use YTS\YTS;
use YTS\TransServer;
use jc21\Util\Size;

$router = new Router();

$router->get('/', function () {
    require_once(ROOT.'/pages/home.php');
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

$router->get('/downloaded', function () {
    require_once(dirname(__DIR__).'/pages/downloaded.php');
});

$router->post('/search', function () {
    $query = urldecode($_POST['term']);
    $yts = new YTS();
    print $yts->search($query);
});

$router->post('/download', function () {
    $yts = new YTS();
    $title = urldecode($_POST['title']);
    $year = (int) urldecode($_POST['year']);
    $quality = $_POST['quality'];
    $res = $yts->updateDownload($title, $year);

    if ($res) {
        $ts = new TransServer();
        $movie = $yts->getMovie($title, $year);
        $tor = $ts->checkForDownload($movie, $quality);
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

        print header("Content-type: application/json").
            json_encode([
                'remainingSpace' => $remainingSpace,
                'humanReadableSpace' => $ts->humanReadableSize($remainingSpace),
                'torrentName' => $tor->getName(),
                'resolution' => $resolution,
                'class' => $class,
            ]);
    }
});

$router->post('/deleteMovie', function () {
    $yts = new YTS();
    $title = urldecode($_POST['title']);
    $year = filter_var($_POST['year'], FILTER_VALIDATE_INT);

    $res = $yts->deleteMovie($title, $year);
    print header("Content-type: application/json").
        json_encode([
            'success' => $res,
            'title' => $title,
            'year' => $year,
            'movieCount' => $yts->getMovieCount()
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

    print header("Content-type: application/json").
        json_encode([
            'downloadSize' => $totalDownloadSize->GB(),
            'freeSpace' => $totalFreeSpace->GB(),
            'torrents' => $torrentCount,
            'active' => $activeTorrent,
            'complete' => $torrentComplete
        ]);
});

$router->post('/update-count', function () {
    $movie_count = $_POST['movieCount'];
    $str = file_get_contents(ROOT.'/.env');
    $match = [];
    $res = preg_match("/MOVIE_COUNT\=(\d+)/", $str, $match);
    if ($res) {
        $env = str_replace(
            "MOVIE_COUNT={$match[1]}",
            "MOVIE_COUNT={$movie_count}",
            $str
        );
        file_put_contents(ROOT.'/.env', $env);
    }

    print header("Content-type: application/json").
        json_encode([
            'msg' => "Updated count to {$movie_count}"
        ]);
});

$router->get('/plex', function () {
    require_once(dirname(__DIR__).'/pages/plex-movies.php');
});

$router->run();
