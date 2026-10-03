<?php

declare(strict_types=1);

namespace Doppar\Bloom\Tests\Unit;

use Doppar\Bloom\Exceptions\InvalidBloomFilterConfiguration;
use Doppar\Bloom\Utils\Sizing;
use PHPUnit\Framework\TestCase;

class SizingTest extends TestCase
{
    public function testOptimalForOneMillionItemsAtOnePercent(): void
    {
        $this->assertSame(['size' => 9585059, 'num_hashes' => 7], Sizing::optimal(1000000, 0.01));
    }

    public function testOptimalConfigurationActuallyHitsTheTarget(): void
    {
        foreach ([[1000, 0.05], [50000, 0.001], [1000000, 0.01]] as [$n, $p]) {
            $c = Sizing::optimal($n, $p);
            $this->assertLessThanOrEqual($p * 1.05, Sizing::falsePositiveRate($c['size'], $c['num_hashes'], $n));
            $this->assertGreaterThan($c['num_hashes'], $c['size']);
        }
    }

    public function testTinyInputsStillProduceAValidConfiguration(): void
    {
        $c = Sizing::optimal(1, 0.99);

        $this->assertGreaterThanOrEqual(1, $c['num_hashes']);
        $this->assertGreaterThan($c['num_hashes'], $c['size']);
    }

    public function testInvalidInputsAreRejected(): void
    {
        foreach ([[0, 0.01], [-5, 0.01], [10, 0.0], [10, 1.0], [10, -0.1], [10, 1.5]] as [$n, $p]) {
            try {
                Sizing::optimal($n, $p);
                $this->fail("expected an exception for $n, $p");
            } catch (InvalidBloomFilterConfiguration) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testEstimateItemsRoundTrips(): void
    {
        $size = 1000000;
        $k = 5;
        $n = 50000;
        $bitsSet = (int) round($size * (1 - exp(-$k * $n / $size)));

        $this->assertEqualsWithDelta($n, Sizing::estimateItems($size, $k, $bitsSet), $n * 0.01);
        $this->assertSame(0, Sizing::estimateItems($size, $k, 0));
        $this->assertSame(PHP_INT_MAX, Sizing::estimateItems($size, $k, $size));
    }

    public function testFalsePositiveRateEdgeCases(): void
    {
        $this->assertSame(0.0, Sizing::falsePositiveRate(100, 3, 0));
        $this->assertSame(0.0, Sizing::falsePositiveRate(0, 3, 10));
    }
}
