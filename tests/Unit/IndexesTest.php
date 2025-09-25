<?php

declare(strict_types=1);

namespace Doppar\Bloom\Tests\Unit;

use Doppar\Bloom\Utils\Indexes;
use PHPUnit\Framework\TestCase;

class IndexesTest extends TestCase
{
    public function testConstructorWithInitialIndexes(): void
    {
        $indexes = new Indexes([1, 2, 3]);
        $this->assertEquals(3, $indexes->count());
    }

    public function testConstructorWithEmptyArray(): void
    {
        $indexes = new Indexes();
        $this->assertEquals(0, $indexes->count());
    }

    public function testPushAddsNewIndex(): void
    {
        $indexes = new Indexes();
        $indexes->push(42);
        $indexes->push(100);

        $this->assertEquals(2, $indexes->count());
    }

    public function testGetReturnsIterable(): void
    {
        $indexes = new Indexes([10, 20, 30]);
        $result = [];

        foreach ($indexes->get() as $index) {
            $result[] = $index;
        }

        $this->assertEquals([10, 20, 30], $result);
    }

    public function testCountReturnsCorrectNumber(): void
    {
        $indexes = new Indexes([1, 2, 3, 4, 5]);
        $this->assertEquals(5, $indexes->count());
    }

    public function testLargeNumberOfIndexes(): void
    {
        $largeArray = range(1, 1000);
        $indexes = new Indexes($largeArray);

        $this->assertEquals(1000, $indexes->count());
    }
}
