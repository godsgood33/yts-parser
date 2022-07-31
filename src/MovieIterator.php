<?php

namespace YTS;

use Iterator;

class MovieIterator implements Iterator
{

    /**
     * Position of the iterator
     *
     * @var int
     */
    private int $position = 0;

    /**
     * Collection
     *
     * @var MovieCollection
     */
    private MovieCollection $collection;

    /**
     * Constructor
     *
     * @param MovieCollection $collection
     */
    public function __construct(MovieCollection $collection)
    {
        $this->collection = $collection;
    }

    /**
     * Method to return the current key position
     *
     * @return int
     */
    public function key(): int
    {
        return $this->position;
    }

    /**
     * Method to return the current Movie
     *
     * @return Movie
     */
    public function current(): Movie
    {
        return $this->collection->getData($this->position);
    }

    /**
     * Method to increment the iterator position
     */
    public function next(): void
    {
        $this->position++;
    }

    /**
     * Method to rewind the iterator position
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * Method to check if the current position contains an object
     *
     * @return bool
     */
    public function valid(): bool
    {
        return !is_null($this->collection->getData($this->position));
    }
}
