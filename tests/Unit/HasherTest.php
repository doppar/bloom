<?php

declare(strict_types=1);

namespace Doppar\Bloom\Tests\Unit;

use Doppar\Bloom\Utils\HasherMD5Impl;
use Doppar\Bloom\Utils\HasherMurmurImpl;
use PHPUnit\Framework\TestCase;

class HasherTest extends TestCase
{
    public function testHasherMD5ImplProducesConsistentHashes(): void
    {
        $hasher = new HasherMD5Impl();

        $hash1 = $hasher->hash(1, 'test_value');
        $hash2 = $hasher->hash(1, 'test_value');

        $this->assertEquals($hash1, $hash2);
        $this->assertIsInt($hash1);
        $this->assertGreaterThanOrEqual(0, $hash1);
    }

    public function testHasherMD5ImplWithDifferentSeeds(): void
    {
        $hasher = new HasherMD5Impl();

        $hash1 = $hasher->hash(1, 'test_value');
        $hash2 = $hasher->hash(2, 'test_value');

        $this->assertNotEquals($hash1, $hash2);
    }

    public function testHasherMD5ImplWithDifferentValues(): void
    {
        $hasher = new HasherMD5Impl();

        $hash1 = $hasher->hash(1, 'value1');
        $hash2 = $hasher->hash(1, 'value2');

        $this->assertNotEquals($hash1, $hash2);
    }

    public function testHasherMurmurImplProducesConsistentHashes(): void
    {
        $hasher = new HasherMurmurImpl();

        $hash1 = $hasher->hash(1, 'test_value');
        $hash2 = $hasher->hash(1, 'test_value');

        $this->assertEquals($hash1, $hash2);
        $this->assertIsInt($hash1);
    }

    public function testHasherMurmurImplWithDifferentSeeds(): void
    {
        $hasher = new HasherMurmurImpl();

        $hash1 = $hasher->hash(1, 'test_value');
        $hash2 = $hasher->hash(2, 'test_value');

        $this->assertNotEquals($hash1, $hash2);
    }
}
