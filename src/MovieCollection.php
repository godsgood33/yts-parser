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
     * Method to add a Movie to the collection
     *
     * @param Movie $movie
     */
    public function addData(Movie $movie)
    {
        $this->collection[] = $movie;
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
