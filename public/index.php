<?php

require_once dirname(__DIR__).'/vendor/autoload.php';

use Godsgood33\YTS\YTS;
use Godsgood33\YTS\DotEnv;

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

    <title>Movie Listing</title>
</head>

<body>

    <div>
        <div>
            <input type='text' name='search' id='search' />
            <?php include_once($yts->isTransmissionConnected() ? __DIR__."/assets/green.svg" : __DIR__."/assets/red.svg") ?>
        </div>

        <div id='pager'>
            <?php
            print "<a href='/'>&lt;&lt;</a>&nbsp;";
            if ($page > 1) {
                print "<a href='/?page=".($page-1)."'>&lt;</a>&nbsp;";
            }
            print "<a href='/?page=".($page+1)."'>&gt;</a>";
            ?>
        </div>

        <div id='container'>
            <?php
            $cont = 1;
            foreach ($movies as $movie) {
                $button = null;
                $class = $movie->getClass();
                $encodedTitle = urlencode($movie->title);
                $encodedYear = urlencode($movie->year);

                if ($class != 'have4k' && $yts->isTransmissionConnected()) {
                    $button = "<input 
                        type='button' 
                        class='download' 
                        data-title='{$encodedTitle}' 
                        data-year='{$encodedYear}' 
                        value='Download' />";
                }
                print "<div class='movie'>
                <a href='/details.php?title={$encodedTitle}".
                    "&year={$encodedYear}' ".
                    "target='_blank'>
                    <img src='{$movie->imgUrl}'/>
                </a><br />
                <span class='{$class}'>{$movie->title} ({$movie->year})</span><br />
                {$button}
            </div>";
                $cont++;
            }
            
            ?>
        </div>

    </div>

    <script src='https://code.jquery.com/jquery-3.6.0.min.js'></script>
    <script src='https://code.jquery.com/ui/1.13.1/jquery-ui.min.js'></script>
    <script src='/js/script.js'></script>

    <script>
        $(function() {
            $('#search').autocomplete({
                source: 'query.php',
                minLength: 3
            });
        });
    </script>

</body>

</html>