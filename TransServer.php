<?php

namespace YTS;

use Transmission\Client;
use Transmission\Transmission;
use Transmission\Model\Status;
use Transmission\Model\Torrent;

/**
 * Class to act as a mediator with a Transmission Server
 */
class TransServer
{
    /**
     * Client connection
     *
     * @var Client
     */
    private Client $client;

    /**
     * Transmission server connection
     *
     * @var Transmission
     */
    private Transmission $rpc;

    /**
     * Variable to store the download directory
     *
     * @var string
     */
    private string $downloadDir;

    /**
     * Variable to store the total size available on the Transmission server
     *
     * @var int
     */
    public int $freeSpace;

    /**
     * Variable to store the total download size
     *
     * @var int
     */
    public int $downloadSize;

    /**
     * Variable to store arrays
     *
     * @var array
     */
    private array $torrents;

    /**
     * Constructor
     *
     * @param string $url URL of the Transmission server
     * @param int $port Port number for the Transmission server
     * @param string $user User to connect to the Transmission server
     * @param string $password Password to connect to the Tranmission server
     */
    public function __construct(
        string $downloadDir,
        string $url,
        int $port = 9091,
        string $user = null,
        string $password = null
    ) {
        $this->downloadDir = $downloadDir;

        $this->rpc = new Transmission($url, $port);
        if ($user && $password) {
            $this->client = new Client($url, $port);
            $this->client->authenticate($user, $password);

            $this->rpc->setClient($this->client);
        }

        $fs = $this->rpc->getFreeSpace($this->downloadDir);
        $this->freeSpace = $fs->getSize();

        $this->updateDownloadSpace();
    }

    /**
     * Method to add torrent
     *
     * @param string $url
     *
     * @return Torrent
     */
    public function add(string $url): Torrent
    {
        $tor = $this->rpc->add($url, false, $this->downloadDir);
        $this->rpc->stop($tor);

        $this->updateDownloadSpace();

        if ($this->freeSpace > $this->downloadSize) {
            $this->rpc->start($tor);
        }

        return $tor;
    }

    /**
     * Method to start a torrent
     *
     * @param Torrent $tor
     */
    public function start(Torrent $tor)
    {
        $this->rpc->start($tor);
    }

    /**
     * Method to start all torrents
     */
    public function startAll()
    {
        foreach ($this->torrents as $tor) {
            $this->rpc->start($tor);
        }
    }

    /**
     * Method to get download size
     */
    public function updateDownloadSpace()
    {
        $this->torrents = $this->rpc->all();
        $this->downloadSize = 0;

        foreach ($this->torrents as $tor) {
            if ($tor->getStatus() != Status::STATUS_STOPPED) {
                $this->downloadSize += $tor->getSize();
            }
        }
    }

    /**
     * Method to pretify
     *
     * @param int $bytes
     * @param int $decimals
     *
     * @return string
     */
    public static function humanReadableSize(int $bytes, int $decimals = 2): string
    {
        $size = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];
        $factor = floor((strlen((string) $bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
    }
}
