<?php

declare(strict_types=1);

namespace Doppar\Bloom\Utils;

use Doppar\Bloom\Contracts\Hasher;

class Indexer
{
    /**
     * Original index calculation, kept as the default so filters that were
     * persisted by earlier versions keep answering correctly.
     *
     * Hashes of 2^31 or more are doubled, which leaves half of them on even
     * bit positions only and roughly doubles the false positive rate.
     */
    public const LEGACY = 'legacy';

    /**
     * Uniform index calculation: the 32-bit hash modulo the filter size.
     * Use it for new filters.
     */
    public const V2 = 'v2';

    /**
     * @var Hasher
     */
    private $hasher;

    /**
     * @var string
     */
    private string $strategy;

    /**
     * Indexer constructor.
     *
     * @param Hasher $hasher
     * @param string $strategy One of Indexer::LEGACY or Indexer::V2
     * @throws \InvalidArgumentException
     */
    public function __construct(Hasher $hasher, string $strategy = self::LEGACY)
    {
        if ($strategy !== self::LEGACY && $strategy !== self::V2) {
            throw new \InvalidArgumentException(
                "Unknown Bloom indexing strategy [{$strategy}]. Supported: legacy, v2."
            );
        }

        $this->hasher = $hasher;
        $this->strategy = $strategy;
    }

    /**
     * @param int $numHashes
     * @param string $value
     * @param int $size
     * @return Indexes
     */
    public function getIndexes(
        int $numHashes,
        string $value,
        int $size,
    ): Indexes {
        $indexes = new Indexes();

        for ($i = 1; $i <= $numHashes; $i++) {
            $indexes->push($this->getIndex($i, $value, $size));
        }

        return $indexes;
    }

    /**
     * @param int $seed
     * @param string $value
     * @param int $size
     * @return int
     */
    private function getIndex(int $seed, string $value, int $size): int
    {
        $hash = $this->hasher->hash($seed, $value);

        if ($this->strategy === self::V2) {
            return ($hash & 0xFFFFFFFF) % $size;
        }

        // Strip the two's complement negative bit
        $bitIndex = $hash & (-1 >> 1);

        // If the result has a 1 as its leading bit,
        // multiply our index by 2 to compensate.
        if ($hash >> 31 === 1) {
            $bitIndex *= 2;
        }

        return $bitIndex % $size;
    }
}
