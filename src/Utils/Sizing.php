<?php

declare(strict_types=1);

namespace Doppar\Bloom\Utils;

use Doppar\Bloom\Exceptions\InvalidBloomFilterConfiguration;

/**
 * Bloom filter sizing maths.
 *
 * The formulas assume uniformly distributed bit positions, which is what the
 * "v2" indexing strategy provides.
 */
final class Sizing
{
    /**
     * Work out the bit-array size and hash count for an expected load
     *
     * @param int $expectedItems
     * @param float $falsePositiveRate A value between 0 and 1 (exclusive), e.g. 0.01 for 1%
     * @return array{size: int, num_hashes: int}
     * @throws InvalidBloomFilterConfiguration
     */
    public static function optimal(int $expectedItems, float $falsePositiveRate): array
    {
        if ($expectedItems < 1) {
            throw new InvalidBloomFilterConfiguration('Expected item count must be at least 1.');
        }

        if ($falsePositiveRate <= 0.0 || $falsePositiveRate >= 1.0) {
            throw new InvalidBloomFilterConfiguration('False positive rate must be greater than 0 and less than 1.');
        }

        $size = (int) ceil(-$expectedItems * log($falsePositiveRate) / (M_LN2 ** 2));
        $numHashes = max(1, (int) round(($size / $expectedItems) * M_LN2));

        // The size must stay above the hash count to be a valid configuration.
        $size = max($size, $numHashes + 1);

        return ['size' => $size, 'num_hashes' => $numHashes];
    }

    /**
     * Expected false positive rate after inserting a number of items
     *
     * @param int $size
     * @param int $numHashes
     * @param int $items
     * @return float
     */
    public static function falsePositiveRate(int $size, int $numHashes, int $items): float
    {
        if ($size < 1 || $numHashes < 1 || $items < 1) {
            return 0.0;
        }

        return (1 - exp(-$numHashes * $items / $size)) ** $numHashes;
    }

    /**
     * Estimate how many distinct items were added from the number of set bits
     *
     * @param int $size
     * @param int $numHashes
     * @param int $bitsSet
     * @return int
     */
    public static function estimateItems(int $size, int $numHashes, int $bitsSet): int
    {
        if ($size < 1 || $numHashes < 1 || $bitsSet < 1) {
            return 0;
        }

        // A completely full filter cannot be estimated: report the saturation point.
        if ($bitsSet >= $size) {
            return PHP_INT_MAX;
        }

        return (int) round(-($size / $numHashes) * log(1 - $bitsSet / $size));
    }
}
