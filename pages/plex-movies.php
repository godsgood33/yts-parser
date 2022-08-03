<?php

use jc21\PlexApi;
use YTS\YTS;
use YTS\Plex;

$page = $_GET['page'] ?? 1;
include_once('inc/header.php');

if (!Plex::validateEnvironment()) {
    die('Plex connection not available');
}

$api = new PlexApi($_ENV['PLEX_SERVER']);
$api->setToken($_ENV['PLEX_TOKEN']);
$yts = new YTS();
$plex = new Plex($api);
$tsConnected = $yts->isTransmissionConnected();

foreach ($plex->getLibrary() as $idhash => $m) {
    /** @var \jc21\Movies\Movie $m */
    $movie = $yts->getMovie($m->title, $m->year);
    if (is_a($movie, 'YTS\Movie')) {
        print $movie->getHtml($tsConnected);
    }
}

include_once('inc/footer.php');
