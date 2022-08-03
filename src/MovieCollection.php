<?php

namespace YTS;

use IteratorAggregate;
use Traversable;

class MovieCollection implements IteratorAggregate
{

    /**
     * Collections array
     *
     * @var array
     */
    private array $collection;

    /**
     * IDs
     *
     * @var array
     */
    private array $ids = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->collection = [];
    }

    /**
     * Method to get the count of items in the collection
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->collection);
    }

    /**
     * Method to get a movie
     *
     * @param Movie $movie
     *
     * @return Movie|null
     */
    public function getMovie(Movie $movie)
    {
        $id = sha1("{$movie->title}-{$movie->year}");
        return $this->movieExists($movie) ? $this->getData($this->ids[$id]) : null;
    }

    /**
     * Method to get a movie by title
     *
     * @param string $title
     * @param int $year
     *
     * @return Movie|null
     */
    public function getMovieByTitle(string $title, int $year)
    {
        $id = sha1("{$title}-{$year}");
        return isset($this->ids[$id]) ? $this->getData($this->ids[$id]) : null;
    }

    /**
     * Method to get data at a position
     *
     * @param int $position
     *
     * @return null|Movie
     */
    public function getData(int $position = 0)
    {
        if (isset($this->collection[$position])) {
            return $this->collection[$position];
        }

        return null;
    }

    /**
     * Method to remove a movie from the collection
     *
     * @param Movie $movie
     *
     * @return bool
     */
    public function removeMovie(Movie $movie): bool
    {
        $id = sha1("{$movie->title}-{$movie->year}");
        if (isset($this->ids[$id])) {
            unset($this->collection[$this->ids[$id]]);
            unset($this->ids[$id]);
            return true;
        }

        return false;
    }

    /**
     * Method to add a Movie to the collection
     *
     * @param Movie $movie
     *
     * @return bool
     */
    public function addData(Movie $movie): bool
    {
        if (!$this->movieExists($movie)) {
            $this->collection[] = $movie;
            $this->setId($movie);
            return true;
        }

        return false;
    }

    /**
     * Method to set the ID for a movie
     *
     * @param Movie $movie
     */
    public function setId(Movie $movie)
    {
        $id = sha1("{$movie->title}-{$movie->year}");
        $currentPosition = count($this->collection) - 1;
        $this->ids[$id] = $currentPosition;
    }

    /**
     * Method to check if the movie is in the collection
     *
     * @param Movie $movie
     *
     * @return bool
     */
    public function movieExists(Movie $movie): bool
    {
        $id = sha1("{$movie->title}-{$movie->year}");
        return isset($this->ids[$id]);
    }

    /**
     * Method to get an iterator for the collection
     *
     * @return MovieIterator
     *
     * {@inheritDoc}
     */
    public function getIterator(): Traversable
    {
        return new MovieIterator($this);
    }
}
