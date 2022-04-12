<?php

require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/Movie.php';
require_once __DIR__.'/YTS.php';
require_once __DIR__.'/TransServer.php';

use YTS\YTS;

$yts = new YTS();

$title = $_GET['title'];
$year = $_GET['year'];
$movie = $yts->getMovie($title, $year);

?>

<html>

<head>
    <title>
        Movie details - <?php print (string) $movie; ?>
    </title>

    <link href='/css/style.css' type='text/css' rel='stylesheet' />
    <link href='//code.jquery.com/ui/1.13.1/themes/dot-luv/jquery-ui.css' />
</head>

<body>
    <h1>
        <?php print $movie->title; ?>
    </h1>

    <div>
        <img src='<?php print $movie->imgUrl; ?>' />
    </div>

    <form method='get'>
        <input type='hidden' name='title' id='title'
            value='<?php print urlencode($movie->title); ?>' />
        <input type='hidden' name='year' id='year'
            value='<?php print urlencode($movie->year); ?>' />

        Highest quality:
        <?php print $movie->highestResolution; ?>
        <br />
        Movie URL:
        <a href='<?php print $movie->url; ?>'>Link</a>
        <br />
        Download:
        <input type='checkbox' name='download' id='download' value='1' <?php print $movie->download ? 'checked readonly' : null ?>
        />
        <br />
        Highest Resolution Available:
        <?php print $movie->highestResolutionAvailable; ?>

    </form>

    <script src='https://code.jquery.com/jquery-3.6.0.min.js'></script>
    <script src='https://code.jquery.com/ui/1.13.1/jquery-ui.min.js'></script>
    <script src='/js/script.js'></script>
</body>

</html>