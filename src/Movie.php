<?php

namespace YTS;

use jc21\Movies\Movie as MoviesMovie;

/**
 * Class to create a movie
 */
class Movie
{
    /**
     * Variable to store the title of the movie
     *
     * @var string
     */
    public string $title;

    /**
     * Movie release year
     *
     * @var int
     */
    public int $year;

    /**
     * Movie language
     *
     * @var string
     */
    public string $lang;

    /**
     * Variable to store the url to the movie itself
     *
     * @var string
     */
    public string $url;

    /**
     * Variable to store URL to the image
     *
     * @var string
     */
    public ?string $imgUrl;

    /**
     * Variable to decide if we are downloading the file
     *
     * @var bool
     */
    public bool $download;

    /**
     * 720p Torrent link
     *
     * @var string
     */
    public ?string $hdTorrent;

    /**
     * Has the 720p Torrent been downloaded
     *
     * @var bool
     */
    public bool $hdComplete;

    /**
     * 1080p Torrent Link
     *
     * @var string
     */
    public ?string $fhdTorrent;

    /**
     * Has the 1080p Torrent been downloaded
     *
     * @var bool
     */
    public bool $fhdComplete;

    /**
     * 2160p Torrent Link
     *
     * @var string
     */
    public ?string $uhdTorrent;
    
    /**
     * Has the 2160p torrent been downloaded
     *
     * @var bool
     */
    public bool $uhdComplete;

    /**
     * Variable to store if the movie has been retrieved
     *
     * @var bool
     */
    public bool $retrieved;

    /**
     * Constructor
     *
     * @param string $title
     * @param string $url
     */
    public function __construct(
        string $title,
        string $url,
        ?string $imgUrl
    ) {
        $this->title = $title;
        $year = substr($url, -4);
        $this->year = (int) $year;
        $this->url = $url;
        $this->imgUrl = $imgUrl;
        $this->download = 0;

        $this->retrieved = 0;
        $this->hdTorrent = null;
        $this->hdComplete = 0;
        $this->fhdTorrent = null;
        $this->fhdComplete = 0;
        $this->uhdTorrent = null;
        $this->uhdComplete = 0;
    }

    /**
     * Method to convert the object to a string
     */
    public function __toString(): string
    {
        return "{$this->title} ({$this->year})";
    }

    /**
     * Method to check if a higher resolution version of the movie is available
     *
     * @return bool
     */
    public function betterVersionAvailable(): bool
    {
        if ($this->uhdComplete) {
            return false;
        }
        if ($this->fhdComplete && empty($this->uhdTorrent)) {
            return false;
        }
        if ($this->hdComplete && empty($this->fhdTorrent) && empty($this->uhdTorrent)) {
            return false;
        }

        return true;
    }

    /**
     * Method to get the highest version
     *
     * @return string
     */
    public function highestVersion(): ?string
    {
        $ret = null;
        if ($this->uhdComplete) {
            $ret = '4K';
        } elseif ($this->fhdComplete) {
            $ret = 'FHD';
        } elseif ($this->hdComplete) {
            $ret = 'HD';
        }

        return $ret;
    }

    /**
     * Method to determine if there is a better version to download
     *
     * @return string
     */
    public function highestVersionAvailable(): string
    {
        $ret = 'HD';
        if (!$this->uhdComplete && $this->uhdTorrent) {
            $ret = 'UHD';
        } elseif (!$this->fhdComplete && $this->fhdTorrent) {
            $ret = 'FHD';
        }

        return $ret;
    }

    /**
     * Method to set resolutions
     *
     * @param MoviesMovie $res
     */
    public function setDownload(MoviesMovie $res)
    {
        if ($res->media->videoResolution == 'sd') {
            $this->download = true;
            return;
        }

        if ($res->media->videoResolution == 720) {
            print "HD version found".PHP_EOL;
            $this->download = true;
            $this->hdComplete = true;
            return;
        }
        
        if ($res->media->videoResolution == 1080) {
            print "FHD version found".PHP_EOL;
            $this->download = true;
            $this->hdComplete = true;
            $this->fhdComplete = true;
            return;
        }
        
        if ($res->media->videoResolution == '4k') {
            print "4K version found".PHP_EOL;
            $this->download = false;
            $this->hdComplete = true;
            $this->fhdComplete = true;
            $this->uhdComplete = true;
        }
    }

    /**
     * Method to get the class for web page display
     *
     * @return null|string
     */
    public function getClass(): ?string
    {
        if ($this->uhdComplete) {
            return 'have4k';
        }

        if ($this->fhdComplete) {
            return 'havefhd';
        }

        if ($this->hdComplete) {
            return 'havehd';
        }

        return null;
    }

    /**
     * Method to generate html card
     *
     * @param bool $tsConnected
     *
     * @return string
     */
    public function getHtml(bool $tsConnected): string
    {
        $button = null;
        $class = $this->getClass();
        $encodedTitle = urlencode($this->title);
        $encodedYear = urlencode($this->year);

        if ($class != 'have4k' && $this->betterVersionAvailable() && $tsConnected) {
            $button = "<input 
                type='button' 
                class='download' 
                data-title='{$encodedTitle}' 
                data-year='{$encodedYear}' 
                value='Download' />";
        }
        $ret = "<div class='movie'>
            <a href='/movie/{$encodedTitle}/year/{$this->year}/' ".
                "target='_blank'>
                <img src='{$this->imgUrl}'/>
            </a><br />
            <span class='{$class}'>{$this->title} ({$this->year})</span><br />
            {$button}
        </div>";

        return $ret;
    }

