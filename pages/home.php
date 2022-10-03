<?php

use YTS\YTS;

$page = (isset($_GET['page']) ? $_GET['page'] : 1);
include_once('inc/header.php');

$movies = $yts->getMoviesByPage($page);

foreach ($movies as $movie) {
    /** @var \YTS\Movie $movie */
    print $movie->getHtml($tsConnected);
}

include_once('inc/footer.php');
