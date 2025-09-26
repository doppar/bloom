<?php

declare(strict_types=1);

namespace Doppar\Bloom\Utils;

use Exception;
use Doppar\Bloom\Contracts\Persister;

final class PersisterRedisImpl implements Persister
{
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
     * @throws Exception
     */
    public function __construct(\Redis $redis, int $capacity)
    {
        if ($capacity > self::getMaxCapacity()) {
            $message = "$capacity, " . self::getMaxCapacity();
            throw new Exception($message);
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
        $pipe = $this->redis->pipeline();

        foreach ($indexes->get() as $index) {
            $pipe->setBit($key, $index, true);
        }

        $pipe->exec();
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
        $pipe = $this->redis->pipeline();

        foreach ($indexes->get() as $index) {
            $pipe->getBit($key, $index);
        }

        $responses = $pipe->exec();

        return new Bits($responses);
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
}
