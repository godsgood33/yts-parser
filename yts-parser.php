<?php

require_once __DIR__."/vendor/autoload.php";

use jc21\PlexApi;
use jc21\Section;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use YTS\Movie;
use YTS\Plex;
use YTS\TransServer;
use YTS\YTS;
use YTS\DotEnv;
use YTS\CMD;

if (!file_exists(__DIR__.'/.env')) {
    touch(__DIR__.'/.env');
}

DotEnv::$LOAD_ENV = false;
DotEnv::$LOAD_SERVER = false;

DotEnv::load(__DIR__.'/.env');
$cmd = CMD::getCommandParameters();
$log = new Logger('db-update');
$output = "%message%";

if ($cmd->showHelp) {
    die(YTS::usage());
}

if ($cmd->log) {
    $dt = new DateTime();
    $file = new StreamHandler("db-update{$dt->format('Ymd')}.log", Level::Info);
    $file->setFormatter(new LineFormatter($output));
    $log->pushHandler($file);
}

if ($cmd->url) {
    $yts = new YTS();
    //$yts->testLoad($cmd->url);
    
    die;
}

if ($cmd->install) {
    YTS::install();

    die("Created database");
}

if ($cmd->merge) {
    YTS::mergeDatabase();
    die("Merged database");
}

if ($cmd->plexToken) {
    if (!Plex::validateEnvironment()) {
        die('Need to populate .env file with correct values');
    }

    $api = new PlexApi($_ENV['PLEX_SERVER']);
    $api->setAuth($_ENV['PLEX_USER'], $_ENV['PLEX_PASSWORD']);

    $token = $api->getToken();
    $env = file_get_contents(".env");
    $env .= PHP_EOL."PLEX_TOKEN={$token}".PHP_EOL;
    file_put_contents(".env", $env);

    print ".env file updated with PLEX_TOKEN so it doesn't have to authenticate every time".PHP_EOL;
    die;
}

if ($cmd->libraryList) {
    if (!Plex::validateEnvironment()) {
        die('Failed to validate all environment variables are present');
    }
    $api = new PlexApi($_ENV['PLEX_SERVER']);
    $api->setToken($_ENV['PLEX_TOKEN']);

    $res = $api->getLibrarySections();

    print "ID\t".str_pad("Title", 20)."Path".PHP_EOL;

    if ($res['size'] > 1) {
        foreach ($res['Directory'] as $s) {
            $section = Section::fromLibrary($s);
            print $section->key."\t".str_pad($section->title, 20).$section->location->path.PHP_EOL;
        }
    }

    $id = readline("Which library do you want to query when searching for movies? ");

    $env = file_get_contents(".env");
    $env .= "PLEX_MOVIE_LIBRARY={$id}".PHP_EOL;
    file_put_contents(".env", $env);
}

if ($cmd->update) {
    $page = $cmd->startPage;
    $keepGoing = true;
    $new = 0;
    $existing = 0;
    $skipped = 0;
    $error = 0;
    $hdMovies = 0;
    $fhdMovies = 0;
    $uhdMovies = 0;
    $yts = new YTS();
    $api = null;
    if (Plex::validateEnvironment()) {
        $api = new PlexApi($_ENV['PLEX_SERVER']);
        $api->setToken($_ENV['PLEX_TOKEN']);
    }
    $plex = new Plex($api);

    print "'-' = skipped, '+' = added, '!' = '404 error', '*' = existing";

    do {
        $log->notice(($cmd->verbose ? "Loading page $page".PHP_EOL : PHP_EOL.$page));
        print($cmd->verbose ? "Loading page $page".PHP_EOL : $page);

        $yts->load($page);
        $movies = $yts->findMovies();

        if (!count($movies)) {
            $keepGoing = false;
        }

        foreach ($movies as $movie) {
            $imgLink = $movie->parent->parent->find('.img-responsive');
            $lang = $movie->find('span');

            $title = trim($movie->text);
            if (empty($title)) {
                $skipped++;
                $log->notice(($cmd->verbose ? "Skipping empty title".PHP_EOL : "!"));
                print($cmd->verbose ? "Skipping empty title".PHP_EOL : "!");
                continue;
            }

            $nm = Movie::fromOnline(
                $title,
                $movie->getAttribute('href'),
                $imgLink->getAttribute('src')
            );

            if (count($lang)) {
                $nm->lang = (string) str_replace(['[', ']'], '', $lang->text);
            }

            $movieExists = false;

            if ($yts->isMoviePresent($nm)) {
                $nm = $yts->getMovie($nm->title, $nm->year);

                if ($nm->retrieved) {
                    $skipped++;
                    $log->notice(($cmd->verbose ? "Skipping {$nm}".PHP_EOL : "-"));
                    print($cmd->verbose ? "Skipping {$nm}".PHP_EOL : "-");
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

            $res = $yts->getTorrentLinks($nm);
            if ($res === false) {
                $error++;
                $log->notice(($cmd->verbose ? "404 Error on {$nm}".PHP_EOL : "!"));
                print($cmd->verbose ? "404 Error on {$nm}".PHP_EOL : "!");
                $yts->deleteMovie($nm->title, $nm->year);
                continue;
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
                $log->notice(($cmd->verbose ? "Updating {$nm}".PHP_EOL : "*"));
                print($cmd->verbose ? "Updating {$nm}".PHP_EOL : "*");
                $res = $yts->updateMovie($nm);
                $existing++;
            } else {
                $log->notice(($cmd->verbose ? "Adding {$nm}".PHP_EOL : "+"));
                print($cmd->verbose ? "Adding {$nm}".PHP_EOL : "+");
                $res = $yts->insertMovie($nm);
                $new++;
            }

            if (($new + $existing + $skipped + $error) % 40 == 0) {
                print($cmd->verbose ? null : PHP_EOL);
            }
        }

        $page++;
        if ($cmd->pageCount) {
            $keepGoing = !($page >= ($cmd->startPage + $cmd->pageCount));
        }
    } while ($keepGoing);

    print <<<EOF

    Summary:
    HD movies: {$hdMovies}
    FHD movies: {$fhdMovies}
    UHD movies: {$uhdMovies}
    New movies: {$new}
    Existing movies: {$existing}
    Skipped movied: {$skipped}
    Retrieval errors: {$error}
    
    EOF;
}

if ($cmd->download) {
    $yts = new YTS();
    $movies = $yts->getDownloadableMovies();
    $ts = new TransServer();

    if (!is_countable($movies) && $movies->count()) {
        die("No movies found");
    }

    foreach ($movies as $movie) {
        /** @var Movie $movie */
        print "Downloading {$movie->highestVersionAvailable()} of $movie".PHP_EOL;

        if ($movie->betterVersionAvailable()) {
            $res = $ts->checkForDownload($movie, strtolower($m->highestVersionAvailable()));

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

if ($cmd->clean) {
    $yts = new YTS();
    $count = 0;
    foreach ($yts->getMovies() as $m) {
        $count++;
        if (!$yts->checkUrl($m)) {
            if ($yts->deleteMovie($m->title, $m->year)) {
                print($cmd->verbose ? "Deleted {$m}".PHP_EOL : "-");
            } else {
                die("Failed to delete {$m}");
            }
        } else {
            print($cmd->verbose ? "{$m} is good".PHP_EOL : "*");
        }

        if ($count % 50 == 0) {
            print PHP_EOL;
        }
    }
}
