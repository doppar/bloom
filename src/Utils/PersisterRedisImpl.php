<?php

declare(strict_types=1);

namespace Doppar\Bloom\Utils;

use Doppar\Bloom\Contracts\Persister;
use Doppar\Bloom\Exceptions\BloomPersistenceException;
use Doppar\Bloom\Exceptions\InvalidBloomFilterSize;

final class PersisterRedisImpl implements Persister
{
    /**
     * Items sent per pipeline, to bound the memory used by very large batches.
     */
    private const BATCH_ITEMS = 500;

    /**
     * @var \Redis
     */
    private $redis;

    /**
     * @var int
     */
    private $capacity;

    /**
     * PersisterRedisImpl constructor.
     * @param \Redis $redis
     * @param int $capacity
     * @throws InvalidBloomFilterSize
     */
    public function __construct(\Redis $redis, int $capacity)
    {
        $max = $this->getMaxCapacity();

        if ($capacity > $max) {
            throw new InvalidBloomFilterSize(
                "Bloom filter size {$capacity} exceeds the maximum capacity of {$max} bits.",
                1,
            );
        }

        $this->redis = $redis;
        $this->capacity = $capacity;
    }

    /**
     * Set bits
     *
     * @param string $key
     * @param Indexes $indexes
     * @return void
     */
    public function setBits(string $key, Indexes $indexes): void
    {
        $this->setBitsMany($key, [$indexes]);
    }

    /**
     * Get bits
     *
     * @param string $key
     * @param Indexes $indexes
     * @return Bits
     */
    public function getBits(string $key, Indexes $indexes): Bits
    {
        return $this->getBitsMany($key, [$indexes])[0];
    }

    /**
     * Set the bits of many items, reporting which were already present.
     *
     * @param string $key
     * @param array<int, Indexes> $items
     * @return array<int, bool>
     * @throws BloomPersistenceException
     */
    public function setBitsMany(string $key, array $items): array
    {
        $result = [];

        foreach (array_chunk(array_values($items), self::BATCH_ITEMS) as $chunk) {
            $pipe = $this->redis->pipeline();

            foreach ($chunk as $indexes) {
                foreach ($indexes->get() as $index) {
                    $pipe->setBit($key, $index, true);
                }
            }

            $responses = $this->responses($pipe->exec(), $chunk, "SETBIT", $key);

            foreach ($this->groupByItem($responses, $chunk) as $previous) {
                // SETBIT returns the bit's previous value: the item was probably
                // present when none of its bits had to be flipped.
                $result[] = !in_array(0, $previous, true);
            }
        }

        return $result;
    }

    /**
     * Get the bits of many items.
     *
     * @param string $key
     * @param array<int, Indexes> $items
     * @return array<int, Bits>
     * @throws BloomPersistenceException
     */
    public function getBitsMany(string $key, array $items): array
    {
        $result = [];

        foreach (array_chunk(array_values($items), self::BATCH_ITEMS) as $chunk) {
            $pipe = $this->redis->pipeline();

            foreach ($chunk as $indexes) {
                foreach ($indexes->get() as $index) {
                    $pipe->getBit($key, $index);
                }
            }

            $responses = $this->responses($pipe->exec(), $chunk, "GETBIT", $key);

            foreach ($this->groupByItem($responses, $chunk) as $values) {
                $result[] = new Bits($values);
            }
        }

        return $result;
    }

    /**
     * Count the bits that are set
     *
     * @param string $key
     * @return int
     * @throws BloomPersistenceException
     */
    public function countBits(string $key): int
    {
        $count = $this->redis->bitCount($key);

        if (!is_int($count)) {
            throw new BloomPersistenceException("Redis BITCOUNT failed for Bloom filter key [{$key}].");
        }

        return $count;
    }

    /**
     * Clear redis key
     *
     * @param string $key
     * @return void
     */
    public function clear(string $key): void
    {
        $this->redis->del($key);
    }

    /**
     * @return int
     */
    public function getMaxCapacity(): int
    {
        $maxCapacity = (int) config('bloom.max_capacity', '4294967296');

        return $maxCapacity;
    }

    /**
     * Check a pipeline answered every command with a 0/1 reply. A failed
     * command must never be read as "bit not set" (a false negative).
     *
     * @param mixed $responses
     * @param array<int, Indexes> $chunk
     * @param string $command
     * @param string $key
     * @return array<int, int>
     * @throws BloomPersistenceException
     */
    private function responses(mixed $responses, array $chunk, string $command, string $key): array
    {
        $expected = array_sum(array_map(static fn(Indexes $indexes): int => count($indexes), $chunk));

        if (!is_array($responses) || count($responses) !== $expected) {
            throw new BloomPersistenceException("Redis pipeline failed for Bloom filter key [{$key}].");
        }

        $responses = array_values($responses);

        foreach ($responses as $response) {
            if ($response !== 0 && $response !== 1) {
                throw new BloomPersistenceException("Redis {$command} failed for Bloom filter key [{$key}].");
            }
        }

        return $responses;
    }

    /**
     * Split a flat list of replies back into one list per item.
     *
     * @param array<int, int> $responses
     * @param array<int, Indexes> $chunk
     * @return array<int, array<int, int>>
     */
    private function groupByItem(array $responses, array $chunk): array
    {
        $grouped = [];
        $offset = 0;

        foreach ($chunk as $indexes) {
            $length = count($indexes);
            $grouped[] = array_slice($responses, $offset, $length);
            $offset += $length;
        }

        return $grouped;
    }
}
