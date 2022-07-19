<?php

namespace YTS;

/**
 * Class to store command params
 *
 * @property bool $showHelp
 * @property bool $install
 * @property bool $update
 * @property bool $download
 * @property bool $highestVersion
 * @property bool $torrentLinks
 * @property bool $plexToken
 * @property int $startPage
 * @property int $pageCount
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
     * Method to get the command line parameters and return an object with them
     *
     * @return stdClass
     */
    public static function getCommandParameters()
    {
        $ret = new static();
        $arr = getopt('h', [
            'install::', 'update::', 'download::', 'page:', 'count:', 'highestVersion::',
            'torrentLinks::', 'plexToken::', 'libraryList::', 'help::'
        ]);

        $ret->showHelp = (bool) (isset($arr['h']) || isset($arr['help']));

        $ret->install = isset($arr['install']);
        $ret->update = isset($arr['update']);
        $ret->download = isset($arr['download']);
        $ret->highestVersion = isset($arr['highestVersion']);
        $ret->startPage = (
            isset($arr['page']) && is_numeric($arr['page']) && $arr['page'] > 0 ? $arr['page'] : 1
        );
        $ret->pageCount = (
            isset($arr['count']) && is_numeric($arr['count']) && $arr['count'] > 0 ? $arr['count'] : null
        );
        $ret->torrentLinks = isset($arr['torrentLinks']);
        $ret->plexToken = isset($arr['plexToken']);
        $ret->libraryList = isset($arr['libraryList']);

        return $ret;
    }
}
