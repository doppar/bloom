<?php

declare(strict_types=1);

namespace Doppar\Bloom\Tests\Unit;

use Doppar\Bloom\Exceptions\BloomPersistenceException;
use Doppar\Bloom\Exceptions\InvalidBloomFilterSize;
use Doppar\Bloom\Tests\Support\BootsFramework;
use Doppar\Bloom\Utils\Indexes;
use Doppar\Bloom\Utils\PersisterRedisImpl;
use Mockery;
use PHPUnit\Framework\TestCase;

class PersisterRedisImplTest extends TestCase
{
    use BootsFramework;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFramework();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * A \Redis whose pipeline records the commands and answers with the given replies.
     *
     * @param array<int, array<int, mixed>> $replyBatches one list of replies per pipeline
     */
    private function redis(array $replyBatches, array &$commands): \Redis
    {
        $redis = Mockery::mock(\Redis::class);
        $batches = $replyBatches;

        $redis->shouldReceive('pipeline')->andReturnUsing(function () use (&$batches, &$commands) {
            $pipe = Mockery::mock(\Redis::class);
            $pipe->shouldReceive('setBit')->andReturnUsing(function ($key, $index, $value) use (&$commands, $pipe) {
                $commands[] = ['SETBIT', $key, $index, $value];

                return $pipe;
            });
            $pipe->shouldReceive('getBit')->andReturnUsing(function ($key, $index) use (&$commands, $pipe) {
                $commands[] = ['GETBIT', $key, $index];

                return $pipe;
            });
            $pipe->shouldReceive('exec')->andReturnUsing(function () use (&$batches) {
                return array_shift($batches);
            });

            return $pipe;
        });

        return $redis;
    }

    public function testSetBitsManyReportsWhichItemsWereAlreadyPresent(): void
    {
        $commands = [];
        // item 1: previous bits 1,1 (present); item 2: 1,0 (new); item 3: 0,0 (new)
        $p = new PersisterRedisImpl($this->redis([[1, 1, 1, 0, 0, 0]], $commands), 1000);

        $result = $p->setBitsMany('k', [new Indexes([1, 2]), new Indexes([3, 4]), new Indexes([5, 6])]);

        $this->assertSame([true, false, false], $result);
        $this->assertSame(['SETBIT', 'k', 3, true], $commands[2]);
        $this->assertCount(6, $commands);
    }

    public function testGetBitsManyMapsRepliesBackToItems(): void
    {
        $commands = [];
        $p = new PersisterRedisImpl($this->redis([[1, 1, 1, 0]], $commands), 1000);

        $bits = $p->getBitsMany('k', [new Indexes([1, 2]), new Indexes([3, 4])]);

        $this->assertTrue($bits[0]->test());
        $this->assertFalse($bits[1]->test());
    }

    public function testLargeBatchesAreSplitIntoSeveralPipelines(): void
    {
        $commands = [];
        // 1200 items x 2 indexes: batches of 500, 500, 200 items
        $batches = [array_fill(0, 1000, 0), array_fill(0, 1000, 0), array_fill(0, 400, 0)];
        $p = new PersisterRedisImpl($this->redis($batches, $commands), 1000);

        $items = array_map(fn($i) => new Indexes([$i, $i + 1]), range(0, 1199));
        $result = $p->setBitsMany('k', $items);

        $this->assertCount(1200, $result);
        $this->assertCount(2400, $commands);
        $this->assertNotContains(true, $result);
    }

    public function testAFailedPipelineIsNeverReadAsBitNotSet(): void
    {
        foreach ([false, [1], [1, false], [1, "x"]] as $bad) {
            $commands = [];
            $p = new PersisterRedisImpl($this->redis([$bad], $commands), 1000);

            try {
                $p->getBitsMany('k', [new Indexes([1, 2])]);
                $this->fail('expected an exception for ' . var_export($bad, true));
            } catch (BloomPersistenceException $e) {
                $this->assertStringContainsString('[k]', $e->getMessage());
            }
        }
    }

    public function testSetBitsAndGetBitsStillWork(): void
    {
        $commands = [];
        $p = new PersisterRedisImpl($this->redis([[0, 0], [1, 1]], $commands), 1000);

        $p->setBits('k', new Indexes([1, 2]));
        $this->assertTrue($p->getBits('k', new Indexes([1, 2]))->test());
    }

    public function testCountBits(): void
    {
        $redis = Mockery::mock(\Redis::class);
        $redis->shouldReceive('bitCount')->with('k')->andReturn(42);
        $this->assertSame(42, (new PersisterRedisImpl($redis, 1000))->countBits('k'));

        $broken = Mockery::mock(\Redis::class);
        $broken->shouldReceive('bitCount')->andReturn(false);
        $this->expectException(BloomPersistenceException::class);
        (new PersisterRedisImpl($broken, 1000))->countBits('k');
    }

    public function testCapacityAboveTheMaximumIsRejectedWithAClearException(): void
    {
        $this->expectException(InvalidBloomFilterSize::class);
        $this->expectExceptionMessage('exceeds the maximum capacity');
        new PersisterRedisImpl(Mockery::mock(\Redis::class), 4294967297);
    }

    public function testClearDeletesTheKey(): void
    {
        $redis = Mockery::mock(\Redis::class);
        $redis->shouldReceive('del')->once()->with('k');
        (new PersisterRedisImpl($redis, 1000))->clear('k');
        $this->addToAssertionCount(1);
    }
}
