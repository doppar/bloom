<?php

declare(strict_types=1);

namespace Doppar\Bloom;

use Doppar\Bloom\Contracts\{Persister};
use Doppar\Bloom\Factories\HasherFactory;
use Doppar\Bloom\Factories\PersisterFactory;
use Doppar\Bloom\Utils\KeySpecificConfig;
use Doppar\Bloom\Utils\Indexer;

final class BloomManager
{
    /**
     * Bloom filter configuration, possibly loaded from app config.
     *
     * @var KeySpecificConfig
     */
    private $bloomConfig;

    /**
     * Factory for creating persistence drivers (e.g., Redis).
     *
     * @var PersisterFactory
     */
    private PersisterFactory $persisterFactory;

    /**
     * Factory for creating hashers (e.g., MD5, Murmur).
     *
     * @var HasherFactory
     */
    private HasherFactory $hasherFactory;

    /**
     * BloomManager constructor.
     *
     * @param PersisterFactory $persisterFactory Factory for persisters.
     * @param HasherFactory $hasherFactory Factory for hashers.
     */
    public function __construct(PersisterFactory $persisterFactory, HasherFactory $hasherFactory)
    {
        $this->bloomConfig = config('bloom');
        $this->persisterFactory = $persisterFactory;
        $this->hasherFactory = $hasherFactory;
    }

    /**
     * Create a BloomFilter instance bound to a specific key.
     *
     * @param string $key
     * @param string|null $keySuffix
     * @return BloomFilter
     * @throws \Doppar\Bloom\Exceptions\InvalidBloomFilterSize
     */
    public function key(string $key, ?string $keySuffix = null): BloomFilter
    {
        $keySpecificConfig = KeySpecificConfig::of($key, $this->bloomConfig);

        $indexer = $this->resolveIndexer($keySpecificConfig);
        $persister = $this->resolvePersister($keySpecificConfig);

        return $this->resolveBloomFilter($key, $keySuffix, $keySpecificConfig, $indexer, $persister);
    }

    /**
     * Build a BloomFilter instance with the given components.
     *
     * @param string $key
     * @param string|null $keySuffix
     * @param KeySpecificConfig $keySpecificConfig
     * @param Indexer $indexer
     * @param Persister $persister
     * @return BloomFilter
     */
    private function resolveBloomFilter(
        string $key,
        ?string $keySuffix,
        KeySpecificConfig $keySpecificConfig,
        Indexer $indexer,
        Persister $persister
    ): BloomFilter {
        $key = $keySuffix ? $key . strval($keySuffix) : $key;

        return new BloomFilter($key, $keySpecificConfig, $indexer, $persister);
    }

    /**
     * Resolve the Indexer, which maps values to bit positions
     *
     * @param KeySpecificConfig $config
     * @return Indexer
     */
    private function resolveIndexer(KeySpecificConfig $config): Indexer
    {
        return new Indexer(
            hasher: $this->hasherFactory->make($config->getHashingAlgorithm())
        );
    }

    /**
     * Resolve the Persister, which manages storing bits
     *
     * @param KeySpecificConfig $config
     * @return Persister
     */
    private function resolvePersister(KeySpecificConfig $config): Persister
    {
        return $this->persisterFactory->make(
            driver: $config->getPersistenceDriver(),
            connection: $config->getPersistenceConnection(),
            capacity: $config->getSize()
        );
    }
}
