<?php

use jc21\PlexApi;
use YTS\Plex;
use YTS\PlexMovie;
use YTS\YTS;

$page = (isset($_GET['page']) ? $_GET['page'] : 1);
include_once('inc/header.php');

$movies = $yts->getMoviesByPage($page);
$api = new PlexApi($_ENV['PLEX_SERVER']);
$api->setToken($_ENV['PLEX_TOKEN']);
$plex = new Plex($api);

foreach ($movies as $movie) {
    /** @var \YTS\Movie $movie */

    $pm = $plex->check($movie);
    if ($pm) {
        $movie->setPlexMovie(new PlexMovie($pm));
    }

    print $movie->getHtml($tsConnected);
}

include_once('inc/footer.php');
