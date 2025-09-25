<?php

declare(strict_types=1);

namespace Doppar\Bloom\Utils;

use Countable;

class Indexes implements Countable
{
    /**
     * Array of integer indexes representing bit positions
     *
     * @var array<int>
     */
    private $indexes;

    /**
     * Indexes constructor.
     *
     * @param array<int> $indexes
     */
    public function __construct(array $indexes = [])
    {
        $this->indexes = $indexes;
    }

    /**
     * Add a new index to the collection.
     *
     * @param int $index
     */
    public function push(int $index): void
    {
        $this->indexes[] = $index;
    }

    /**
     * Get an iterator for all stored indexes.
     *
     * @return iterable<int> Generator that yields each index
     */
    public function get(): iterable
    {
        foreach ($this->indexes as $index) {
            yield $index;
        }
    }

    /**
     * Count elements of an object
     * @link https://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     * @since 5.1.0
     */
    public function count(): int
    {
        return count($this->indexes);
    }
}
