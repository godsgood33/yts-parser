<?php

namespace YTS;

use Exception;
use RuntimeException;
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
     */
    public function __construct()
    {
        $downloadDir = $_ENV['TRANSMISSION_DOWNLOAD_DIR'];
        if ($downloadDir) {
            $this->downloadDir = $downloadDir;
        }
        $url = $_ENV['TRANSMISSION_URL'];
        $port = $_ENV['TRANSMISSION_PORT'];

        if (!$url || !$port || !$downloadDir) {
            throw new Exception('Transmission data not available');
        }

        $this->rpc = new Transmission($url, $port);

        $user = $_ENV['TRANSMISSION_USER'];
        $password = $_ENV['TRANSMISSION_PASSWORD'];

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
     * Method to check for and start a download
     *
     * @param Movie $movie
     * @param string $quality
     *
     * @return Torrent|null
     */
    public function checkForDownload(Movie &$movie, string $quality)
    {
        $tor = null;
        if ($movie->uhdTorrent && $quality == 'uhd') {
            $tor = $this->add($movie->uhdTorrent);
            if (is_a($tor, 'Transmission\Model\Torrrent')) {
                $movie->download = false;
                $movie->uhdComplete = true;
                $movie->fhdComplete = true;
                $movie->hdComplete = true;
            }
        } elseif ($movie->fhdTorrent && $quality == 'fhd') {
            $tor = $this->add($movie->fhdTorrent);
            if (is_a($tor, 'Transmission\Model\Torrent')) {
                $movie->fhdComplete = true;
                $movie->hdComplete = true;
            }
        } elseif ($movie->hdTorrent && $quality == 'hd') {
            $tor = $this->add($movie->hdTorrent);
            if (is_a($tor, 'Transmission\Model\Torrrent')) {
                $movie->hdComplete = true;
            }
        }

        return $tor;
    }

    /**
     * Method to retrieve all the torrents
     *
     * @return Torrent[]
     */
    public function all()
    {
        return $this->rpc->all();
    }

    /**
     * Method to add torrent
     *
     * @param string $url
     *
     * @return Torrent|bool
     */
    public function add(string $url): Torrent|bool
    {
        try {
            $tor = $this->rpc->add($url, false, $this->downloadDir);
        } catch (RuntimeException $e) {
            print "Failed to add torrent".PHP_EOL;
            return false;
        }
        $this->rpc->stop($tor);

        $this->updateDownloadSpace();

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
     * Method to stop a torrent
     *
     * @param Torrent $torrent
     */
    public function stop(Torrent $torrent)
    {
        $this->rpc->stop($torrent);
    }

    /**
     * Method to get download size
     */
    public function updateDownloadSpace()
    {
        $this->torrents = $this->rpc->all();
        $this->downloadSize = 0;
        $fs = $this->rpc->getFreeSpace($this->downloadDir);
        $this->freeSpace = $fs?->getSize();

        foreach ($this->torrents as $tor) {
            if (in_array(
                $tor->getStatus(),
                [
                    Status::STATUS_DOWNLOAD_WAIT,
                    Status::STATUS_DOWNLOAD
                ]
            )) {
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
