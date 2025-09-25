<?php

namespace Doppar\Bloom\Contracts;

use Doppar\Bloom\Utils\Indexes;
use Doppar\Bloom\Utils\Bits;

/**
 * Interface Persister
 * @package Doppar\Bloom\Contracts
 */
interface Persister
{
    /**
     * Set multiple bits in the underlying storage.
     *
     * @param string $key
     * @param Indexes $multi
     */
    public function setBits(string $key, Indexes $multi): void;

    /**
     * Retrieve the values of multiple bits from storage.
     *
     * @param string $key
     * @param Indexes $multi
     * @return Bits
     */
    public function getBits(string $key, Indexes $multi): Bits;

    /**
     * Clear the bit array associated with the given key.
     *
     * @param string $key
     * @return void
     */
    public function clear(string $key): void;

    /**
     * Return the maximum number of bits this persister can store.
     *
     * @return int
     */
    public function getMaxCapacity(): int;
}