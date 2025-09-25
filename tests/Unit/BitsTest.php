<?php

declare(strict_types=1);

namespace Doppar\Bloom\Tests\Unit;

use Doppar\Bloom\Utils\Bits;
use PHPUnit\Framework\TestCase;

class BitsTest extends TestCase
{
    public function testTestReturnsTrueWhenAllBitsAreSet(): void
    {
        $bits = new Bits([1, 1, 1, 1, 1]);
        $this->assertTrue($bits->test());
    }

    public function testTestReturnsFalseWhenAnyBitIsNotSet(): void
    {
        $bits = new Bits([1, 1, 0, 1, 1]);
        $this->assertFalse($bits->test());
    }

    public function testTestReturnsFalseWhenAllBitsAreNotSet(): void
    {
        $bits = new Bits([0, 0, 0, 0, 0]);
        $this->assertFalse($bits->test());
    }

    public function testTestReturnsFalseForEmptyArray(): void
    {
        $bits = new Bits([]);
        $this->assertFalse($bits->test());
    }

    public function testTestWithMixedTruthyValues(): void
    {
        $bits = new Bits([1, true, 1, 1]);
        $this->assertTrue($bits->test());
    }

    public function testTestWithMixedFalsyValues(): void
    {
        $bits = new Bits([1, false, 1, 1]);
        $this->assertFalse($bits->test());
    }
}
