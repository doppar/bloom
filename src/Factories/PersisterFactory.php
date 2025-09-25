<?php

declare(strict_types=1);

namespace Doppar\Bloom\Factories;

use Doppar\Bloom\Contracts\Persister;
use Doppar\Bloom\Utils\PersisterRedisImpl;

class PersisterFactory
{
    /**
     * Create a Hasher implementation based on the given algorithm name.
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
            $conn = $this->getRedisConnection($connection);
        } catch (\InvalidArgumentException $e) {
            throw new \Exception($e->getMessage());
        }

        switch (strtolower($driver)) {
            case config("bloom.default.persistence.driver"):
                return new PersisterRedisImpl($conn, $capacity);
            default:
                throw new \Exception($driver);
        }
    }

    /**
     * Get Redis connection instance
     *
     * @param string $store
     * @return \Redis|null
     */
    private function getRedisConnection(?string $store = null): ?\Redis
    {
        $storeConfig = config("caching.stores.redis");

        if (($storeConfig["driver"] ?? null) !== "redis") {
            return null;
        }

        $redis = new \Redis();
        $dsn = $storeConfig["connection"] ?? "redis://127.0.0.1:6379";
        $parsed = parse_url($dsn);

        $host = $parsed["host"] ?? "127.0.0.1";
        $port = $parsed["port"] ?? 6379;
        $password = $parsed["pass"] ?? null;
        $database = isset($parsed["path"])
            ? (int) substr($parsed["path"], 1)
            : 0;

        if (!$redis->connect($host, $port, 2.5)) {
            throw new \RuntimeException(
                "Could not connect to Redis at {$host}:{$port}",
            );
        }

        if ($password !== null) {
            $redis->auth($password);
        }

        if ($database > 0) {
            $redis->select($database);
        }

        foreach ($storeConfig["options"] ?? [] as $name => $value) {
            $optionConstant = $this->getRedisOptionConstant($name);
            if ($optionConstant !== null) {
                $redis->setOption($optionConstant, $value);
            }
        }

        return $redis;
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
        ];

        return $constants[strtolower($name)] ?? null;
    }
}
