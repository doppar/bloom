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
     * Set the bits of many items in one round trip.
     *
     * @param string $key
     * @param array<int, Indexes> $items One Indexes per item
     * @return array<int, bool> Per item, in order: true when every bit was already set
     *                          (the item was probably present before this call)
     */
    public function setBitsMany(string $key, array $items): array;

    /**
     * Read the bits of many items in one round trip.
     *
     * @param string $key
     * @param array<int, Indexes> $items One Indexes per item
     * @return array<int, Bits> One Bits per item, in order
     */
    public function getBitsMany(string $key, array $items): array;

    /**
     * Count the bits that are set.
     *
     * @param string $key
     * @return int
     */
    public function countBits(string $key): int;

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