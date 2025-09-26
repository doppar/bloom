<?php

declare(strict_types=1);

namespace Doppar\Bloom\Factories;

use Doppar\Bloom\Contracts\Persister;
use Doppar\Bloom\Utils\PersisterRedisImpl;

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

        $config = $this->getRedisConfig($connectionName);
        $redis = $this->createRedisConnection($config);

        self::$redisInstances[$connectionName] = $redis;

        return $redis;
    }

    /**
     * Get Redis configuration from config files
     *
     * @param string $connectionName
     * @return array
     */
    private function getRedisConfig(string $connectionName): array
    {
        $storeConfig = config("caching.stores.redis");

        if (($storeConfig["driver"] ?? null) !== "redis") {
            throw new \InvalidArgumentException(
                "Redis driver not configured for connection: {$connectionName}"
            );
        }

        $dsn = $storeConfig["connection"] ?? $storeConfig["host"] ?? "redis://127.0.0.1:6379";
        $parsed = parse_url($dsn);

        return [
            'host' => $parsed["host"] ?? "127.0.0.1",
            'port' => $parsed["port"] ?? 6379,
            'password' => $parsed["pass"] ?? $storeConfig["password"] ?? null,
            'database' => isset($parsed["path"]) ? (int) substr($parsed["path"], 1) : ($storeConfig["database"] ?? 0),
            'timeout' => $storeConfig["timeout"] ?? 2.5,
            'read_timeout' => $storeConfig["read_timeout"] ?? 2.5,
            'persistent' => $storeConfig["persistent"] ?? false,
            'prefix' => $storeConfig["prefix"] ?? null,
            'options' => $storeConfig["options"] ?? [],
        ];
    }

    /**
     * Create a new Redis connection with proper error handling
     *
     * @param array $config
     * @return \Redis
     */
    private function createRedisConnection(array $config): \Redis
    {
        $redis = new \Redis();

        try {
            // Use persistent connection if configured
            if ($config['persistent']) {
                $connected = $redis->pconnect(
                    $config['host'],
                    $config['port'],
                    $config['timeout'],
                    $config['persistent'] === true ? null : $config['persistent']
                );
            } else {
                $connected = $redis->connect(
                    $config['host'],
                    $config['port'],
                    $config['timeout']
                );
            }

            if (!$connected) {
                throw new \RuntimeException(
                    "Could not connect to Redis at {$config['host']}:{$config['port']}"
                );
            }

            // Authenticate if password is set
            if ($config['password'] !== null) {
                if (!$redis->auth($config['password'])) {
                    throw new \RuntimeException("Redis authentication failed");
                }
            }

            // Select database if specified
            if ($config['database'] > 0) {
                if (!$redis->select($config['database'])) {
                    throw new \RuntimeException("Could not select Redis database");
                }
            }

            // Set read timeout
            $redis->setOption(\Redis::OPT_READ_TIMEOUT, $config['read_timeout']);

            // Set prefix if configured
            if ($config['prefix'] !== null) {
                $redis->setOption(\Redis::OPT_PREFIX, $config['prefix']);
            }

            // Apply additional options
            foreach ($config['options'] as $name => $value) {
                $optionConstant = $this->getRedisOptionConstant($name);
                if ($optionConstant !== null) {
                    $redis->setOption($optionConstant, $value);
                }
            }

            // Test connection
            if ($redis->ping() !== true) {
                throw new \RuntimeException("Redis connection test failed");
            }

            return $redis;
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Redis connection failed: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Close all Redis connections (useful for long-running processes)
     *
     * @return void
     */
    public static function closeConnections(): void
    {
        foreach (self::$redisInstances as $connectionName => $redis) {
            try {
                $redis->close();
            } catch (\Exception $e) {
            }
        }

        self::$redisInstances = [];
    }

    /**
     * Get connection statistics for monitoring
     *
     * @return array
     */
    public static function getConnectionStats(): array
    {
        $stats = [];

        foreach (self::$redisInstances as $name => $redis) {
            $stats[$name] = [
                'connected' => $redis->isConnected(),
                'last_error' => $redis->getLastError(),
            ];
        }

        return $stats;
    }

    /**
     * Map string option names to Redis constants
     *
     * @param string $name
     * @return int|null
     */
    protected function getRedisOptionConstant(string $name): ?int
    {
        $constants = [
            "serializer" => \Redis::OPT_SERIALIZER,
            "prefix" => \Redis::OPT_PREFIX,
            "read_timeout" => \Redis::OPT_READ_TIMEOUT,
            "scan" => \Redis::OPT_SCAN,
            "compression" => \Redis::OPT_COMPRESSION,
            "compression_level" => \Redis::OPT_COMPRESSION_LEVEL,
            "tcp_keepalive" => \Redis::OPT_TCP_KEEPALIVE,
        ];

        return $constants[strtolower($name)] ?? null;
    }
}
