<?php

require_once __DIR__."/vendor/autoload.php";

use YTS\Movie;
use YTS\Plex;
use YTS\TransServer;
use YTS\YTS;
use YTS\DotEnv;

DotEnv::load(__DIR__.'/.env');
$cmd = YTS::getCommandParameters();

if ($cmd->showHelp) {
    die(YTS::usage());
}

$startPage = 1;
$page = 1;
if ($cmd->startPage) {
    $startPage = $cmd->startPage;
    $page = $cmd->startPage;
}

if ($cmd->install) {
    YTS::install();

    die("Created database table in movies.db");
}

if ($cmd->update) {
    $page = $startPage;
    $keepGoing = true;
    $newMovie = 0;
    $existingMovie = 0;
    $hdMovies = 0;
    $fhdMovies = 0;
    $uhdMovies = 0;
    $yts = new YTS();
    $plex = new Plex();
    if ($cmd->plexDB) {
        $plex->setDB($cmd->plexDB);
    }

    do {
        print "Loading page $page".PHP_EOL;

        $yts->load($page);
        $movies = $yts->findMovies();

        if (!count($movies)) {
            $keepGoing = false;
        }
        
        foreach ($movies as $movie) {
            $imgLink = $movie->parent->parent->find('.img-responsive');

            $title = trim($movie->text);
            $nm = new Movie(
                $title,
                $movie->getAttribute('href'),
                $imgLink->getAttribute('src')
            );

            $movieExists = false;

            if ($yts->isMoviePresent($nm)) {
                $nm = $yts->getMovie($nm->title, $nm->year);

                if ($nm->retrieved) {
                    continue;
                }

                $nm->url = $movie->getAttribute('href');
                $nm->imgUrl = $imgLink->getAttribute('src');
                $nm->retrieved = true;
                $yts->saveMovie($nm);

                $movieExists = true;
            } else {
                $nm->retrieved = true;
                $yts->addMovie($nm);
            }
            
            if ($cmd->torrentLinks) {
                $yts->getTorrentLinks($nm);
            }

            if ($plex->isConnected()) {
                $onPlex = $plex->check($nm);
                
                if ($onPlex) {
                    $nm->setDownload($onPlex);
                }

                $nm->uhdTorrent ? $uhdMovies++ : null;
                $nm->fhdTorrent ? $fhdMovies++ : null;
                $nm->hdTorrent ? $hdMovies++ : null;
            }

            if ($movieExists) {
                print "Updating {$nm}".PHP_EOL;
                $res = $yts->updateMovie($nm);
                $existingMovie++;
            } else {
                print "Adding {$nm}".PHP_EOL;
                $res = $yts->insertMovie($nm);
                $newMovie++;
            }
        }

        $page++;
        if ($cmd->pageCount) {
            $keepGoing = !($page >= ($startPage + $cmd->pageCount));
        }
    } while ($keepGoing);

    print "New movies: {$newMovie}".PHP_EOL;
    print "Existing movies: {$existingMovie}".PHP_EOL;
}

if ($cmd->download) {
    $yts = new YTS();
    $movies = $yts->getDownloadableMovies();
    $ts = new TransServer();

    if (!is_countable($movies) && count($movies)) {
        die("No movies found");
    }

    foreach ($movies as $movie) {
        /** @var Movie $movie */
        print "Downloading {$movie->highestVersionAvailable()} of $movie".PHP_EOL;

        if ($movie->betterVersionAvailable()) {
            $res = $ts->checkForDownload($movie);

            if (is_a($res, 'Transmission\Model\Torrent')) {
                $yts->updateMovie($movie);
            }
        }
    }
}

if ($cmd->highestVersion) {
    $yts = new YTS();
    $movies = $yts->getMovies();
    print "Updating resolutions".PHP_EOL;
    print '#';
    $alpha = [
        '','#','A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'
    ];
    $currentIndex = 0;
    $idx = 0;

    foreach ($movies as $movie) {
        if ($movie->hdTorrent && $movie->fhdTorrent && $movie->uhdTorrent) {
            continue;
        }

        if ($movie->uhdComplete) {
            continue;
        }

        if (substr($movie->title, 0, 1) == $alpha[$currentIndex]) {
            print $alpha[$currentIndex];
            $currentIndex++;
        } else {
            print ".";
        }

        if ($idx % 100 == 0) {
            print PHP_EOL;
        }

        $yts->getTorrentLinks($movie);
        $yts->updateMovie($movie);
        $idx++;
    }
}
