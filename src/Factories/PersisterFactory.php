<?php

declare(strict_types=1);

namespace Doppar\Bloom\Factories;

use Phaseolies\Support\Facades\Cache;
use Doppar\Bloom\Utils\PersisterRedisImpl;
use Doppar\Bloom\Contracts\Persister;

class PersisterFactory
{
    /**
     * Redis connection instances cache
     *
     * @var array<string, \Redis>
     */
    private static array $redisInstances = [];

    /**
     * Create a Persister implementation based on the given driver.
     *
     * @param string $driver
     * @param string $connection
     * @param int $capacity
     * @return Persister
     */
    public function make(
        string $driver,
        string $connection,
        int $capacity,
    ): Persister {
        try {
            $redis = $this->getRedisConnection($connection);
        } catch (\InvalidArgumentException $e) {
            throw new \Exception($e->getMessage());
        }

        switch (strtolower($driver)) {
            case config("bloom.default.persistence.driver"):
                return new PersisterRedisImpl($redis, $capacity);
            default:
                throw new \Exception("Unsupported driver: {$driver}");
        }
    }

    /**
     * Get Redis connection instance with proper connection pooling
     *
     * @param string $connectionName
     * @return \Redis
     */
    private function getRedisConnection(string $connectionName): \Redis
    {
        if (isset(self::$redisInstances[$connectionName])) {
            $redis = self::$redisInstances[$connectionName];
            try {
                if ($redis->ping() === true) {
                    return $redis;
                }
            } catch (\Exception $e) {
                unset(self::$redisInstances[$connectionName]);
            }
        }

        $adapter = Cache::getAdapter();
        $reflection = new \ReflectionClass($adapter);
        $property = $reflection->getProperty('redis');
        $property->setAccessible(true);
        $redis = $property->getValue($adapter);

        self::$redisInstances[$connectionName] = $redis;

        return $redis;
    }
}
