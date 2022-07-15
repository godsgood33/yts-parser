# YIFY Parser

This library scrapes the YIFY website (https://yts.mx) for movie listings, and stores movie data to a SQLite3 database.  You can use the built in web content to render an alphabetic listing of all of the movies and click the `Download` button if you'd like to download that movie.  You can also have it look in your Plex Media Server to see if a higher resolution version of the same movie is available.  There are also flags for if you have already downloaded the movie in 720p, 1080p, or 4k.  If you already have a lower resolution version, you can change that flag as well.

## Install

Once you have the files downloaded and in the directory you want, run the installer `php yts-parser.php --install`.  This will create the SQLite3 database and tables, and create the `.env` file and populate it with the necessary values.  You will then need to edit the `.env` with your choosen text editor and update the values as appropriate for your setup.

## Plex

If you have a Plex Media Server, you can update the `PLEX_SERVER` field in the `.env` file with the correct IP address (no host name).  If you do this, then when running the script with `--update` it will look at your Plex server and see if you already have that movie and what the resoution is.  When calling the script with the `--download` flag, it will look for movies that have a higher resolution version.

## Web Server

If you are using a recent version of PHP you can run a PHP web server to see what was retrieved.  From the main directory execute...`php -S {ip}:{port} -t public/`, with composer you can run `composer web` to start a web server on the localhost:8080.  Then you can open a browser and navigate to that IP and port.  The page displayed will be an alphabetic listing of all movies retrieved.  Movie titles highlighted red means you have a 720p version in Plex, yellow highlighting is a 1080p version, and green is a 4k version.

Right next to the search box, there is a red or green circle.  If it is green that means that this is able to communicate with the Transmission server.  You can then click the `Download` button to have the system retrieve the torrent link from the movie listing on yts and send that to the Transmission server.  It will automatically start downloading the movie ```(so make sure your Transmission server is on a VPN)```.

NOTE: If you have an as good or better version than what is on the site, the download button will not appear.
