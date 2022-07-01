<?php

use Bramus\Router\Router;

use YTS\YTS;

$router = new Router();

$router->get('/', function () {
    require_once(dirname(__DIR__).'/pages/home.php');
});

$router->get('/movie/{title}/year/{year}/', function ($encodedTitle, $year) {
    $yts = new YTS();
    $title = urldecode($encodedTitle);
    $movie = $yts->getMovie($title, $year);

    $movie->getDetails();
});

$router->get('/new-movies', function () {
    require_once(dirname(__DIR__).'/pages/new-movies.php');
});

$router->get('/search/{query}', function ($query) {
    $yts = new YTS();
});

$router->run();
