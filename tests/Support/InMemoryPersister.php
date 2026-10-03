<?php

declare(strict_types=1);

namespace Doppar\Bloom\Tests\Support;

use Doppar\Bloom\Contracts\Persister;
use Doppar\Bloom\Utils\Bits;
use Doppar\Bloom\Utils\Indexes;

/**
 * Persister that keeps the bits in PHP arrays, so the filter logic can be tested without Redis.
 */
final class InMemoryPersister implements Persister
{
    /** @var array<string, array<int, true>> */
    public array $store = [];

    public int $writes = 0;

    public function setBits(string $key, Indexes $multi): void
    {
        $this->setBitsMany($key, [$multi]);
    }

    public function getBits(string $key, Indexes $multi): Bits
    {
        return $this->getBitsMany($key, [$multi])[0];
    }

    public function setBitsMany(string $key, array $items): array
    {
        $result = [];

        foreach ($items as $indexes) {
            $present = true;
            foreach ($indexes->get() as $index) {
                if (!isset($this->store[$key][$index])) {
                    $present = false;
                }
                $this->store[$key][$index] = true;
                $this->writes++;
            }
            $result[] = $present;
        }

        return $result;
    }

    public function getBitsMany(string $key, array $items): array
    {
        $result = [];

        foreach ($items as $indexes) {
            $values = [];
            foreach ($indexes->get() as $index) {
                $values[] = isset($this->store[$key][$index]) ? 1 : 0;
            }
            $result[] = new Bits($values);
        }

        return $result;
    }

    public function countBits(string $key): int
    {
        return count($this->store[$key] ?? []);
    }

    public function clear(string $key): void
    {
        unset($this->store[$key]);
    }

    public function getMaxCapacity(): int
    {
        return 4294967296;
    }
}
