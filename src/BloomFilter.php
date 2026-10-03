<?php

declare(strict_types=1);

namespace Doppar\Bloom;

use Doppar\Bloom\Contracts\Hasher;
use Doppar\Bloom\Contracts\Persister;
use Doppar\Bloom\Utils\Indexes;
use Doppar\Bloom\Utils\KeySpecificConfig;
use Doppar\Bloom\Utils\Indexer;
use Doppar\Bloom\Utils\Sizing;

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
     * @return bool True when the item was probably already present, so one call
     *              both adds the item and tells you whether it was new
     */
    public function add($item): bool
    {
        return $this->addMany([$item])[0];
    }

    /**
     * Add many items in as few round trips as possible
     *
     * @param iterable<string|integer|float> $items
     * @return array<int, bool>
     */
    public function addMany(iterable $items): array
    {
        $indexes = $this->indexesForMany($items);

        if ($indexes === []) {
            return [];
        }

        return $this->persister->setBitsMany($this->key, $indexes);
    }

    /**
     * Check if an item may exist in the Bloom filter
     *
     * @param string|integer|float $item
     * @return bool
     */
    public function has($item): bool
    {
        return $this->hasMany([$item])[0];
    }

    /**
     * Check many items in as few round trips as possible
     *
     * @param iterable<string|integer|float> $items
     * @return array<int, bool>
     */
    public function hasMany(iterable $items): array
    {
        $indexes = $this->indexesForMany($items);

        if ($indexes === []) {
            return [];
        }

        return array_map(
            static fn($bits): bool => $bits->test(),
            $this->persister->getBitsMany($this->key, $indexes),
        );
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
     * Report how full the filter is.
     *
     * @return array{size: int, num_hashes: int, bits_set: int, fill_ratio: float,
     *               estimated_items: int, estimated_false_positive_rate: float}
     */
    public function stats(): array
    {
        $size = $this->config->getSize();
        $numHashes = $this->config->getNumHashes();
        $bitsSet = $this->persister->countBits($this->key);
        $fillRatio = (float) ($bitsSet / $size);

        return [
            'size' => $size,
            'num_hashes' => $numHashes,
            'bits_set' => $bitsSet,
            'fill_ratio' => $fillRatio,
            'estimated_items' => Sizing::estimateItems($size, $numHashes, $bitsSet),
            // The chance that an absent item finds all of its bits set
            'estimated_false_positive_rate' => $fillRatio ** $numHashes,
        ];
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
     * Validate every item, then compute the bit positions of each.
     *
     * @param iterable<mixed> $items
     * @return array<int, Indexes>
     */
    private function indexesForMany(iterable $items): array
    {
        $indexes = [];

        foreach ($items as $item) {
            $this->verifyItem($item);

            $indexes[] = $this->indexer->getIndexes(
                $this->config->getNumHashes(),
                strval($item),
                $this->config->getSize(),
            );
        }

        return $indexes;
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
