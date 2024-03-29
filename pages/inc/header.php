<?php

use YTS\YTS;

$yts = new YTS();
$page = isset($_GET['page']) ? $_GET['page'] : 1;

$title = 'Movie Listing';
if (isset($_SERVER['REQUEST_URI'])) {
    if (preg_match("/\/duplicates\//", $_SERVER['REQUEST_URI'])) {
        $title = 'Duplicates';
    } elseif (preg_match("/\/new\-movies\//", $_SERVER['REQUEST_URI'])) {
        $title = 'New Movies';
    } elseif (preg_match("/\/plex\//", $_SERVER['REQUEST_URI'])) {
        $title = 'Plex Movies';
    } elseif (preg_match("/\/downloaded\//", $_SERVER['REQUEST_URI'])) {
        $title = 'Downloaded';
    }
} else {
    $_SERVER['REQUEST_URI'] = '';
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <link href='/css/style.css' type='text/css' rel='stylesheet' />
    <link href='//code.jquery.com/ui/1.13.1/themes/dot-luv/jquery-ui.css' />
    <link href='//cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/regular.min.css' type='text/css'
        rel='stylesheet' />

    <title>
        <?php print $title; ?>
    </title>
</head>

<body>

    <div>
        <?php
        if ($title != 'Movie Listing') {
            print "<a class='pageButtons' href='/'>Home</a>&nbsp;&nbsp;";
        }
        if ($title != 'New Movies') {
            print "<a class='pageButtons' href='/new-movies/'>New Movies</a>&nbsp;&nbsp;";
        }
        if ($title != 'Duplicates') {
            print "<a class='pageButtons' href='/duplicates/'>Duplicates</a>&nbsp;&nbsp;";
        }
        if ($title != 'Plex Movies') {
            print "<a class='pageButtons' href='/plex/'>Plex Movies</a>&nbsp;&nbsp;";
        }
        if ($title != 'Downloaded') {
            print "<a class='pageButtons' href='/downloaded/'>Downloaded</a>&nbsp;&nbsp;";
        } ?>
        <a href='#' class='pageButtons' onclick='javascript:toggleStatus()'>Toggle</a>
        <input type=number id='page-count' min=10 max=100 onchange='javascript:saveMovieCount();'
            value=<?php print $_ENV['MOVIE_COUNT']; ?>
        />
        <span
            id='movieCount'><?php print $yts->getMovieCount(); ?></span>
        <span
            id='pageCount'><?php print $yts->getPageCount() ;?></span>
    </div>

    <div>
        <div id='search-container'>
            <input type='text' name='search' id='search' />
            <?php
            $tsConnected = $yts->isTransmissionConnected();
            include_once(
                $tsConnected ?
                ROOT."/public/assets/green.svg" :
                ROOT."/public/assets/red.svg"
            );
            ?>
        </div>

        <div id='pager'>
            <?php
            $pageCount = (int) $yts->getMovieCount() / $_ENV['MOVIE_COUNT'];
            $url = (isset($_SERVER['REDIRECT_URL']) ? $_SERVER['REDIRECT_URL'] : '/');

            if ($page > 1) {
                print "<a href='{$url}' class='pageButtons'>&lt;&lt;</a>&nbsp;";
                print "<a class='pageButtons' href='{$url}?page=".($page-1)."'>&lt;</a>&nbsp;";
            }
            if (($page + 1) < $pageCount) {
                print "<a class='pageButtons' href='{$url}?page=".($page+1)."'>&gt;</a>";
            }
            ?>
            <span id='downloadSize'></span>&nbsp;&nbsp;
            <span id='freeSpace'></span>&nbsp;&nbsp;
        </div>

        <div id='container'>