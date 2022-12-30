<?php

namespace YTS;

/**
 * Class to store command params
 *
 * @property bool $showHelp
 *      Show the help usage page
 * @property bool $install
 *      Install the project
 * @property bool $update
 *      Update torrent database
 * @property bool $download
 *      Download updated torrents
 * @property bool $highestVersion
 *      Retrieve the highest versions from the site
 * @property bool $torrentLinks
 *      Retrieve the torrent links when updating torrent database
 * @property bool $plexToken
 *      Retrieve the plexToken, used to connect to Plex database without credentials
 * @property bool $libraryList
 *      List all Plex libraries
 * @property bool $merge
 *      Merge current personal torrent database with new database downloaded from github
 * @property bool $verbose
 *      Verbose output when updating and downloading
 * @property int $startPage
 *      The page to start on
 * @property int $pageCount
 *      The number of pages to retrieve
 * @property string $url
 *      A specific url to look at
 */
class CMD
{
    /**
     * Variable to show the help page
     *
     * @var bool
     */
    private bool $showHelp;

    /**
     * Variable to decide if you are wanting to install the database
     *
     * @var bool
     */
    private bool $install;

    /**
     * Variable to decide if you want to update the database
     *
     * @var bool
     */
    private bool $update;

    /**
     * Variable to decide if you want to download the highest version
     *
     * @var bool
     */
    private bool $download;

    /**
     * Variable to decide if we are operating in a docker container
     *
     * @var bool
     */
    private bool $docker;

    /**
     * Variable to retrieve the highest versions
     *
     * @var bool
     */
    private bool $highestVersion;

    /**
     * Variable to store if you want to retrieve the torrent links
     *
     * @var bool
     */
    private bool $torrentLinks;

    /**
     * Variable to store if you want to update Plex
     *
     * @var bool
     */
    private bool $plexToken;

    /**
     * Variable to store the start page
     *
     * @var int
     */
    private int $startPage;

    /**
     * Variable to store the number of pages to render
     *
     * @var int
     */
    private ?int $pageCount;

    /**
     * Retrieve a list of Plex libraries to determine which is the main movie library
     *
     * @var bool
     */
    private bool $libraryList;

    /**
     * Store if we should merge databases
     *
     * @var bool
     */
    private bool $merge;

    /**
     * Should we output verbose
     *
     * @var bool
     */
    private bool $verbose;

    /**
     * Variable to store a url that needs to be directly retrieved for testing purposes
     *
     * @var string
     */
    private ?string $url;

    /**
     * Variable to store to signal updating the Plex database
     *
     * @var bool
     */
    private bool $plex;

    /**
     * Magic getter method
     *
     * @param string $var
     *
     * @return mixed
     */
    public function __get(string $var)
    {
        $vars = get_class_vars(CMD::class);
        if (in_array($var, array_keys($vars))) {
            return $this->{$var};
        }
        return null;
    }

    /**
     * Magic setter method
     *
     * @param string $var
     * @param string $val
     */
    public function __set(string $var, $val)
    {
        $vars = get_class_vars(CMD::class);
        if (in_array($var, array_keys($vars))) {
            $this->{$var} = $val;
        }
    }

    /**
     * Method to get the command line parameters and return an object with them
     *
     * @return stdClass
     */
    public static function getCommandParameters()
    {
        $ret = new static();
        $arr = getopt('h', [
            'install::', 'update::', 'download::', 'page:', 'count:', 'highestVersion::',
            'torrentLinks::', 'plexToken::', 'libraryList::', 'help::', 'url:', 'verbose::',
            'merge::', 'log::', 'clean::', 'docker::', 'plex::'
        ]);

        $ret->showHelp = (bool) (isset($arr['h']) || isset($arr['help']));

        $ret->docker = isset($arr['docker']);
        $ret->install = isset($arr['install']);
        $ret->update = isset($arr['update']);
        $ret->download = isset($arr['download']);
        $ret->highestVersion = isset($arr['highestVersion']);
        $ret->torrentLinks = isset($arr['torrentLinks']);
        $ret->plexToken = isset($arr['plexToken']);
        $ret->libraryList = isset($arr['libraryList']);
        $ret->verbose = isset($arr['verbose']);
        $ret->merge = isset($arr['merge']);
        $ret->log = isset($arr['log']);
        $ret->clean = isset($arr['clean']);
        $ret->plex = isset($arr['plex']);
        $ret->startPage = (
            isset($arr['page']) && is_numeric($arr['page']) && $arr['page'] > 0 ? $arr['page'] : 1
        );
        $ret->pageCount = (
            isset($arr['count']) && is_numeric($arr['count']) && $arr['count'] > 0 ? $arr['count'] : null
        );
        $ret->url = null;
        $ret->url = (
            isset($arr['url']) && is_string($arr['url']) && filter_var($arr['url'], FILTER_VALIDATE_URL) ? $arr['url'] : null
        );

        return $ret;
    }
}
