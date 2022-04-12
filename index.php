<?php

$db = new SQLite3('movies.db');

$page = 1;
$offset = 1;
const PAGE_COUNT = 24;

if (isset($_GET['page']) && $_GET['page']) {
    $page = $_GET['page'];
    $offset = ($page - 1) * PAGE_COUNT;
}

$res = $db->query("SELECT * FROM movies ORDER BY `title`,`year` LIMIT $offset,".PAGE_COUNT);

?>

<html>

<head>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <link href='/css/style.css' type='text/css' rel='stylesheet' />
    <link href='//code.jquery.com/ui/1.13.1/themes/dot-luv/jquery-ui.css' />

    <title>Movie Listing</title>
</head>

<body>

    <div id='container' style="width:80%;">
        <div>
            <input type='text' name='search' id='search' />
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
        <?php
        while ($movie = $res->fetchArray(SQLITE3_ASSOC)) {
            print "<span style='float:left;'>
            <a href='/details.php?title=".
                urlencode($movie['title']).
                "&year=".urlencode($movie['year']).
                "' target='_blank'>
                <img src='{$movie['imgUrl']}'/>
            </a><br />{$movie['title']} ({$movie['year']})<br />
        </span>";
        }
        
        ?>

    </div>

    <script src='https://code.jquery.com/jquery-3.6.0.min.js'></script>
    <script src='https://code.jquery.com/ui/1.13.1/jquery-ui.min.js'></script>

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