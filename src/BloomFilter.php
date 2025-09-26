<?php

declare(strict_types=1);

namespace Doppar\Bloom;

use Doppar\Bloom\Contracts\Hasher;
use Doppar\Bloom\Contracts\Persister;
use Doppar\Bloom\Utils\KeySpecificConfig;
use Doppar\Bloom\Utils\Indexer;

final class BloomFilter
{
    /**
     * Responsible for hashing items into bit indexes.
     *
     * @var Hasher|Indexer
     */
    private $indexer;

    /**
     * Handles storing and retrieving the bits (e.g., Redis).
     *
     * @var Persister
     */
    private $persister;

    /**
     * Holds config (number of hashes, size of bit array).
     *
     * @var KeySpecificConfig
     */
    private $config;
    /**
     * filter key (used to identify a specific filter in storage).
     *
     * @var string
     */
    private $key;

    /**
     * BloomRedisImpl constructor.
     *
     * @param string $key
     * @param KeySpecificConfig $config
     * @param Indexer $indexer
     * @param Persister $persister
     */
    public function __construct(string $key, KeySpecificConfig $config, Indexer $indexer, Persister $persister)
    {
        $this->config = $config;
        $this->indexer = $indexer;
        $this->persister = $persister;
        $this->key = $key;
    }

    /**
     * Add an item to the Bloom filter
     *
     * @param string|integer|float $item
     * @return void
     */
    public function add($item): void
    {
        $this->verifyItem($item);

        $indexes = $this->indexer->getIndexes(
            $this->config->getNumHashes(),
            strval($item),
            $this->config->getSize(),
        );

        $this->persister->setBits($this->key, $indexes);
    }

    /**
     * Check if an item may exist in the Bloom filter
     *
     * @param string|integer|float $item
     * @return bool
     */
    public function has($item): bool
    {
        $this->verifyItem($item);

        $indexes = $this->indexer->getIndexes(
            $this->config->getNumHashes(),
            strval($item),
            $this->config->getSize(),
        );

        return $this->persister->getBits($this->key, $indexes)->test();
    }

    /**
     * Clear all bits for this Bloom filter (reset it).
     *
     * @return void
     */
    public function clear(): void
    {
        $this->persister->clear($this->key);
    }

    /**
     * Get number of hash functions used.
     *
     * @return int
     */
    public function getNumHashes(): int
    {
        return $this->config->getNumHashes();
    }

    /**
     * Get the size of the Bloom filter bit array.
     *
     * @return int
     */
    public function getSize(): int
    {
        return $this->config->getSize();
    }

    /**
     * Validate item type.
     *
     * @param $item
     * @return void
     */
    private function verifyItem($item): void
    {
        if (!is_numeric($item) && !is_string($item)) {
            throw new \InvalidArgumentException(
                "Bloom filter items must be string or numeric, got " . gettype($item)
            );
        }
    }
}
