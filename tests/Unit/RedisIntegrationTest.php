<?php

declare(strict_types=1);

namespace Doppar\Bloom\Tests\Unit;

use Doppar\Bloom\BloomFilter;
use Doppar\Bloom\Tests\Support\BootsFramework;
use Doppar\Bloom\Utils\HasherMurmurImpl;
use Doppar\Bloom\Utils\Indexer;
use Doppar\Bloom\Utils\KeySpecificConfig;
use Doppar\Bloom\Utils\PersisterRedisImpl;
use PHPUnit\Framework\TestCase;

/**
 * Runs the real phpredis commands against a local Redis (127.0.0.1:6379, or
 * BLOOM_TEST_REDIS_HOST / BLOOM_TEST_REDIS_PORT). Skipped when none is reachable.
 * Uses a unique key that is deleted afterwards and never touches other keys.
 */
class RedisIntegrationTest extends TestCase
{
    use BootsFramework;

    private ?\Redis $redis = null;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFramework();

        $redis = new \Redis();
        $host = getenv('BLOOM_TEST_REDIS_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('BLOOM_TEST_REDIS_PORT') ?: 6379);

        try {
            if (!@$redis->connect($host, $port, 0.5)) {
                $this->markTestSkipped('No Redis available');
            }
            $redis->ping();
        } catch (\Throwable) {
            $this->markTestSkipped('No Redis available');
        }

        $this->redis = $redis;
        $this->key = 'bloom-phpunit:' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->redis?->del($this->key);
        parent::tearDown();
    }

    private function filter(int $size = 200000, int $hashes = 5): BloomFilter
    {
        $config = KeySpecificConfig::of('t', [
            'default' => ['size' => $size, 'num_hashes' => $hashes, 'hashing_algorithm' => 'murmur', 'indexing' => 'v2',
                'persistence' => ['driver' => 'redis', 'connection' => 'default']],
            'keys' => [],
        ]);

        return new BloomFilter($this->key, $config, new Indexer(new HasherMurmurImpl(), 'v2'), new PersisterRedisImpl($this->redis, $size));
    }

    public function testAddHasAndTheAlreadyPresentFlagAgainstRealRedis(): void
    {
        $f = $this->filter();

        $this->assertFalse($f->add('alice@example.com'));
        $this->assertTrue($f->add('alice@example.com'));
        $this->assertTrue($f->has('alice@example.com'));
        $this->assertFalse($f->has('nobody@example.com'));
    }

    public function testBatchesAcrossSeveralPipelines(): void
    {
        $f = $this->filter(2000000, 5);
        $items = array_map(fn($i) => "user-$i", range(1, 1500));

        $added = $f->addMany($items);
        $this->assertCount(1500, $added);

        $found = $f->hasMany($items);
        $this->assertNotContains(false, $found, 'false negative');
        $this->assertCount(1500, $found);
    }

    public function testStatsUseBitCount(): void
    {
        $f = $this->filter(1000000, 5);
        $f->addMany(array_map(fn($i) => "s$i", range(1, 10000)));

        $stats = $f->stats();
        $this->assertSame($stats['bits_set'], (int) $this->redis->bitCount($this->key));
        $this->assertEqualsWithDelta(10000, $stats['estimated_items'], 300);
    }

    public function testClearOnlyRemovesItsOwnKey(): void
    {
        $other = $this->key . ':other';
        $this->redis->set($other, 'keep');

        $f = $this->filter();
        $f->add('x');
        $f->clear();

        $this->assertFalse($f->has('x'));
        $this->assertSame('keep', $this->redis->get($other));
        $this->redis->del($other);
    }

    public function testLegacyFiltersWrittenBeforeThisVersionStillAnswer(): void
    {
        // Bits written with the original calculation, straight into Redis.
        $hasher = new HasherMurmurImpl();
        $size = 100000;
        foreach (['old-1', 'old-2'] as $item) {
            for ($seed = 1; $seed <= 5; $seed++) {
                $hash = $hasher->hash($seed, $item);
                $bit = $hash & (-1 >> 1);
                if ($hash >> 31 === 1) {
                    $bit *= 2;
                }
                $this->redis->setBit($this->key, $bit % $size, true);
            }
        }

        $config = KeySpecificConfig::of('t', [
            'default' => ['size' => $size, 'num_hashes' => 5, 'hashing_algorithm' => 'murmur',
                'persistence' => ['driver' => 'redis', 'connection' => 'default']],
            'keys' => [],
        ]);
        $filter = new BloomFilter($this->key, $config, new Indexer(new HasherMurmurImpl()), new PersisterRedisImpl($this->redis, $size));

        $this->assertTrue($filter->has('old-1'));
        $this->assertTrue($filter->has('old-2'));
        $this->assertFalse($filter->has('never-added'));
    }

    public function testFullStackThroughTheManagerAndTheCacheAdapterConnection(): void
    {
        $container = $this->bootFramework(array_replace_recursive(self::defaultConfig(), [
            'default' => ['indexing' => 'v2', 'prefix' => 'bloom-phpunit:', 'hashing_algorithm' => 'murmur'],
        ]));
        $adapter = new \Symfony\Component\Cache\Adapter\RedisAdapter($this->redis);
        $container->instance('cache', new class($adapter) {
            public function __construct(private object $adapter) {}

            public function getAdapter(): object
            {
                return $this->adapter;
            }

            public function __call(string $name, array $arguments): mixed
            {
                return null;
            }
        });

        $suffix = bin2hex(random_bytes(4));
        $manager = new \Doppar\Bloom\BloomManager(new \Doppar\Bloom\Factories\PersisterFactory(), new \Doppar\Bloom\Factories\HasherFactory());
        $filter = $manager->key('stack', $suffix);

        try {
            $this->assertFalse($filter->add('alice@example.com'));
            $this->assertTrue($manager->key('stack', $suffix)->has('alice@example.com'));
            $this->assertFalse($manager->key('stack', $suffix . 'x')->has('alice@example.com'));
            $this->assertSame(1, $this->redis->exists('bloom-phpunit:stack' . $suffix), 'stored under the prefixed key');
        } finally {
            $filter->clear();
        }
    }
}