    /**
     * Method to output the detail page
     */
    public function getDetails()
    {
        $encodedTitle = urlencode($this->title);
        $download = null;
        if ($this->highestResolution != '4K') {
            $checked = ($this->download ? 'checked readonly' : null);
            $download = "Download: <input type='checkbox' name='download' id='download' value='1' $checked /><br />";
        }

        print <<<EOF
<!DOCTYPE html>
<html>

<head>
    <title>
        Movie details - {$this}
    </title>

    <link href='/css/style.css' type='text/css' rel='stylesheet' />
    <link href='//code.jquery.com/ui/1.13.1/themes/dot-luv/jquery-ui.css' />
</head>

<body>
    <h1>
        {$this->title}
    </h1>

    <div>
        <img src='{$this->imgUrl}' />
    </div>

    <form method='get'>
        <input type='hidden' name='title' id='title'
            value='{$encodedTitle}' />
        <input type='hidden' name='year' id='year'
            value='{$encodedTitle}' />

        Highest quality:
        {$this->highestVersion()}
        <br />
        Movie URL:
        <a href='{$this->url}'>Link</a>
        <br />
        {$download}

        Highest Resolution Available: {$this->highestVersionAvailable()}

    </form>

    <script src='https://code.jquery.com/jquery-3.6.0.min.js'></script>
    <script src='https://code.jquery.com/ui/1.13.1/jquery-ui.min.js'></script>
    <script src='/js/script.js'></script>
</body>

</html>
EOF;
    }

    /**
     * Method to merge two movies together
     *
     * @param Movie $m
     */
    public function mergeMovie(Movie $m)
    {
        $this->hdTorrent = $m->hdTorrent;
        $this->hdComplete = $m->hdComplete;
        $this->fhdTorrent = $m->fhdTorrent;
        $this->fhdComplete = $m->fhdComplete;
        $this->uhdTorrent = $m->uhdTorrent;
        $this->uhdComplete = $m->uhdComplete;
        $this->download = $m->download;
    }

    /**
     * Turn a database row into an object
     *
     * @param array $sc
     *
     * @return self
     */
    public static function fromDB(array $sc): Movie
    {
        $self = new static($sc['title'], $sc['url'], $sc['imgUrl']);

        $self->year = $sc['year'];
        $self->hdTorrent = $sc['torrent720'];
        $self->hdComplete = (bool) $sc['complete720'];
        $self->fhdTorrent = $sc['torrent1080'];
        $self->fhdComplete = (bool) $sc['complete1080'];
        $self->uhdTorrent = $sc['torrent2160'];
        $self->uhdComplete = (bool) $sc['complete2160'];
        $self->download = (bool) $sc['download'];

        return $self;
    }

    /**
     * Method to return an array for an insert query
     *
     * @return array
     */
    public function insert(): array
    {
        return [
            'title' => $this->title,
            'year' => $this->year,
            'url' => $this->url,
            'imgUrl' => $this->imgUrl,
            'download' => ($this->download ? 1 : 0),
            'torrent720' => $this->hdTorrent,
            'complete720' => ($this->hdComplete ? 1 : 0),
            'torrent1080' => $this->fhdTorrent,
            'complete1080' => ($this->fhdComplete ? 1 : 0),
            'torrent2160' => $this->uhdTorrent,
            'complete2160' => ($this->uhdComplete ? 1 : 0),
        ];
    }

    /**
     * Method to return array for update query
     *
     * @return string
     */
    public function update(): string
    {
        $set = "";
        $arr = [
            'url' => $this->url,
            'imgUrl' => $this->imgUrl,
            'download' => ($this->download ? '1' : '0'),
            'torrent720' => $this->hdTorrent,
            'complete720' => ($this->hdComplete ? '1' : '0'),
            'torrent1080' => $this->fhdTorrent,
            'complete1080' => ($this->fhdComplete ? '1' : '0'),
            'torrent2160' => $this->uhdTorrent,
            'complete2160' => ($this->uhdComplete ? '1' : '0'),
        ];

        foreach ($arr as $key => $val) {
            $val = \SQLite3::escapeString($val);
            $set .= "`{$key}`='{$val}',";
        }

        $set = substr($set, 0, -1);

        return $set;
    }

    /**
     * Method to return an array for replace queries
     *
     * @return array
     */
    public function replace(): array
    {
        return [
            'title' => $this->title,
            'year' => $this->year,
            'url' => $this->url,
            'imgUrl' => $this->imgUrl,
            'download' => ($this->download ? 1 : 0),
            'torrent720' => $this->hdTorrent,
            'complete720' => ($this->hdComplete ? 1 : 0),
            'torrent1080' => $this->fhdTorrent,
            'complete1080' => ($this->fhdComplete ? 1 : 0),
            'torrent2160' => $this->uhdTorrent,
            'complete2160' => ($this->uhdComplete ? 1 : 0),
        ];
    }
}
