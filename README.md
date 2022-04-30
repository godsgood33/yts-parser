# YIFY Parser

This library scrapes the YIFY website (https://yts.mx) for movie listings, and stores movie data to a database.  You can then set a `download` flag for any movie you want to download.  You can also pass in your Plex library file name (`com.plexapp.plugins.library.db`) so the script can check if you already have a movie.  It can then attempt to download a higher resolution version of the file you already have.  There are also flags for if you have already downloaded the movie in 720p, 1080p, or 4k.  If you already have a lower resolution version, you can change that flag as well.  Then it will download a higher resolution version, if available.

## Install

Once you download the files, create a `.env` file in the directory and copy the following to it replacing the info to match your environment.  If you do not want to use this

```
TRANSMISSION_URL={IP of the Transmission server}
TRANSMISSION_PORT={Port of the Transmission server}
TRANSMISSION_USER={username to connect to the Transmission server}
TRANSMISSION_PASSWORD={password for the Transmission server user}
TRANSMISSION_DOWNLOAD_DIR={Download directory on the Transmission server}
```

Then you need to run `php yts-parser.php --install`.  This will create the required database table in the SQLite3 `movies.db` database. 

## Plex

If you have a Plex media server you can either copy the library (if it's on a different computer), or pass in the library path to the `--plex={path}` option when calling the `--update` option.  This will then query the Plex library for a matching title and year to determine what resolution of file you currently have.  Then when calling the `--download` option it will check those options and attempt to download a higher resolution version of the same movie.  If there will not be enough to download the torrent, the torrent will not start.

## Web Server

If you are using a recent version of PHP you can run a PHP web server to see what was retrieved.  From the main directory execute...`php -S {ip}:{port} -t public/`, with composer you can run `composer web` to start a web server on the localhost:8080.  Then you can open a browser and navigate to that IP and port.  The page displayed will be an alphabetic listing of all movies retrieved.  Movie titles highlighted red means you have a 720p version in Plex, yellow highlighting is a 1080p version, and green is a 4k version.

Right next to the search box, there is a red or green circle.  If it is green that means that this is able to communicate with the Transmission server.  You can then click the `Download` button to have the system retrieve the torrent link from the movie listing on yts and send that to the Transmission server.  It will automatically start downloading the movie ```(so make sure your Transmission server is on a VPN)```.

NOTE: If you have an as good or better version than what is on the site, the download button will not appear.
