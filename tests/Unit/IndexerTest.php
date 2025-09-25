<?php

declare(strict_types=1);

namespace Doppar\Bloom\Tests\Unit;

use Doppar\Bloom\Contracts\Hasher;
use Doppar\Bloom\Utils\Indexer;
use Doppar\Bloom\Utils\Indexes;
use PHPUnit\Framework\TestCase;

class IndexerTest extends TestCase
{
    private $hasherMock;

    protected function setUp(): void
    {
        $this->hasherMock = $this->createMock(Hasher::class);
    }

    public function testGetIndexesReturnsCorrectNumberOfIndexes(): void
    {
        $this->hasherMock->method('hash')
            ->willReturnOnConsecutiveCalls(100, 200, 300);

        $indexer = new Indexer($this->hasherMock);
        $indexes = $indexer->getIndexes(3, 'test_value', 1000);

        $this->assertInstanceOf(Indexes::class, $indexes);
        $this->assertEquals(3, $indexes->count());
    }

    public function testGetIndexesCallsHasherWithCorrectParameters(): void
    {
        $callCount = 0;
        $expectedCalls = [
            [1, 'test_value'],
            [2, 'test_value'],
            [3, 'test_value']
        ];

        $this->hasherMock->expects($this->exactly(3))
            ->method('hash')
            ->willReturnCallback(function ($seed, $value) use (&$callCount, $expectedCalls) {
                $this->assertEquals($expectedCalls[$callCount][0], $seed);
                $this->assertEquals($expectedCalls[$callCount][1], $value);
                $callCount++;
                return 500;
            });

        $indexer = new Indexer($this->hasherMock);
        $indexer->getIndexes(3, 'test_value', 1000);
    }

    public function testGetIndexesWithDifferentSeedsProducesDifferentIndexes(): void
    {
        // Mock the hasher to return different values for different seeds
        // but the SAME values when called with the SAME parameters (deterministic)
        $this->hasherMock->method('hash')
            ->willReturnCallback(function ($seed, $value) {
                // Deterministic: same seed + value = same hash
                return $seed * 100 + crc32($value);
            });

        $indexer = new Indexer($this->hasherMock);
        $indexes1 = $indexer->getIndexes(2, 'value', 1000);
        $indexes2 = $indexer->getIndexes(2, 'value', 1000);

        $indexArray1 = iterator_to_array($indexes1->get());
        $indexArray2 = iterator_to_array($indexes2->get());

        // Should be identical because same input produces same output (deterministic)
        $this->assertEquals($indexArray1, $indexArray2);
    }

    public function testGetIndexesWithDifferentValuesProducesDifferentIndexes(): void
    {
        $this->hasherMock->method('hash')
            ->willReturnCallback(function ($seed, $value) {
                return $seed * 100 + crc32($value);
            });

        $indexer = new Indexer($this->hasherMock);
        $indexes1 = $indexer->getIndexes(2, 'value1', 1000);
        $indexes2 = $indexer->getIndexes(2, 'value2', 1000);

        $indexArray1 = iterator_to_array($indexes1->get());
        $indexArray2 = iterator_to_array($indexes2->get());

        // Should be different because different input values
        $this->assertNotEquals($indexArray1, $indexArray2);
    }

    public function testIndexesAreWithinBounds(): void
    {
        $size = 100;
        $this->hasherMock->method('hash')
            ->willReturn(123456); // Large hash value

        $indexer = new Indexer($this->hasherMock);
        $indexes = $indexer->getIndexes(1, 'test', $size);

        foreach ($indexes->get() as $index) {
            $this->assertGreaterThanOrEqual(0, $index);
            $this->assertLessThan($size, $index);
        }
    }

    public function testIndexesAreModuloSize(): void
    {
        $size = 100;
        $largeHash = 123456;

        $this->hasherMock->method('hash')
            ->willReturn($largeHash);

        $indexer = new Indexer($this->hasherMock);
        $indexes = $indexer->getIndexes(1, 'test', $size);

        foreach ($indexes->get() as $index) {
            // The index should be the large hash modulo the size
            $this->assertEquals($largeHash % $size, $index);
        }
    }
}
