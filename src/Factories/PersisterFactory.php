<?php

declare(strict_types=1);

namespace Doppar\Bloom\Factories;

use Phaseolies\Support\Facades\Cache;
use Doppar\Bloom\Utils\PersisterRedisImpl;
use Doppar\Bloom\Contracts\Persister;
use Doppar\Bloom\Exceptions\BloomPersistenceException;

class PersisterFactory
{
    /**
     * Seconds a connection is trusted before it is pinged again
     */
    private const HEALTH_CHECK_SECONDS = 5;

    /**
     * Redis connections already resolved, with the time they were last verified
     *
     * @var array<string, array{redis: \Redis, checked: int}>
     */
    private static array $redisInstances = [];

    /**
     * Create a Persister implementation based on the given driver.
     *
     * @param string $driver
     * @param string $connection
     * @param int $capacity
     * @return Persister
     * @throws BloomPersistenceException
     */
    public function make(
        string $driver,
        string $connection,
        int $capacity,
    ): Persister {
        if (strtolower($driver) !== 'redis') {
            throw new BloomPersistenceException(
                "Unsupported Bloom persistence driver [{$driver}]. Supported drivers: redis."
            );
        }

        return new PersisterRedisImpl($this->getRedisConnection($connection), $capacity);
    }

    /**
     * Get the Redis connection, reusing it and only pinging when it has not
     * been checked recently (a ping on every call costs a round trip).
     *
     * @param string $connectionName
     * @return \Redis
     * @throws BloomPersistenceException
     */
    private function getRedisConnection(string $connectionName): \Redis
    {
        $entry = self::$redisInstances[$connectionName] ?? null;

        if ($entry !== null) {
            if (time() - $entry['checked'] < self::HEALTH_CHECK_SECONDS) {
                return $entry['redis'];
            }

            try {
                if ($entry['redis']->ping()) {
                    self::$redisInstances[$connectionName]['checked'] = time();

                    return $entry['redis'];
                }
            } catch (\Throwable) {
                // fall through and resolve a fresh connection
            }

            unset(self::$redisInstances[$connectionName]);
        }

        $redis = $this->extractRedis();

        self::$redisInstances[$connectionName] = ['redis' => $redis, 'checked' => time()];

        return $redis;
    }

    /**
     * Read the phpredis client held by the default cache store.
     *
     * @return \Redis
     * @throws BloomPersistenceException
     */
    private function extractRedis(): \Redis
    {
        $hint = ' Doppar Bloom stores its bits in the Redis connection of the default cache store: '
            . 'set CACHE_DRIVER = "redis" and install the phpredis extension.';

        try {
            $adapter = Cache::getAdapter();
        } catch (\Throwable $e) {
            throw new BloomPersistenceException('Could not resolve the cache store.' . $hint, 0, $e);
        }

        // The client is a private property: look through the adapter's parents too.
        for ($class = new \ReflectionClass($adapter); $class !== false; $class = $class->getParentClass()) {
            if ($class->hasProperty('redis')) {
                $property = $class->getProperty('redis');

                if ($property->isInitialized($adapter) && $property->getValue($adapter) instanceof \Redis) {
                    return $property->getValue($adapter);
                }
            }
        }

        throw new BloomPersistenceException(
            'The default cache store (' . get_class($adapter) . ') does not use a phpredis connection.' . $hint
        );
    }
}
