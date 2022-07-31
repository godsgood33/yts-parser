<?php

namespace YTS;

use SQLite3;
use PHPHtmlParser\Dom;
use GuzzleHttp\Exception\ConnectException;
use jc21\PlexApi;
use YTS\Movie;

/**
 * Over all method for all access
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
     * Constant to contain the number of movies to display on the index page
     *
     * @var int
     */
    public const PAGE_COUNT = 30;

    /**
     * Decide if the Transmission server is available
     *
     * @var bool
     */
    private bool $transmissionSvrConnected;

    /**
     * Array to store all movies in the database
     *
     * @var array
     */
    private array $movies;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->dom = new Dom();
        $this->db = new SQLite3(dirname(__DIR__).'/movies.db');
        $this->movies = $this->getMovies();

        $this->transmissionSvrConnected = self::ping(
            $_ENV['TRANSMISSION_URL'],
            $_ENV['TRANSMISSION_PORT']
        );
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
     *
     * @return bool
     */
    public function getTorrentLinks(Movie &$m): bool
    {
        $ret = false;
        if ($m->uhdComplete) {
            return $ret;
        }

        try {
            $this->dom->loadFromUrl($m->url);
        } catch (ConnectException $ce) {
            if ($ce->getHandlerContext()['errno'] == 7) {
                print "Connection error on page {$m->url}...retrying in 5 sec".PHP_EOL;
                sleep(5);
                $this->getTorrentLinks($m);
            } elseif ($ce->getHandlerContext()['errno'] == 28) {
                print "SSH error on page {$m->url}..retrying in 5 sec".PHP_EOL;
                sleep(5);
                $this->getTorrentLinks($m);
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
            } elseif (preg_match("/1080p\.BluRay/", $link->text)) {
                $fhdLink = $link->getAttribute('href');
            } elseif (preg_match("/720p\.BluRay/", $link->text)) {
                $hdLink = $link->getAttribute('href');
            } elseif (preg_match("/2160p\.WEB/", $link->text) && !$uhdLink) {
                $uhdLink = $link->getAttribute('href');
            } elseif (preg_match("/1080p\.WEB/", $link->text) && !$fhdLink) {
                $fhdLink = $link->getAttribute('href');
            } elseif (preg_match("/720p\.WEB/", $link->text) && !$hdLink) {
                $hdLink = $link->getAttribute('href');
            }
        }

        if ($uhdLink && $m->uhdTorrent != $uhdLink) {
            $m->uhdTorrent = $uhdLink;
        }

        if ($fhdLink && $m->fhdTorrent != $fhdLink) {
            $m->fhdTorrent = $fhdLink;
        }

        if ($hdLink && $m->hdTorrent != $hdLink) {
            $m->hdTorrent = $hdLink;
        }

        if ($m->betterVersionAvailable()) {
            $ret = true;
        }

        return $ret;
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
        return isset($this->movies[$m->title][$m->year]);
    }

    /**
     * Method to add a movie to the array
     *
     * @param Movie $m
     *
     * @return bool
     */
    public function addMovie(Movie $m): bool
    {
        if (!isset($this->movies[$m->title][$m->year])) {
            $this->movies[$m->title][$m->year] = $m;
            return true;
        }

        return false;
    }

    /**
     * Method to save a movie in the movie array
     *
     * @param Movie $m
     *
     * @return bool
     */
    public function saveMovie(Movie $m): bool
    {
        if (isset($this->movies[$m->title][$m->year])) {
            $this->movies[$m->title][$m->year] = $m;
            return true;
        }

        return false;
    }

    /**
     * Method to delete a movie from the library
     *
     * @param string $title
     * @param int $year
     *
     * @return bool
     */
    public function deleteMovie(string $title, int $year): bool
    {
        return $this->db->exec(
            "DELETE FROM `movies`
                WHERE `title`='{$this->db->escapeString($title)}' AND
                `year`={$this->db->escapeString($year)}"
        );
    }

    /**
     * Method to get the number of movies in the array
     *
     * @return int
     */
    public function getMovieCount(): int
    {
        return count($this->movies);
    }

    /**
     * Method to return an array of all movies in the database
     *
     * @return array:Movie
     */
    public function getMovies(): array
    {
        $ret = [];
        $res = $this->db->query(
            "SELECT * FROM `movies` 
            ORDER BY REPLACE(REPLACE(REPLACE(`title`, 'The ', ''), 'A ', ''), 'An ', ''), `year`"
        );
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $movie = Movie::fromDB($row);
            $ret[$movie->title][$movie->year] = $movie;
        }

        return $ret;
    }

    /**
     * Method to get a movie from the database
     *
     * @param string $title
     * @param int $year
     *
     * @return Movie|bool
     */
    public function getMovie(string $title, int $year)
    {
        if (isset($this->movies[$title][$year])) {
            return $this->movies[$title][$year];
        }

        return false;
    }

    /**
     * Method to get the movie listing from the page
     *
     * @param int $pageNo
     *
     * @return MovieCollection
     */
    public function getMoviesByPage(int $pageNo): MovieCollection
    {
        $ret = new MovieCollection();
        $PAGE_COUNT = self::PAGE_COUNT;
        $offset = $pageNo * self::PAGE_COUNT;
        $res = $this->db->query(
            "SELECT *
            FROM `movies`
            ORDER BY REPLACE(`title`, 'The ', ''),`year`
            LIMIT {$offset},{$PAGE_COUNT}"
        );

        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $movie = Movie::fromDB($row);
            $ret->addData($movie);
        }

        return $ret;
    }

    /**
     * Method to get page count
     *
     * @return int
     */
    public function getPageCount(): int
    {
        $PAGE_COUNT = self::PAGE_COUNT;
        $res = $this->db->querySingle(
            "SELECT count(1) / {$PAGE_COUNT} AS 'count'
            FROM `movies`"
        );

        return $res;
    }

    /**
     * Method to get all the movies that need to be check for new versions
     *
     * @return MovieCollection
     */
    public function getDownloadableMovies(): MovieCollection
    {
        $movies = new MovieCollection();
        $res = $this->db->query(
            "SELECT *
            FROM `movies`
            WHERE
            (`complete2160` IS NULL OR `complete2160` = '' OR `complete2160` = 0)
            AND
            `download` = 1
            AND
            LENGTH(`torrent2160`) > 0
            ORDER BY
            `title`,`year`"
        );

        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $movie = Movie::fromDB($row);
            $movies->addData($movie);
        }

        $res = $this->db->query(
            "SELECT *
            FROM `movies`
            WHERE
            (`complete1080` IS NULL OR `complete1080` = '' OR `complete1080` = 0)
            AND
            `download` = 1
            AND
            LENGTH(`torrent1080`) > 0
            ORDER BY
            `title`,`year`"
        );

        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $movie = Movie::fromDB($row);
            $movies->addData($movie);
        }

        return $movies;
    }

    /**
     * Method to find if the Transmission Server is available
     *
     * @return bool
     */
    public function isTransmissionConnected(): bool
    {
        return $this->transmissionSvrConnected;
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
     * Method to update a movie title and kick off a download
     *
     * @param string $title
     * @param int $year
     */
    public function updateDownload(string $title, int $year)
    {
        $this->db->exec(
            "UPDATE movies
            SET download = '1'
            WHERE
            `title` = '{$this->db->escapeString($title)}'
            AND
            `year` = '{$this->db->escapeString($year)}'"
        );

        $m = $this->getMovie($title, $year);
        if ($m) {
            $res = $this->getTorrentLinks($m);
            $this->updateMovie($m);
            return true;
        }
        return false;
    }

    /**
     * Method to search and auto complete movie titles
     *
     * @param string $search
     *
     * @return string
     */
    public function search(string $search): string
    {
        $PAGE_COUNT = self::PAGE_COUNT;
        $sql = "SELECT *
        FROM movies
        WHERE
        `title` LIKE '%{$this->db->escapeString($search)}%'
        OR
        `year` LIKE '%{$this->db->escapeString($search)}%'
        ORDER BY `title`,`year`
        LIMIT {$PAGE_COUNT}";
        $res = $this->db->query($sql);

        $tsConnected = $this->isTransmissionConnected();
        $ret = '';
        if (is_a($res, 'SQLite3Result') && $res->numColumns() > 1) {
            while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                $movie = Movie::fromDB($row);

                $ret .= $movie->getHtml($tsConnected);
            }
        }

        return header('Content-type: text/html').
            $ret;
    }

    /**
     * Method to find movies that have a higher resolution version available for download
     *
     * @param int $pageNo
     *
     * @return MovieCollection
     */
    public function getNewerMovies(): MovieCollection
    {
        $ret = new MovieCollection();
        $res = $this->db->query(
            "SELECT * FROM `movies`
            WHERE
            `download` = 1
            AND
            ((`torrent2160` != '' AND `complete2160` = 0)
            OR
            (`torrent1080` != '' AND `complete1080` = 0))
            ORDER BY REPLACE(REPLACE(REPLACE(`title`, 'The ', ''), 'An ', ''), 'A ', ''), `year`"
        );

        if (is_bool($res)) {
            return $ret;
        }

        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $movie = Movie::fromDB($row);
            $ret->addData($movie);
        }

        return $ret;
    }

    /**
     * Method to find duplicate movies
     *
     * @param int $pageNo
     *
     * @return MovieCollection
     */
    public function getDuplicateMovies(int $pageNo = 0): MovieCollection
    {
        $ret = new MovieCollection();
        $PAGE_COUNT = self::PAGE_COUNT;
        $offset = $pageNo * self::PAGE_COUNT;
        $res = $this->db->query(
            "SELECT * FROM `movies` 
            WHERE `title` IN (
                SELECT `title` FROM `movies`
                GROUP BY `title`
                HAVING COUNT(`title`) > 1
            )
            ORDER BY REPLACE(REPLACE(REPLACE(`title`, 'The ', ''), 'A ', ''), 'An ', ''), `year`
            LIMIT {$offset},{$PAGE_COUNT}"
        );

        if (is_bool($res)) {
            return $ret;
        }

        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $movie = Movie::fromDB($row);
            $ret->addData($movie);
        }

        return $ret;
    }

    /**
     * Method to create database tables in schema
     */
    public static function install()
    {
        if (!file_exists('movies.db')) {
            $db = new SQLite3('movies.db');
            $db->exec("CREATE TABLE IF NOT EXISTS `movies` (
                `title` varchar(255) NOT NULL,
                `year` varchar(5) NOT NULL,
                `url` varchar(255) NOT NULL,
                `imgUrl` varchar(255) DEFAULT NULL,
                `download` tinyint(1) DEFAULT '0',
                `hide` tinyint(1) DEFAULT '0',
                `torrent720` varchar(255) DEFAULT NULL,
                `complete720` tinyint(1) DEFAULT '0',
                `torrent1080` varchar(255) DEFAULT NULL,
                `complete1080` tinyint(1) DEFAULT '0',
                `torrent2160` varchar(255) DEFAULT NULL,
                `complete2160` tinyint(1) DEFAULT '0',
                PRIMARY KEY (`title`,`year`)
            )");

            $db->exec("CREATE TABLE IF NOT EXISTS `meta` (
                `field` varchar(255) NOT NULL,
                `value` mediumtext DEFAULT NULL,
                PRIMARY KEY (`field`)
            )");

            $db->exec("INSERT INTO `meta` (`field`,`value`) VALUES ('last_update',null)");
        }

        $plex = readline('Do you have a Plex server [n]? ');

        if (strtolower($plex) == 'y') {
            $plexServer = readline('Plex server IP [127.0.0.1]? ');
            $plexUser = readline('Plex account email? ');
            exec('stty -echo');
            print 'Plex account password? ';
            $plexPassword = trim(fgets(STDIN));
            exec('stty echo');
            print PHP_EOL.PHP_EOL;

            $api = new PlexApi($plexServer);
            $api->setAuth($plexUser, $plexPassword);
            $plexToken = $api->getToken();

            $plex = <<<EOF
            PLEX_SERVER={$plexServer}
            PLEX_TOKEN={$plexToken}
            EOF;
        } else {
            $plex = null;
        }

        $transmission = readline('Do you have a Transmission server [n]? ');

        if (strtolower($transmission) == 'y') {
            $transServer = readline('Transmission server IP [127.0.0.1]? ');
            $transServerPort = readline('Transmission server port [9091]? ');
            $transServerUser = readline('Transmission server user? ');
            system('stty -echo');
            print 'Transmission server password? ';
            $transServerPassword = trim(fgets(STDIN));
            system('stty echo');
            print PHP_EOL;
            $transServerDownloadDir = readline('Transmission server download directory [~/Downloads]? ');
            
            $transmission = <<<EOF
            TRANSMISSION_URL={$transServer}
            TRANSMISSION_PORT={$transServerPort}
            TRANSMISSION_USER={$transServerUser}
            TRANSMISSION_PASSWORD={$transServerPassword}
            TRANSMISSION_DOWNLOAD_DIR={$transServerDownloadDir}
            EOF;
        } else {
            $transmission = null;
        }

        $env = <<<EOF
        $plex

        $transmission

        EOF;

        file_put_contents('.env', $env);
    }

    /**
     * Function to ping a host and respond boolean
     *
     * @param string $strHost
     * @param integer $intPort [optional]
     * @param integer $intTimeout [optional]
     *
     * @return boolean
     */
    public static function ping(string $strHost, int $intPort = 80, int $intTimeout = 5)
    {
        $errno = null;
        $errstr = null;
        $fsock = @fsockopen($strHost, $intPort, $errno, $errstr, $intTimeout);
        if (is_resource($fsock)) {
            return true;
        }
        return false;
    }

    /**
     * Method to output the usage page
     */
    public static function usage()
    {
        print <<<EOF
This script is used to scrape yts.mx website for all movies.  You can then set a flag to have it retrieve the torrent
links and download them with a Transmission server.

--install               Flag to call first to create the required tables
--update                Flag to start the scraping
--highestVersion        Flag to scrap each movie for the torrent links and get the highest quality version available
--download              Flag to start the download process
--torrentLinks          Flag to retrieve the torrent links from each title page
--page={number}         What page do you want to start on
--count={number}        How many pages do you want to read
--plexToken             Flag to return the Plex authentication token
-h | --help             This page


EOF;
    }
}
