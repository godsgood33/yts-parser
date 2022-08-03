<?php

use YTS\YTS;

include_once('inc/header.php');

$yts = new YTS();
$movies = $yts->getNewerMovies();

foreach ($movies as $movie) {
    print $movie->getHtml($tsConnected);
}

include_once('inc/footer.php');
