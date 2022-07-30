<?php

use YTS\YTS;

$yts = new YTS();
$movies = $yts->getNewerMovies();
?>

<!DOCTYPE html>
<html>

<head>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <link href='/css/style.css' type='text/css' rel='stylesheet' />
    <link href='//code.jquery.com/ui/1.13.1/themes/dot-luv/jquery-ui.css' />
    <link href='//cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/regular.min.css' type='text/css'
        rel='stylesheet' />

    <title>New Movies</title>
</head>

<body>
    <div>
        <a class='pageButtons' href='/'>Home</a>
        <a href='#' class='pageButtons' onclick='javascript:toggleStatus()'>Toggle</a>
    </div>

    <div>
        <div id='search-container'>
            <input type='text' name='search' id='search' />
            <?php
            $tsConnected = $yts->isTransmissionConnected();
            include_once(
                $tsConnected ?
                dirname(__DIR__)."/public/assets/green.svg" :
                dirname(__DIR__)."/public/assets/red.svg"
            );
            ?>
        </div>

        <div id='pager'>
            <?php
            $pageCount = $movies->count() / YTS::PAGE_COUNT;

            if ($page > 1) {
                print "<a href='/new-movies' class='pageButtons'>&lt;&lt;</a>&nbsp;";
                print "<a class='pageButtons' href='/new-movies/?page=".($page-1)."'>&lt;</a>&nbsp;";
            }
            if (($page + 1) < $pageCount) {
                print "<a class='pageButtons' href='/new-movies/?page=".($page+1)."'>&gt;</a>";
            }
            ?>
            <span id='downloadSize'></span>&nbsp;&nbsp;
            <span id='freeSpace'></span>&nbsp;&nbsp;
        </div>

        <div id='container'>
            <?php
            foreach ($movies as $movie) {
                print $movie->getHtml($tsConnected);
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