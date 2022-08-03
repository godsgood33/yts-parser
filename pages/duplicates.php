<?php

use YTS\YTS;

$page = $_GET['page'] ?? 1;
include_once('inc/header.php');

$yts = new YTS();
$movies = $yts->getDuplicateMovies($page);

foreach ($movies as $movie) {
    print $movie->getDuplicateHtml($tsConnected);
}

include_once('inc/footer.php');
