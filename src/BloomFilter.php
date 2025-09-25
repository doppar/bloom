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
     * @var Hasher
     */
    private $indexer;

    /**
     * @var Persister
     */
    private $persister;

    /**
     * @var KeySpecificConfig
     */
    private $config;
    /**
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
     * Add items to bloom
     *
     * @param string|integer|float $item
     * @return void
     */
    public function add($item): void
    {
        if (!is_numeric($item) && !is_string($item)) {
            throw new \InvalidArgumentException(
                "Bloom filter items must be string or numeric, got " . gettype($item)
            );
        }

        $this->verifyItem($item);

        $indexes = $this->indexer->getIndexes(
            $this->config->getNumHashes(),
            strval($item),
            $this->config->getSize(),
        );

        $this->persister->setBits($this->key, $indexes);
    }

    /**
     * Check the item exixts or not
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

    public function clear(): void
    {
        $this->persister->clear($this->key);
    }

    /**
     * @return int
     */
    public function getNumHashes(): int
    {
        return $this->config->getNumHashes();
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        return $this->config->getSize();
    }

    /**
     * @param $item
     */
    private function verifyItem($item): void
    {
        if (!is_numeric($item) && !is_string($item)) {
        }
    }
}
