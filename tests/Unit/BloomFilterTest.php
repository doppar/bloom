<?php

declare(strict_types=1);

namespace Doppar\Bloom\Tests\Unit;

use Doppar\Bloom\BloomFilter;
use Doppar\Bloom\Tests\Support\InMemoryPersister;
use Doppar\Bloom\Utils\HasherMurmurImpl;
use Doppar\Bloom\Utils\Indexer;
use Doppar\Bloom\Utils\KeySpecificConfig;
use PHPUnit\Framework\TestCase;

class BloomFilterTest extends TestCase
{
    private InMemoryPersister $persister;

    private function filter(int $size = 100000, int $hashes = 5, string $indexing = 'v2'): BloomFilter
    {
        $this->persister = new InMemoryPersister();
        $config = KeySpecificConfig::of('t', [
            'default' => [
                'size' => $size, 'num_hashes' => $hashes, 'hashing_algorithm' => 'murmur', 'indexing' => $indexing,
                'persistence' => ['driver' => 'redis', 'connection' => 'default'],
            ],
            'keys' => [],
        ]);

        return new BloomFilter('t', $config, new Indexer(new HasherMurmurImpl(), $indexing), $this->persister);
    }

    public function testAddedItemsAreFoundAndOthersAreNot(): void
    {
        $f = $this->filter();
        $f->add('alice@example.com');

        $this->assertTrue($f->has('alice@example.com'));
        $this->assertFalse($f->has('bob@example.com'));
    }

    public function testAddReportsWhetherTheItemWasAlreadyThere(): void
    {
        $f = $this->filter();

        $this->assertFalse($f->add('x'), 'first time: not present before');
        $this->assertTrue($f->add('x'), 'second time: already present');
    }

    public function testNumericItemsAndTheirStringFormAreTheSameItem(): void
    {
        $f = $this->filter();
        $f->add(42);

        $this->assertTrue($f->has('42'));
        $this->assertTrue($f->has(42));
    }

    public function testAddManyAndHasManyKeepInputOrder(): void
    {
        $f = $this->filter();

        $this->assertSame([false, false, true], $f->addMany(['a', 'b', 'a']), 'a duplicate inside one batch is seen as present');
        $this->assertSame([true, false, true, false], $f->hasMany(['a', 'zzz-1', 'b', 'zzz-2']));
    }

    public function testBatchesAcceptGenerators(): void
    {
        $f = $this->filter();
        $gen = (function () {
            yield 'g1';
            yield 'g2';
        })();

        $this->assertSame([false, false], $f->addMany($gen));
        $this->assertSame([true, true], $f->hasMany(['g1', 'g2']));
    }

    public function testEmptyBatchesDoNothing(): void
    {
        $f = $this->filter();

        $this->assertSame([], $f->addMany([]));
        $this->assertSame([], $f->hasMany([]));
        $this->assertSame(0, $this->persister->writes);
    }

    public function testAnInvalidItemRejectsTheWholeBatchBeforeAnythingIsWritten(): void
    {
        $f = $this->filter();

        try {
            $f->addMany(['ok', ['array'], 'also-ok']);
            $this->fail('expected an exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('array', $e->getMessage());
        }

        $this->assertSame(0, $this->persister->writes);
        $this->assertFalse($f->has('ok'));
    }

    public function testInvalidItemTypes(): void
    {
        $f = $this->filter();

        foreach ([null, true, [], new \stdClass()] as $bad) {
            try {
                $f->has($bad);
                $this->fail('expected an exception');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testClearForgetsEverything(): void
    {
        $f = $this->filter();
        $f->add('x');
        $f->clear();

        $this->assertFalse($f->has('x'));
    }

    public function testNeverAFalseNegative(): void
    {
        foreach (['legacy', 'v2'] as $indexing) {
            $f = $this->filter(20000, 4, $indexing);
            $items = array_map(fn($i) => "item-$i", range(1, 3000));
            $f->addMany($items);

            $this->assertNotContains(false, $f->hasMany($items), "false negative with $indexing indexing");
        }
    }

    public function testStatsDescribeTheFill(): void
    {
        $f = $this->filter(1000000, 5);
        $empty = $f->stats();

        $this->assertSame(0, $empty['bits_set']);
        $this->assertSame(0, $empty['estimated_items']);
        $this->assertSame(0.0, $empty['estimated_false_positive_rate']);

        $f->addMany(array_map(fn($i) => "u$i", range(1, 20000)));
        $stats = $f->stats();

        $this->assertSame(1000000, $stats['size']);
        $this->assertSame(5, $stats['num_hashes']);
        $this->assertEqualsWithDelta(20000, $stats['estimated_items'], 600);
        $this->assertEqualsWithDelta($stats['bits_set'] / 1000000, $stats['fill_ratio'], 1e-12);
        $this->assertGreaterThan(0.0, $stats['estimated_false_positive_rate']);
    }

    public function testGetters(): void
    {
        $f = $this->filter(5000, 3);

        $this->assertSame(5000, $f->getSize());
        $this->assertSame(3, $f->getNumHashes());
    }
}
