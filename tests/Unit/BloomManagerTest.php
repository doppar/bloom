<?php

declare(strict_types=1);

namespace Doppar\Bloom\Tests\Unit;

use Doppar\Bloom\BloomManager;
use Doppar\Bloom\Contracts\Persister;
use Doppar\Bloom\Exceptions\UnsupportedHashingAlgorithm;
use Doppar\Bloom\Factories\HasherFactory;
use Doppar\Bloom\Factories\PersisterFactory;
use Doppar\Bloom\Tests\Support\BootsFramework;
use Doppar\Bloom\Tests\Support\InMemoryPersister;
use PHPUnit\Framework\TestCase;

class BloomManagerTest extends TestCase
{
    use BootsFramework;

    private InMemoryPersister $persister;

    private function manager(array $config): BloomManager
    {
        $this->bootFramework($config);
        $this->persister = new InMemoryPersister();
        $persister = $this->persister;

        $factory = new class($persister) extends PersisterFactory {
            public function __construct(private Persister $persister) {}

            public function make(string $driver, string $connection, int $capacity): Persister
            {
                return $this->persister;
            }
        };

        return new BloomManager($factory, new HasherFactory());
    }

    private function config(array $keys = [], array $default = []): array
    {
        $c = self::defaultConfig();
        $c['default'] = $default + $c['default'];
        $c['keys'] = $keys;

        return $c;
    }

    public function testKeysAreIsolatedFromEachOther(): void
    {
        $m = $this->manager($this->config());
        $m->key('a')->add('x');

        $this->assertTrue($m->key('a')->has('x'));
        $this->assertFalse($m->key('b')->has('x'));
    }

    public function testSuffixSeparatesFilters(): void
    {
        $m = $this->manager($this->config());
        $m->key('emails', '2024')->add('x');

        $this->assertTrue($m->key('emails', '2024')->has('x'));
        $this->assertFalse($m->key('emails', '2025')->has('x'));
        $this->assertFalse($m->key('emails')->has('x'));
    }

    public function testZeroIsAValidSuffix(): void
    {
        $m = $this->manager($this->config());
        $m->key('emails', '0')->add('x');

        $this->assertFalse($m->key('emails')->has('x'), 'suffix "0" must not fall back to the plain key');
        $this->assertTrue($m->key('emails', '0')->has('x'));
    }

    public function testEmptySuffixMeansNoSuffix(): void
    {
        $m = $this->manager($this->config());
        $m->key('emails')->add('x');

        $this->assertTrue($m->key('emails', '')->has('x'));
        $this->assertTrue($m->key('emails', null)->has('x'));
    }

    public function testPrefixIsAppliedToTheStorageKey(): void
    {
        $m = $this->manager($this->config(['k' => ['prefix' => 'bloom:']]));
        $m->key('k', 's')->add('x');

        $this->assertSame(['bloom:ks'], array_keys($this->persister->store));
    }

    public function testNoPrefixKeepsTheOriginalStorageKey(): void
    {
        $m = $this->manager($this->config());
        $m->key('k', 's')->add('x');

        $this->assertSame(['ks'], array_keys($this->persister->store));
    }

    public function testIndexingStrategyFromConfigIsUsed(): void
    {
        $legacy = $this->manager($this->config(['a' => ['indexing' => 'legacy']]));
        $legacy->key('a')->addMany(range(1, 200));
        $legacyBits = array_keys($this->persister->store['a']);

        $v2 = $this->manager($this->config(['a' => ['indexing' => 'v2']]));
        $v2->key('a')->addMany(range(1, 200));
        $v2Bits = array_keys($this->persister->store['a']);

        $this->assertNotSame($legacyBits, $v2Bits, 'the two strategies place bits differently');
    }

    public function testKeyOverridesApplyToTheFilter(): void
    {
        $m = $this->manager($this->config(['big' => ['size' => 777, 'num_hashes' => 2]]));

        $this->assertSame(777, $m->key('big')->getSize());
        $this->assertSame(2, $m->key('big')->getNumHashes());
        $this->assertSame(100000, $m->key('other')->getSize());
    }

    public function testUnsupportedHashingAlgorithmIsReported(): void
    {
        $m = $this->manager($this->config(['k' => ['hashing_algorithm' => 'sha999']]));

        $this->expectException(UnsupportedHashingAlgorithm::class);
        $m->key('k');
    }

    public function testOptimalConfig(): void
    {
        $m = $this->manager($this->config());

        $this->assertSame(['size' => 9585059, 'num_hashes' => 7], $m->optimalConfig(1000000, 0.01));
    }
}
