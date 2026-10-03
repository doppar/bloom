<?php

declare(strict_types=1);

namespace Doppar\Bloom\Tests\Unit;

use Doppar\Bloom\Exceptions\BloomPersistenceException;
use Doppar\Bloom\Factories\PersisterFactory;
use Doppar\Bloom\Tests\Support\BootsFramework;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class PersisterFactoryTest extends TestCase
{
    use BootsFramework;

    public function testUnsupportedDriverIsReportedBeforeAnythingIsResolved(): void
    {
        $this->bootFramework();

        $this->expectException(BloomPersistenceException::class);
        $this->expectExceptionMessage('Unsupported Bloom persistence driver [memcached]');
        (new PersisterFactory())->make('memcached', 'default', 1000);
    }

    public function testANonRedisCacheStoreGivesAClearError(): void
    {
        $container = $this->bootFramework();
        $container->instance('cache', new class {
            public function getAdapter(): ArrayAdapter
            {
                return new ArrayAdapter();
            }

            public function __call(string $name, array $arguments): mixed
            {
                return null;
            }
        });

        $this->expectException(BloomPersistenceException::class);
        $this->expectExceptionMessage('does not use a phpredis connection');
        (new PersisterFactory())->make('redis', 'nonredis-test', 1000);
    }
}
