<?php

namespace YTS;

use jc21\PlexApi;
use jc21\Util\Filter;

/**
 * Class to store Plex and interact with database
 */
class Plex
{
    /**
     * connection to a Plex server
     *
     * @var PlexApi
     */
    private PlexApi $api;

    /**
     * Plex library
     *
     * @var array
     */
    private array $library;

    /**
     * Constructor
     *
     * @param PlexApi $server
     */
    public function __construct(?PlexApi $server)
    {
        $this->api = $server;
    }

    /**
     * Method to check if the api is connected the Plex server
     *
     * @return bool
     */
    public function isConnected()
    {
        return is_a($this->api, "jc21\PlexApi");
    }

    /**
     * Method to check the Plex library for a particular title
     *
     * @param Movie $m
     *
     * @return \jc21\Movies\Movie|bool
     */
    public function check(Movie &$m): \jc21\Movies\Movie|bool
    {
        $filter1 = new Filter('title', $m->title);
        $filter2 = new Filter('year', $m->year);
        $res = $this->api->filter($_ENV['PLEX_MOVIE_LIBRARY'], [$filter1, $filter2], true);

        if (is_a($res, 'jc21\Collections\ItemCollection') && $res->count()) {
            $movie = $res->getData();
            return $movie;
        }

        return false;
    }

    /**
     * Method to check for Plex environment variables
     *
     * @return bool
     */
    public static function validateEnvironment(): bool
    {
        $ret = true;

        if (!isset($_ENV['PLEX_SERVER']) ||
            !$_ENV['PLEX_SERVER'] ||
            !filter_var($_ENV['PLEX_SERVER'], FILTER_VALIDATE_IP, FILTER_NULL_ON_FAILURE)) {
            $ret = false;
            print "Invalid Plex server IP".PHP_EOL;
        }

        if (!isset($_ENV['PLEX_TOKEN']) || !$_ENV['PLEX_TOKEN']) {
            if (!isset($_ENV['PLEX_USER']) ||
                !$_ENV['PLEX_USER'] ||
                !filter_var($_ENV['PLEX_USER'], FILTER_VALIDATE_EMAIL, FILTER_NULL_ON_FAILURE)) {
                $ret = false;
                print "Invalid Plex user email".PHP_EOL;
            }

            if (!isset($_ENV['PLEX_PASSWORD'])) {
                $ret = false;
                print "PLEX_PASSWORD environment variable not present".PHP_EOL;
            }
        }

        return $ret;
    }
}
