<?php

declare(strict_types=1);

namespace Doppar\Bloom\Tests\Unit;

use Doppar\Bloom\Utils\HasherMD5Impl;
use Doppar\Bloom\Utils\HasherMurmurImpl;
use Doppar\Bloom\Utils\Indexer;
use Doppar\Bloom\Contracts\Hasher;
use PHPUnit\Framework\TestCase;

class IndexerStrategyTest extends TestCase
{
    /**
     * The calculation exactly as it was before strategies existed. Persisted
     * filters depend on it, so the default must never drift from it.
     */
    private function originalIndex(Hasher $hasher, int $seed, string $value, int $size): int
    {
        $hash = $hasher->hash($seed, $value);
        $bitIndex = $hash & (-1 >> 1);

        if ($hash >> 31 === 1) {
            $bitIndex *= 2;
        }

        return $bitIndex % $size;
    }

    /** @return array<string, array{Hasher}> */
    public static function hashers(): array
    {
        return ['md5' => [new HasherMD5Impl()], 'murmur' => [new HasherMurmurImpl()]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('hashers')]
    public function testDefaultStrategyIsBitForBitTheOriginalCalculation(Hasher $hasher): void
    {
        foreach ([1000, 100000000, 4294967296, 7919] as $size) {
            $indexer = new Indexer($hasher);
            $explicit = new Indexer($hasher, Indexer::LEGACY);

            for ($i = 0; $i < 1500; $i++) {
                $expected = [];
                for ($seed = 1; $seed <= 5; $seed++) {
                    $expected[] = $this->originalIndex($hasher, $seed, "item-$i", $size);
                }

                $this->assertSame($expected, iterator_to_array($indexer->getIndexes(5, "item-$i", $size)->get(), false));
                $this->assertSame($expected, iterator_to_array($explicit->getIndexes(5, "item-$i", $size)->get(), false));
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('hashers')]
    public function testV2IndexesStayInRangeAndAreSpreadEvenly(Hasher $hasher): void
    {
        $size = 100000000;
        $indexer = new Indexer($hasher, Indexer::V2);
        $even = 0;
        $total = 0;

        for ($i = 0; $i < 5000; $i++) {
            foreach ($indexer->getIndexes(5, "user$i@example.com", $size)->get() as $index) {
                $this->assertGreaterThanOrEqual(0, $index);
                $this->assertLessThan($size, $index);
                $even += $index % 2 === 0 ? 1 : 0;
                $total++;
            }
        }

        $this->assertEqualsWithDelta(0.5, $even / $total, 0.02, 'legacy puts about 75% of positions on even bits');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('hashers')]
    public function testV2FalsePositiveRateMatchesTheory(Hasher $hasher): void
    {
        [$size, $k, $n, $probes] = [400000, 7, 40000, 60000];
        $indexer = new Indexer($hasher, Indexer::V2);
        $bits = [];

        for ($i = 0; $i < $n; $i++) {
            foreach ($indexer->getIndexes($k, "in$i", $size)->get() as $b) {
                $bits[$b] = true;
            }
        }

        $false = 0;
        for ($i = 0; $i < $probes; $i++) {
            $all = true;
            foreach ($indexer->getIndexes($k, "out$i", $size)->get() as $b) {
                if (!isset($bits[$b])) {
                    $all = false;
                    break;
                }
            }
            $false += $all ? 1 : 0;
        }

        $theory = (1 - exp(-$k * $n / $size)) ** $k;
        $this->assertLessThan($theory * 1.35, $false / $probes, 'measured rate must not exceed theory by much');
        $this->assertGreaterThan($theory * 0.65, $false / $probes);
    }

    public function testNoFalseNegativesWithEitherStrategy(): void
    {
        foreach ([Indexer::LEGACY, Indexer::V2] as $strategy) {
            $indexer = new Indexer(new HasherMurmurImpl(), $strategy);
            $set = [];
            for ($i = 0; $i < 2000; $i++) {
                foreach ($indexer->getIndexes(4, "k$i", 50000)->get() as $b) {
                    $set[$b] = true;
                }
            }
            for ($i = 0; $i < 2000; $i++) {
                foreach ($indexer->getIndexes(4, "k$i", 50000)->get() as $b) {
                    $this->assertTrue($set[$b]);
                }
            }
        }
    }

    public function testUnknownStrategyIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Indexer(new HasherMD5Impl(), 'v9');
    }
}
