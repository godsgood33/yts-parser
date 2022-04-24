<?php

namespace Godsgood33\YTS;

use SQLite3;

/**
 * Class to store Plex and interact with database
 */
class Plex
{
    /**
     * SQLite 3 database connection
     *
     * @var SQLite3
     */
    private SQLite3 $db;

    /**
     * Variable to know if the database is connected
     *
     * @var bool
     */
    private bool $isConnected;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->isConnected = false;
    }

    /**
     * Set database connection if available
     *
     * @param string $dbFile
     */
    public function setDB(string $dbFile)
    {
        $this->db = new SQLite3($dbFile);
        $this->isConnected = true;
    }

    /**
     * Method to check if the plex database is present
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->isConnected;
    }

    /**
     * Method to check the Plex library for a particular title
     *
     * @param Movie $m
     */
    public function check(Movie &$m)
    {
        $this->db->enableExceptions(true);
        $res = $this->db->query(
            "SELECT mdi.`id`,mdi.`title`,mi.`height`,mi.`width`
            FROM `media_items` mi
            JOIN `metadata_items` mdi ON mdi.id = mi.metadata_item_id 
            WHERE 
            LOWER(mdi.`title`) = LOWER('{$this->db->escapeString($m->title)}')
            AND 
            mdi.`year` = '{$this->db->escapeString($m->year)}'
            AND
            mdi.`metadata_type` = 1
            ORDER BY mi.`width` DESC"
        );

        $row = $res->fetchArray(SQLITE3_ASSOC);

        return $row;
    }
}
