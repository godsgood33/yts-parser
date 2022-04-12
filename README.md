# YIFY Parser

This library scrapes the YIFY website (https://yts.mx) for movie listings, and stores the movie title, release year, URL, and other info in a database table.  You can then update a `download` flag for any movie you want to download.  You can also pass in your Plex library file name so the script can check if you already have a movie.  It can then attempt to download a higher resolution version of the file you already have.  There are also flags for if you have already downloaded the movie in 720p, 1080p, or 4k.  If you already have a lower resolution version, you can change that flag as well.  Then it will download a higher resolution version if available.

## Install

Once you download the files, create a `.env` file in the directory and copy the following to it replacing the info to match your environment.

```
TRANSMISSION_URL={IP of the Transmission server}
TRANSMISSION_PORT={Port of the Transmission server}
TRANSMISSION_USER={username to connect to the Transmission server}
TRANSMISSION_PASSWORD={password for the Transmission server user}
TRANSMISSION_DOWNLOAD_DIR={Download directory on the Transmission server}

HTTP_DEBUG=0
```

Then you need to run `php yts-parser.php --install`.  This will create the required database table in the SQLite3 `movies.db` database. 

## Plex

If you have a Plex media server you can either copy the library (if it's on a different computer), or pass in the library path to the `--plex={path}` option when calling the `--update` option.  This will then query the Plex library for a matching title and year to determine what resolution of file you currently have.  Then when calling the `--download` option it will check those options and attempt to download a higher resolution version of the same movie.  If the disk that contains the `TRANSMISSION_DOWNLOAD_DIR` gets under 10,000,000,000 bytes, it will throw an error.

