<?php

require_once __DIR__."/vendor/autoload.php";
require_once __DIR__."/Movie.php";
require_once __DIR__."/DotEnv.php";

use PHPHtmlParser\Dom;
use GuzzleHttp\Exception\ConnectException;
use Transmission\Client as TransmissionClient;
use Transmission\Transmission;

Godsgood33\DotEnv::load(__DIR__.'/.env');
$cmd = getopt('h', ['install::', 'download::', 'update::', 'plex:', 'web::', 'help::']);

if (isset($cmd['h']) || isset($cmd['help'])) {
    die(usage());
}

$page = 3;
$html = "https://yts.mx/browse-movies?page=";
$dom = new Dom();
$db = new SQLite3('movies.db');

if (isset($cmd['install'])) {
    $db->exec("CREATE TABLE `movies` (
        `title` varchar(255) NOT NULL,
        `year` varchar(5) NOT NULL,
        `url` varchar(255) NOT NULL,
        `imgUrl` varchar(255) DEFAULT NULL,
        `download` tinyint(1) DEFAULT '0',
        `torrent720` varchar(255) DEFAULT NULL,
        `complete720` tinyint(1) DEFAULT '0',
        `torrent1080` varchar(255) DEFAULT NULL,
        `complete1080` tinyint(1) DEFAULT '0',
        `torrent2160` varchar(255) DEFAULT NULL,
        `complete2160` tinyint(1) DEFAULT '0',
        PRIMARY KEY (`title`,`year`)
    )");

    print "Created database table in movies.db".PHP_EOL;
}

if (isset($cmd['update'])) {
    do {
        print "Loading page $page".PHP_EOL;

        try {
            $dom->loadFromUrl($html.$page);
        } catch (ConnectException $ce) {
            if ($ce->curl_errno == 7) {
                continue;
            }
        }
        $movies = $dom->find('.browse-movie-title');

        foreach ($movies as $movie) {
            $imgLink = $movie->parent->parent->find('.img-responsive');

            $title = trim($movie->text);
            $nm = new Movie(
                $title,
                $movie->getAttribute('href'),
                $imgLink->getAttribute('src')
            );

            if (isset($cmd['plex']) && $cmd['plex'] && file_exists($cmd['plex'])) {
                $onPlex = checkPlex($cmd['plex'], $nm);
                
                if ($onPlex) {
                    $nm->setDownload($onPlex);
                }
            }
            
            $res = $db->query(
                "SELECT *
                FROM `movies`
                WHERE
                `title` = '{$db->escapeString($nm->title)}'
                AND
                `year` = '{$db->escapeString($nm->year)}'"
            );

            if (!$res->fetchArray()) {
                print "Adding {$nm}".PHP_EOL;
                $ins = $nm->insert();

                /**/
                $res = $db->exec(
                    "INSERT INTO `movies` (`".
                    implode("`,`", array_keys($ins))."`) VALUES ('".
                    implode("','", array_map([SQLite3::class, 'escapeString'], array_values($ins)))."')"
                );
            } else {
                $res = $db->exec(
                    "UPDATE `movies` 
                    SET {$nm->update()} 
                    WHERE 
                    `title` = '{$db->escapeString($nm->title)}' 
                    AND 
                    `year` = '{$db->escapeString($nm->year)}'"
                );
            }
        }

        sleep(5);

        $page++;
    } while ($page < 10);
}

if (isset($cmd['download'])) {
    $movies = $db->query(
        "SELECT * 
        FROM `movies` 
        WHERE 
        `download` = '1' 
        AND 
        `complete2160` = '0'"
    );

    if (is_array($movies)) {
        foreach ($movies as $movie) {
            $om = Movie::fromDB($movie);
            $res = getTorrent($om);
            if ($res) {
                $db->update('movies', $om, $om);
            }
            sleep(1);
        }
    } else {
        $om = Movie::fromDB($movies);
        $res = getTorrent($om);
        if ($res) {
            $db->update('movies', $om, $om);
        }
    }
}

/**
 * Method to retrieve torrent links from YTS.mx movie page
 *
 * @param Movie $m
 *
 * @return int
 */
