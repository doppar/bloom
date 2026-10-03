<?php

declare(strict_types=1);

namespace Doppar\Bloom\Utils;

use Doppar\Bloom\Exceptions\InvalidBloomFilterSize;
use Doppar\Bloom\Exceptions\InvalidBloomFilterConfiguration;
use Doppar\Bloom\Exceptions\InvalidBloomFilterHashFunctionsNumber;

final class KeySpecificConfig
{
    /**
     * Default ceiling for the filter size, in bits (Redis string limit: 2^32).
     */
    private const DEFAULT_MAX_CAPACITY = 4294967296;

    /**
     * @var integer
     */
    private $numHashes;

    /**
     * @var int
     */
    private $size;

    /**
     * @var string
     */
    private $hashingAlgorithm;

    /**
     * @var string
     */
    private $persistenceDriver;

    /**
     * @var string
     */
    private $persistenceConnection;

    /**
     * @var string
     */
    private $key;

    /**
     * @var string
     */
    private $indexing;

    /**
     * @var string
     */
    private $prefix;

    /**
     * BloomFilterConfig constructor.
     * @param string $key
     * @param int $numHashes
     * @param int $size
     * @param string $hashingAlgorithm
     * @param string $persistenceDriver
     * @param string $persistenceConnection
     * @param string $indexing
     * @param string $prefix
     */
    private function __construct(
        string $key,
        int $numHashes,
        int $size,
        string $hashingAlgorithm,
        string $persistenceDriver,
        string $persistenceConnection,
        string $indexing,
        string $prefix,
    ) {
        $this->key = $key;
        $this->numHashes = $numHashes;
        $this->size = $size;
        $this->hashingAlgorithm = $hashingAlgorithm;
        $this->persistenceDriver = $persistenceDriver;
        $this->persistenceConnection = $persistenceConnection;
        $this->indexing = $indexing;
        $this->prefix = $prefix;
    }

    /**
     * Resolve the settings of one key: the [default] block with the key's own
     * overrides applied on top, so a key may override only what it needs.
     *
     * @param string $key
     * @param mixed $bloomConfig
     * @return KeySpecificConfig
     * @throws InvalidBloomFilterConfiguration
     * @throws InvalidBloomFilterHashFunctionsNumber
     * @throws InvalidBloomFilterSize
     */
    public static function of(string $key, $bloomConfig = []): self
    {
        if (
            !is_array($bloomConfig) ||
            empty($bloomConfig) ||
            !isset($bloomConfig["default"]) ||
            !isset($bloomConfig["keys"])
        ) {
            throw new InvalidBloomFilterConfiguration(
                "Bloom filter configuration file [bloom.php] is empty, invalid or misplaced.",
                1,
            );
        }

        if (!is_array($bloomConfig["default"]) || !is_array($bloomConfig["keys"])) {
            throw new InvalidBloomFilterConfiguration(
                "Bloom filter configuration: [default] and [keys] must both be arrays.",
                1,
            );
        }

        $settings = array_replace_recursive(
            $bloomConfig["default"],
            self::overridesFor($key, $bloomConfig["keys"]),
        );

        $numHashes = self::validatedNumHashes($settings["num_hashes"] ?? null, $key);

        $maxCapacity = $bloomConfig["max_capacity"] ?? self::DEFAULT_MAX_CAPACITY;
        $size = self::validatedSize(
            $settings["size"] ?? null,
            $numHashes,
            is_int($maxCapacity) ? $maxCapacity : self::DEFAULT_MAX_CAPACITY,
            $key,
        );

        $hashingAlgorithm = self::validatedString($settings["hashing_algorithm"] ?? null, "hashing_algorithm", $key);
        $persistenceDriver = self::validatedString($settings["persistence"]["driver"] ?? null, "persistence.driver", $key);
        $persistenceConnection = self::validatedString($settings["persistence"]["connection"] ?? null, "persistence.connection", $key);

        // Absent options keep the historical behaviour so persisted filters stay valid.
        $indexing = $settings["indexing"] ?? Indexer::LEGACY;
        if ($indexing !== Indexer::LEGACY && $indexing !== Indexer::V2) {
            throw new InvalidBloomFilterConfiguration(
                "Bloom filter [{$key}]: indexing must be [legacy] or [v2].",
                1,
            );
        }

        $prefix = $settings["prefix"] ?? "";
        if (!is_string($prefix)) {
            throw new InvalidBloomFilterConfiguration(
                "Bloom filter [{$key}]: prefix must be a string.",
                1,
            );
        }

        return new static(
            $key,
            $numHashes,
            $size,
            $hashingAlgorithm,
            $persistenceDriver,
            $persistenceConnection,
            $indexing,
            $prefix,
        );
    }

