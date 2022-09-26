<?php

use YTS\YTS;

$page = $_GET['page'] ?? 1;
include_once('inc/header.php');

$yts = new YTS();
$tsConnected = $yts->isTransmissionConnected();
$movies = $yts->getDownloaded($page, false);

foreach ($movies as $movie) {
    print $movie->getHtml($tsConnected);
}

include_once('inc/footer.php');