function getTorrent(Movie &$m)
{
    if ($m->uhdComplete) {
        return 0;
    } elseif ($m->fhdComplete && !$m->uhdComplete) {
        return 0;
    } elseif ($m->hdComplete && !$m->fhdComplete && !$m->hdComplete) {
        return 0;
    }
    $dom = $GLOBALS['dom'];
    $client = new TransmissionClient(getenv('TRANSMISSION_URL'), getenv('TRANSMISSION_PORT'));
    $client->authenticate(getenv('TRANSMISSION_USER'), getenv('TRANSMISSION_PASSWORD'));
    $rpc = new Transmission(getenv('TRANSMISSION_URL'), getenv('TRANSMISSION_PORT'));
    $rpc->setClient($client);

    $dom->loadFromUrl($m->url);
    $torrentLinks = $dom->find('a');

    $uhdLink = null;
    $fhdLink = null;
    $hdLink = null;
    $download = 0;

    foreach ($torrentLinks as $link) {
        if (preg_match("/2160p\.BluRay/", $link->text)) {
            $uhdLink = $link->getAttribute('href');
            $m->uhdTorrent = $uhdLink;
        } elseif (preg_match("/1080p\.BluRay/", $link->text)) {
            $fhdLink = $link->getAttribute('href');
            $m->fhdTorrent = $fhdLink;
        } elseif (preg_match("/720p\.BluRay/", $link->text)) {
            $hdLink = $link->getAttribute('href');
            $m->hdTorrent = $hdLink;
        } elseif (preg_match("/2160p\.WEB/", $link->text) && !$uhdLink) {
            $uhdLink = $link->getAttribute('href');
            $m->uhdTorrent = $uhdLink;
        } elseif (preg_match("/1080p\.WEB/", $link->text) && !$fhdLink) {
            $fhdLink = $link->getAttribute('href');
            $m->fhdTorrent = $fhdLink;
        } elseif (preg_match("/720p\.WEB/", $link->text) && !$hdLink) {
            $hdLink = $link->getAttribute('href');
            $m->hdTorrent = $hdLink;
        }
    }

    try {
        if ($uhdLink) {
            print "Downloading 4K {$m}".PHP_EOL;
            $tor = $rpc->add($uhdLink, false, getenv('TRANSMISSION_DOWNLOAD_DIR'));
            $download = 3;
            $m->download = false;
        } elseif ($fhdLink) {
            print "Downloading 1080p {$m}".PHP_EOL;
            $tor = $rpc->add($fhdLink, false, getenv('TRANSMISSION_DOWNLOAD_DIR'));
            $download = 2;
        } elseif ($hdLink) {
            print "Downloading 720p {$m}".PHP_EOL;
            $tor = $rpc->add($hdLink, false, getenv('TRANSMISSION_DOWNLOAD_DIR'));
            $download = 1;
        }
    } catch (RuntimeException $e) {
        print "Failed to add {$m}".PHP_EOL;
    }

    switch ($download) {
        case 3:
            $m->uhdComplete = true;
            // no break
        case 2:
            $m->fhdComplete = true;
            // no break
        case 1:
            $m->hdComplete = true;
        // no default
    }

    return $download;
}

/**
 * Method to check is a movie is in Plex library
 *
 * @param string $plexLibrary
 * @param Movie $m
 *
 * @return array|false
 */
function checkPlex(string $plexLibrary, Movie &$m)
{
    $plex = new SQLite3($plexLibrary);
    $plex->enableExceptions(true);
    $res = $plex->query(
        "SELECT mdi.`id`,mdi.`title`,mi.`height`,mi.`width`
        FROM `media_items` mi
        JOIN `metadata_items` mdi ON mdi.id = mi.metadata_item_id 
        WHERE 
        LOWER(mdi.`title`) = LOWER('{$plex->escapeString($m->title)}')
        AND 
        mdi.`metadata_type` = 1"
    );

    $row = $res->fetchArray(SQLITE3_ASSOC);

    $plex->close();

    return $row;
}

/**
 * Usage method
 */
function usage()
{
    print <<<EOF
This script is used to scrape yts.mx website for all movies.  You can then set a flag to have it retrieve the torrent links and download them with a Transmission server.

    --install               Flag to call first to create the required tables
    --update                Flag to start the scraping
    --download              Flag to start the download process
    --web                   Generate a user friendly web page
    --plex={Plex library}   Flag to point to a Plex library
    -h | --help             This page

EOF;
}
