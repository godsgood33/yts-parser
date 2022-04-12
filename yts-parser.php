<?php

require_once __DIR__."/vendor/autoload.php";
require_once __DIR__."/Movie.php";
require_once __DIR__."/DotEnv.php";
require_once __DIR__."/TransServer.php";
require_once __DIR__.'/YTS.php';
require_once __DIR__.'/Plex.php';

use YTS\Movie;
use YTS\Plex;
use YTS\TransServer;
use YTS\YTS;

Godsgood33\DotEnv::load(__DIR__.'/.env');
$cmd = getopt('h', [
    'page:', 'count:', 'install::', 'download::', 'update::', 'plex:', 'web::', 'help::', 'autostart::'
]);

if (isset($cmd['h']) || isset($cmd['help'])) {
    die(YTS::usage());
}

if (isset($cmd['page']) && is_numeric($cmd['page'])) {
    $startPage = $cmd['page'];
    $page = $cmd['page'];
} else {
    $startPage = 1;
    $page = 1;
}

if (isset($cmd['install'])) {
    YTS::install();

    print "Created database table in movies.db".PHP_EOL;
}

if (isset($cmd['update'])) {
    $page = $startPage;
    $keepGoing = true;
    $newMovie = 0;
    $existingMovie = 0;
    $hdMovies = 0;
    $fhdMovies = 0;
    $uhdMovies = 0;
    $yts = new YTS();
    $plex = new Plex();
    if (isset($cmd['plex']) && $cmd['plex'] && file_exists($cmd['plex'])) {
        $plex->setDB($cmd['plex']);
    }

    do {
        print "Loading page $page".PHP_EOL;

        $yts->load($page);
        $movies = $yts->findMovies();

        if (!count($movies)) {
            die;
        }
        
        foreach ($movies as $movie) {
            $imgLink = $movie->parent->parent->find('.img-responsive');

            $title = trim($movie->text);
            $nm = new Movie(
                $title,
                $movie->getAttribute('href'),
                $imgLink->getAttribute('src')
            );

            if ($plex->isConnected()) {
                $onPlex = $plex->check($nm);
                
                if ($onPlex) {
                    $nm->setDownload($onPlex);
                }

                if ($nm->uhdTorrent) {
                    $uhdMovies++;
                } elseif ($nm->fhdTorrent) {
                    $fhdMovies++;
                } elseif ($nm->hdTorrent) {
                    $hdMovies++;
                }
            }

            if (!$yts->isMoviePresent($nm)) {
                print "Adding {$nm}".PHP_EOL;
                $res = $yts->insertMovie($nm);
                $newMovie++;
            } else {
                print "Updating {$nm}".PHP_EOL;
                $res = $yts->updateMovie($nm);
                $existingMovie++;
            }
        }

        //sleep(5);

        $page++;
        if (isset($cmd['count']) && is_numeric($cmd['count'])) {
            if ($page >= ($startPage + $cmd['count'])) {
                $keepGoing = false;
            }
        }
    } while ($keepGoing);

    print "New movies: {$newMovie}".PHP_EOL;
    print "Existing movies: {$existingMovie}".PHP_EOL;
}

if (isset($cmd['download'])) {
    $yts = new YTS();
    $movies = $yts->getMovies();
    $ts = new TransServer(
        getenv('TRANSMISSION_DOWNLOAD_DIR'),
        getenv('TRANSMISSION_URL'),
        getenv('TRANSMISSION_PORT'),
        getenv('TRANSMISSION_USER'),
        getenv('TRANSMISSION_PASSWORD')
    );

    if (!is_countable($movies)) {
        die("No movies found");
    }

    foreach ($movies as $movie) {
        print "Checking for {$movie}".PHP_EOL;
        $res = $yts->getTorrent($movie);

        if ($res) {
            if ($movie->uhdTorrent) {
                $ts->add($movie->uhdTorrent);
                $movie->download = false;
                $movie->uhdComplete = true;
                $movie->fhdComplete = true;
                $movie->hdComplete = true;
            } elseif ($movie->fhdTorrent) {
                $ts->add($movie->fhdTorrent);
                $movie->fhdComplete = true;
                $movie->hdComplete = true;
            } elseif ($movie->hdTorrent) {
                $ts->add($movie->hdTorrent);
                $movie->hdComplete = true;
            }
        }

        if ($movie->uhdTorrent) {
            if ($tor) {
                $yts->updateMovie($movie);
            }
        }
        //sleep(1);
    }
}