    /**
     * Find a key's own overrides. A literal key name wins, then a dotted path
     * (the original lookup) is tried.
     *
     * @param string $key
     * @param array<mixed> $keys
     * @return array<mixed>
     * @throws InvalidBloomFilterConfiguration
     */
    private static function overridesFor(string $key, array $keys): array
    {
        if (array_key_exists($key, $keys)) {
            $found = $keys[$key];
        } else {
            $found = $keys;
            foreach (explode(".", $key) as $segment) {
                if (!is_array($found) || !array_key_exists($segment, $found)) {
                    return [];
                }
                $found = $found[$segment];
            }
        }

        if (!is_array($found)) {
            throw new InvalidBloomFilterConfiguration(
                "Bloom filter [{$key}]: its entry under [keys] must be an array.",
                1,
            );
        }

        return $found;
    }

    /**
     * @param mixed $size
     * @param int $numHashes
     * @param int $maxCapacity
     * @param string $key
     * @return int
     * @throws InvalidBloomFilterSize
     */
    private static function validatedSize($size, int $numHashes, int $maxCapacity, string $key): int
    {
        if (!is_int($size) || $size <= $numHashes) {
            throw new InvalidBloomFilterSize(
                "Bloom filter [{$key}]: invalid size " . self::describe($size)
                    . ", it must be an integer greater than num_hashes ({$numHashes}).",
                1,
            );
        }

        if ($size > $maxCapacity) {
            throw new InvalidBloomFilterSize(
                "Bloom filter [{$key}]: size {$size} exceeds the maximum capacity of {$maxCapacity} bits.",
                1,
            );
        }

        return $size;
    }

    /**
     * @param mixed $num
     * @param string $key
     * @return int
     * @throws InvalidBloomFilterHashFunctionsNumber
     */
    private static function validatedNumHashes($num, string $key): int
    {
        if (!is_int($num) || $num <= 0) {
            throw new InvalidBloomFilterHashFunctionsNumber(
                "Bloom filter [{$key}]: invalid num_hashes " . self::describe($num) . ", it must be a positive integer.",
                1,
            );
        }

        return $num;
    }

    /**
     * @param mixed $value
     * @param string $option
     * @param string $key
     * @return string
     * @throws InvalidBloomFilterConfiguration
     */
    private static function validatedString($value, string $option, string $key): string
    {
        if (!is_string($value) || $value === "") {
            throw new InvalidBloomFilterConfiguration(
                "Bloom filter [{$key}]: invalid {$option} " . self::describe($value) . ", it must be a non-empty string.",
                1,
            );
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @return string
     */
    private static function describe($value): string
    {
        return is_scalar($value) ? "[" . var_export($value, true) . "]" : "[" . get_debug_type($value) . "]";
    }

    /**
     * @return int
     */
    public function getNumHashes(): int
    {
        return $this->numHashes;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return string
     */
    public function getHashingAlgorithm(): string
    {
        return $this->hashingAlgorithm;
    }

    /**
     * @return string
     */
    public function getPersistenceDriver(): string
    {
        return $this->persistenceDriver;
    }

    /**
     * @return string
     */
    public function getPersistenceConnection(): string
    {
        return $this->persistenceConnection;
    }

    /**
     * Index calculation strategy: "legacy" or "v2".
     *
     * @return string
     */
    public function getIndexing(): string
    {
        return $this->indexing;
    }

    /**
     * Prefix prepended to the storage key.
     *
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }
}
