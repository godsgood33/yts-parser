<?php

namespace YTS;

use PHPHtmlParser\Dom;
use GuzzleHttp\Exception\ConnectException;
use SQLite3;
use YTS\Movie;

/**
 *
 */
class YTS
{
    /**
     * Database connection
     *
     * @var SQLite3
     */
    private SQLite3 $db;

    /**
     * Base page
     *
     * @var string
     */
    private const BASE_PAGE = 'https://yts.mx/browse-movies?page=';

    /**
     * Variable to store the dom
     *
     * @var Dom
     */
    private Dom $dom;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->dom = new Dom();
        $this->db = new SQLite3('movies.db');
    }

    /**
     * Method to load a url
     *
     * @param int $pageNo
     */
    public function load(int $pageNo)
    {
        try {
            $this->dom->loadFromUrl(self::BASE_PAGE.$pageNo);
        } catch (ConnectException $ce) {
            if ($ce->getHandlerContext()['errno'] == 7) {
                print "Connection error on page {$pageNo}...retrying in 5 sec".PHP_EOL;
                sleep(5);
                $this->load($pageNo);
            } elseif ($ce->getHandlerContext()['errno'] == 28) {
                print "SSH error on page {$pageNo}...retrying in 5 sec".PHP_EOL;
                sleep(5);
                $this->load($pageNo);
            } else {
                die(print_r($ce, true));
            }
        }
    }

    /**
     * Method to retrieve the torrent path
     *
     * @param Movie $m
     */
    public function getTorrent(Movie &$m)
    {
        if ($m->uhdComplete) {
            return false;
        } elseif ($m->fhdComplete && !$m->uhdComplete) {
            return false;
        } elseif ($m->hdComplete && !$m->fhdComplete && !$m->uhdComplete) {
            return false;
        }

        try {
            $this->dom->loadFromUrl($m->url);
        } catch (ConnectException $ce) {
            if ($ce->getHandlerContext()['errno'] == 7) {
                print "Connection error on page {$m->url}...retrying in 5 sec".PHP_EOL;
                sleep(5);
                $this->getTorrent($m);
            } elseif ($ce->getHandlerContext()['errno'] == 28) {
                print "SSH error on page {$m->url}..retrying in 5 sec".PHP_EOL;
                sleep(5);
                $this->getTorrent($m);
            } else {
                die(print_r($ce, true));
            }
        }

        $torrentLinks = $this->dom->find('a');

        $uhdLink = null;
        $fhdLink = null;
        $hdLink = null;
    
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

        return true;
    }

    /**
     * Method to retrieve the movie listing
     *
     * @return mixed|Collection|null
     */
    public function findMovies()
    {
        $movies = $this->dom->find('.browse-movie-title');
        if (!count($movies)) {
            print "Found the end".PHP_EOL;
            return [];
        }

        return $movies;
    }

    /**
     * Method to see if a movie is present in the database already
     *
     * @param Movie $m
     *
     * @return bool
     */
    public function isMoviePresent(Movie $m): bool
    {
        $res = $this->getMovie($m);

        if (is_array($res)) {
            return isset($res['title']);
        }
        return false;
    }

    /**
     * Method to get a movie from the database
     *
     * @param Movie $m
     *
     * @return array|bool
     */
    public function getMovie(Movie $m)
    {
        $res = $this->db->query(
            "SELECT *
            FROM `movies`
            WHERE
            `title` = '{$this->db->escapeString($m->title)}'
            AND
            `year` = '{$this->db->escapeString($m->year)}'"
        );

        return $res->fetchArray(SQLITE3_ASSOC);
    }

    /**
     * Method to get all the movies that need to be check for new versions
     *
     * @return array:Movie
     */
    public function getMovies(): array
    {
        $movies = [];
        $res = $this->db->query(
            "SELECT *
            FROM `movies`
            WHERE
            `download` = '1'
            AND
            `complete2160` = '0'
            ORDER BY `title`,`year`"
        );

        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $movie = Movie::fromDB($row);
            $movies[] = $movie;
        }

        return $movies;
    }

    /**
     * Method to insert a movie into the database
     *
     * @param Movie $m
     *
     * @return bool
     */
    public function insertMovie(Movie $m): bool
    {
        $ins = $m->insert();

        $res = $this->db->exec(
            "INSERT INTO `movies` (`".
            implode("`,`", array_keys($ins))."`) VALUES ('".
            implode("','", array_map([SQLite3::class, 'escapeString'], array_values($ins)))."')"
        );

        return $res;
    }

    /**
     * Method to update a movie in the database
     *
     * @param Movie $m
     *
     * @return bool
     */
    public function updateMovie(Movie $m): bool
    {
        $res = $this->db->exec(
            "UPDATE `movies` 
            SET {$m->update()} 
            WHERE 
            `title` = '{$this->db->escapeString($m->title)}' 
            AND 
            `year` = '{$this->db->escapeString($m->year)}'"
        );

        return $res;
    }

    /**
     * Method to create database tables in schema
     */
    public static function install()
    {
        $db = new SQLite3('movies.db');
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
    }

    /**
     * Method to output the usage page
     */
    public static function usage()
    {
        print <<<EOF
        This script is used to scrape yts.mx website for all movies.  You can then set a flag to have it retrieve the torrent links and download them with a Transmission server.
        
            --install               Flag to call first to create the required tables
            --update                Flag to start the scraping
            --download              Flag to start the download process
            --page={number}         What page do you want to start on
            --count={number}        How many pages do you want to read
            --plex={Plex library}   Flag to point to a Plex library
            -h | --help             This page
        
        EOF;
    }
}
