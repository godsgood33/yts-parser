<?php

namespace YTS;

use DateTime;
use Exception;
use mysqli;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use jc21\PlexApi;
use jc21\Section;
use PHPHtmlParser\Dom;
use YTS\Movie;

/**
 * Over all method for all access
 */
class YTS
{
    /**
     * Database connection
     *
     * @var mysqli
     */
    private mysqli $db;

    /**
     * Base page
     *
     * @var string
     */
    private const BASE_PAGE = 'https://yts.mx/browse-movies?page=';

    /**
     * HTTP Client
     *
     * @var GuzzleHttp\Client
     */
    private Client $client;

    /**
     * Dom
     *
     * @var PhpHtmlParser\Dom
     */
    private Dom $dom;

    /**
     * Constant to contain the number of movies to display on each page
     *
     * @var int
     */
    //public const PAGE_COUNT = $_ENV['MOVIE_COUNT'];

    /**
     * Decide if the Transmission server is available
     *
     * @var bool
     */
    private bool $transmissionSvrConnected;

    /**
     * Array to store all movies in the database
     *
     * @var MovieCollection
     */
    private MovieCollection $movies;

    /**
     * Constructor
     */
    public function __construct()
    {
        /*$dbFile = dirname(__DIR__).'/my-movies.db';
        if (!file_exists($dbFile)) {
            die("Cannot find movie database my-movies.db");
        } elseif (!is_readable($dbFile)) {
            die("Cannot read movie database");
        } elseif (!is_writeable($dbFile)) {
            die("Cannot write to movie database");
        }*/

        $this->dom = new Dom();
        $this->client = new Client();
        try {
            $host = $_ENV['DATABASE_HOST'];
            $user = $_ENV['DATABASE_USER'];
            $pwd = $_ENV['DATABASE_PASSWORD'];
            $name = $_ENV['DATABASE_NAME'];

            $this->db = new mysqli($host, $user, $pwd, $name);
        } catch (Exception $e) {
            if ($this->db->connect_errno) {
                print($this->db->connect_error);
            }
            die;
        }
        $this->movies = new MovieCollection();
        $this->populateMovies();

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
            $this->response = $this->client->request('GET', self::BASE_PAGE.$pageNo, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; Win64; rv:89.0) Gecko/20100101 Firefox/89.0'
                ]
            ]);
            $this->dom->loadStr($this->response->getBody());
        } catch (ConnectException $ce) {
            switch ($ce->getHandlerContext()['errno']) {
                case 7:
                    print "Connection error on page {$pageNo}...retrying in 5 sec".PHP_EOL;
                    sleep(5);
                    $this->load($pageNo);
                    break;
                case 28:
                    print "SSH error on page {$pageNo}...retrying in 5 sec".PHP_EOL;
                    sleep(5);
                    $this->load($pageNo);
                    break;
                case 504:
                    print "504 error";
                    // no break
                default:
                    die(print_r($ce, true));
            }
        }
    }

    /**
     * Method to check a specific url
     *
     * @param string $url
     *
     * @return Movie
     */
    public function loadUrl(string $url): Movie
    {
        try {
            $this->response = $this->client->request('GET', $url, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; Win64; rv:89.0) Gecko/20100101 Firefox/89.0'
                ]
            ]);
            $this->dom->loadStr($this->response->getBody());
        } catch (ConnectException $ce) {
            switch ($ce->getHandlerContext()['errno']) {
                case 7:
                    print "Connection error on page {$url}...retrying in 5 sec".PHP_EOL;
                    sleep(5);
                    $this->loadUrl($url);
                    break;
                case 28:
                    print "SSH error on page {$url}...retrying in 5 sec".PHP_EOL;
                    sleep(5);
                    $this->loadUrl($url);
                    break;
                case 504:
                    print "504 error";
                    // no break
                default:
                    die(print_r($ce, true));
            }
        }

        $img = $this->dom->find('#movie-poster img.img-responsive');
        $imgUrl = $img->src;
        $title = $this->dom->find('#movie-info .hidden-xs h1')->text;
        $year = $this->dom->find('#movie-info .hidden-xs h2')[0]->text;

        $movie = new Movie($title, $year);
        $movie->url = $url;
        $movie->imgUrl = $imgUrl;

        $this->getTorrentLinks($movie);

        return $movie;
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
            $this->response = $this->client->request('GET', $m->url);
            $this->dom->loadStr($this->response->getBody());
        } catch (RequestException $e) {
            if ($e->getResponse()->getStatusCode() == 404) {
                return $ret;
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
     * Method to check if a page is a valid movie
     *
     * @param Movie $m
     *
     * @return bool
     */
    public function checkUrl(Movie &$m): bool
    {
        try {
            $this->response = $this->client->request('GET', $m->url);
        } catch (RequestException $e) {
            if ($e->getResponse()->getStatusCode() == 404) {
                return false;
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
        return $this->movies->movieExists($m);
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
        if (!$this->movies->movieExists($m)) {
            return $this->movies->addData($m);
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
        if (!$this->movies->movieExists($m)) {
            return $this->movies->addData($m);
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
        $m = $this->movies->getMovieByTitle($title, $year);
        if (!is_a($m, 'YTS\Movie')) {
            print json_encode([
                'error' => 'failed to retrieve movie'
            ]);
            return false;
        }

        //$this->movies->removeMovie($m);

        $query = "DELETE FROM movies
            WHERE
                title='{$this->db->real_escape_string($m->title)}' AND
                year={$this->db->real_escape_string($m->year)}";

        try {
            return $this->db->real_query($query);
        } catch (Exception $e) {
            die(print_r($e, true));
        }
    }

    /**
     * Method to get the number of movies in the array
     *
     * @return int
     */
    public function getMovieCount(): int
    {
        return $this->movies->count();
    }

    /**
     * Method to populate the movie collection
     */
    private function populateMovies()
    {
        $query = "SELECT * FROM `movies`
            ORDER BY REPLACE(
                REPLACE(
                    REPLACE(
                        `title`, 'The ', ''
                    ), 'A ', ''
                ), 'An ', ''
            ), `year`";

        $res = $this->db->query($query);

        while ($row = $res->fetch_assoc()) {
            $m = Movie::fromDB($row);
            $this->movies->addData($m);
        }
    }

    /**
     * Method to return an array of all movies in the database
     *
     * @return MovieCollection
     */
    public function getMovies(): MovieCollection
    {
        return $this->movies;
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
        return $this->movies->getMovieByTitle($title, $year);
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
        $offset = ($pageNo - 1) * $_ENV['MOVIE_COUNT'];

        $mi = new MovieIterator($this->movies);
        $mi->setPosition($offset);
        for ($x = 0; $x < $_ENV['MOVIE_COUNT']; $x++) {
            $position = $offset + $x;
            $ret->addData($this->movies->getData($position));
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
        $res = $this->db->query(
            "SELECT count(1) / {$_ENV['MOVIE_COUNT']} AS 'count'
            FROM `movies`"
        );

        return $res->fetch_assoc()['count'];
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

        while ($row = $res->fetch_assoc()) {
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

        while ($row = $res->fetch_assoc()) {
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

        foreach ($ins as $k => $v) {
            if (is_null($v)) {
                $ins[$k] = 'NULL';
            } else {
                $ins[$k] = $this->db->real_escape_string($v);
            }
        }

        $res = $this->db->real_query(
            "INSERT INTO `movies` (`".
            implode("`,`", array_keys($ins))."`) VALUES ('".
            implode("','", array_values($ins))."')"
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
        $res = $this->db->real_query(
            "UPDATE `movies`
            SET {$m->update()}
            WHERE
            `title` = '{$this->db->real_escape_string($m->title)}'
            AND
            `year` = '{$this->db->real_escape_string($m->year)}'"
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
        $this->db->real_query(
            "UPDATE movies
            SET download = '1'
            WHERE
            `title` = '{$this->db->real_escape_string($title)}'
            AND
            `year` = '{$this->db->real_escape_string($year)}'"
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
        $sql = "SELECT *
        FROM movies
        WHERE
        `title` LIKE '%{$this->db->real_escape_string($search)}%'
        OR
        `year` LIKE '%{$this->db->real_escape_string($search)}%'
        ORDER BY `title`,`year`
        LIMIT {$_ENV['MOVIE_COUNT']}";
        $res = $this->db->query($sql);

        $tsConnected = $this->isTransmissionConnected();
        $ret = '';
        if (is_a($res, 'mysqli_result') && $res->num_rows > 1) {
            while ($row = $res->fetch_assoc()) {
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
            ORDER BY REPLACE(REPLACE(REPLACE(REPLACE(`title`, 'The ', ''), 'An ', ''), 'A ', ''), ' ', '') `year`"
        );

        if (is_bool($res)) {
            return $ret;
        }

        while ($row = $res->fetch_assoc()) {
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
        $offset = $pageNo * $_ENV['MOVIE_COUNT'];
        $res = $this->db->query(
            "SELECT * FROM `movies`
            WHERE `title` IN (
                SELECT `title` FROM `movies`
                GROUP BY `imgUrl`
                HAVING COUNT(`imgUrl`) > 1
            )
            ORDER BY REPLACE(REPLACE(REPLACE(REPLACE(`title`, 'The ', ''), 'A ', ''), 'An ', ''), ' ', ''), `year`
            LIMIT {$offset},{$_ENV['MOVIE_COUNT']}"
        );

        if (is_bool($res)) {
            return $ret;
        }

        while ($row = $res->fetch_assoc()) {
            $movie = Movie::fromDB($row);
            $ret->addData($movie);
        }

        return $ret;
    }

    /**
     * Get movies marked for download
     *
     * @param int $pageNo
     * @param bool $inc4k
     *
     * @return MovieCollection
     */
    public function getDownloaded(int $pageNo = 0, bool $exc4k = false): MovieCollection
    {
        $ret = new MovieCollection();
        $offset = ($pageNo - 1) * $_ENV['MOVIE_COUNT'];
        $remove4k = null;
        if ($exc4k) {
            $remove4k = " AND `complete2160` = 0";
        }
        $res = $this->db->query(
            "SELECT * FROM `movies`
            WHERE download=1{$remove4k}
            ORDER BY REPLACE(REPLACE(REPLACE(REPLACE(`title`, 'The ', ''), 'An ', ''), 'A ', ''), ' ', ''), `year`
            LIMIT {$offset},{$_ENV['MOVIE_COUNT']}"
        );

        if (is_bool($res)) {
            return $ret;
        }

        while ($row = $res->fetch_assoc()) {
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
        (file_exists('movies.db') ? self::checkDatabase() : self::createDatabase());

        $plex = readline('Do you have a Plex server [n]? ');

        if (strtolower($plex) == 'y') {
            $plexServer = readline('Plex server IP [127.0.0.1]? ');
            $plexUser = readline('Plex account email? ');
            exec('stty -echo');
            print 'Plex account password? ';
            $plexPassword = trim(fgets(STDIN));
            exec('stty echo');
            print PHP_EOL.PHP_EOL;

            if (empty($plexServer)) {
                $plexServer = '127.0.0.1';
            }

            if (empty($plexUser) || empty($plexPassword)) {
                die('You must specific a Plex.tv email and password');
            }

            $api = new PlexApi($plexServer);
            $api->setAuth($plexUser, $plexPassword);
            $plexToken = $api->getToken();

            $plexLibrary = self::getLibraries($plexServer, $plexToken, true);

            $plex = <<<EOF
            PLEX_SERVER={$plexServer}
            PLEX_TOKEN={$plexToken}
            PLEX_MOVIE_LIBRARY={$plexLibrary}
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

            if (empty($transServer)) {
                $transServer = '127.0.0.1';
            }
            if (empty($transServerPort)) {
                $transServerPort = 9091;
            }
            if (empty($transServerDownloadDir)) {
                $transServerDownloadDir = '~/Downloads';
            }

            if (empty($transServerUser) || empty($transServerPassword)) {
                die('You must specify a username and password to connect to a Transmission server');
            }

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
     * Method to get all the Plex libraries and find the one for the movies
     *
     * @param string $plexServer
     * @param string $plexToken
     *
     * @return int
     */
    public static function getLibraries(string $plexServer, string $plexToken, bool $install = false)
    {
        if (!$install && !Plex::validateEnvironment()) {
            die('Failed to validate all environment variables are present');
        }
        $api = new PlexApi($plexServer);
        $api->setToken($plexToken);

        $res = $api->getLibrarySections();

        print "ID\t".str_pad("Title", 20)."Path".PHP_EOL;
        $libraries = [];

        if ($res['size'] > 1) {
            foreach ($res['Directory'] as $s) {
                $libraries[] = $s->key;

                $section = Section::fromLibrary($s);
                print $section->key."\t".str_pad($section->title, 20).$section->location->path.PHP_EOL;
            }
        }

        $id = readline("Which library do you want to query when searching for movies? ");

        if (!in_array($id, $libraries, true)) {
            die('You must select a number from the library list above');
        }

        return $id;
    }

    /**
     * Method to create a database
     */
    public static function createDatabase()
    {
        $db = new mysqli(
            $_ENV['DATABASE_HOST'],
            $_ENV['DATABASE_USER'],
            $_ENV['DATABASE_PASSWORD'],
            $_ENV['DATABASE_NAME'],
            $_ENV['DATABASE_PORT']
        );
        $db->real_query("CREATE TABLE IF NOT EXISTS `movies` (
            `hash` varchar(255) NOT NULL,
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
            PRIMARY KEY (`hash`)
        )");

        $db->real_query("CREATE TABLE IF NOT EXISTS `meta` (
            `field` varchar(255) NOT NULL,
            `value` mediumtext DEFAULT NULL,
            PRIMARY KEY (`field`)
        )");

        $dt = new DateTime();
        $db->real_query("INSERT INTO `meta` (`field`,`value`) VALUES ('db_version','{$dt->format('Y-m-d')}')");
    }

    /**
     * Method to copy a baseline database
     */
    public static function checkDatabase()
    {
        if (!file_exists('my-movies.db')) {
            rename('movies.db', 'my-movies.db');
            return;
        }

        $db = new mysqli(
            $_ENV['DATABASE_HOST'],
            $_ENV['DATABASE_USER'],
            $_ENV['DATABASE_PASSWORD'],
            $_ENV['DATABASE_NAME'],
            $_ENV['DATABASE_PORT']
        );
        $res = $db->query("SELECT `value` FROM `meta` WHERE `field`='db_version'");
        $myVer = $res->fetch_column();
        $myDate = new DateTime($myVer);

        $db = new mysqli(
            $_ENV['DATABASE_HOST'],
            $_ENV['DATABASE_USER'],
            $_ENV['DATABASE_PASSWORD'],
            $_ENV['DATABASE_NAME'],
            $_ENV['DATABASE_PORT']
        );
        $res = $db->query("SELECT `value` FROM `meta` WHERE `field`='db_version'");
        $ver = $res->fetch_column();
        $date = new DateTime($ver);

        if ($date <= $myDate) {
            return;
        }

        $merge = readline("Would you like to merge changes from latest movies.db [n]? ");
        $merge = (strtolower($merge) == 'y' ? true : false);

        if ($merge) {
            self::mergeDatabase();
        }
    }

    /**
     * Method to merge database
     */
    public static function mergeDatabase()
    {
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
        --merge                 Merge databases
        --verbose               Verbose output
        -h | --help             This page


        EOF;
    }
}
