<?php

require_once dirname(__DIR__).'/vendor/autoload.php';

use YTS\YTS;
use YTS\DotEnv;

DotEnv::load(dirname(__DIR__).'/.env');
$yts = new YTS();
$page = $_GET['page'] ?? 1;
$movies = $yts->getMoviesByPage($page);
?>

<!doctype html>
<html>

<head>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <link href='/css/style.css' type='text/css' rel='stylesheet' />
    <link href='//code.jquery.com/ui/1.13.1/themes/dot-luv/jquery-ui.css' />
    <link href='//cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/regular.min.css' type='text/css'
        rel='stylesheet' />

    <title>Movie Listing</title>
</head>

<body>

    <div>
        <div id='search-container'>
            <input type='text' name='search' id='search' />
            <?php include_once($yts->isTransmissionConnected() ? __DIR__."/assets/green.svg" : __DIR__."/assets/red.svg") ?>
        </div>

        <div id='pager'>
            <?php
            print "<a href='/' class='pageButtons'>&lt;&lt;</a>&nbsp;";
            if ($page > 1) {
                print "<a class='pageButtons' href='/?page=".($page-1)."'>&lt;</a>&nbsp;";
            }
            print "<a class='pageButtons' href='/?page=".($page+1)."'>&gt;</a>";
            ?>
        </div>

        <div id='container'>
            <?php
            foreach ($movies as $movie) {
                print $movie->getHtml();
            }
            
            ?>
        </div>

    </div>

    <script src='https://code.jquery.com/jquery-3.6.0.min.js'></script>
    <script src='https://code.jquery.com/ui/1.13.1/jquery-ui.min.js'></script>
    <script src='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/js/regular.min.js'></script>
    <script src='/js/script.js'></script>
</body>

</html>