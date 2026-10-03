<?php

declare(strict_types=1);

namespace Doppar\Bloom\Tests\Support;

use Phaseolies\Config\Config;
use Phaseolies\DI\Container;

/**
 * Boots just enough of the framework (container + config) for Bloom unit tests.
 */
trait BootsFramework
{
    /**
     * @param array<string, mixed>|null $bloomConfig
     * @return Container
     */
    protected function bootFramework(?array $bloomConfig = null): Container
    {
        $container = new class extends Container {
            public function storagePath(string $path = ''): string
            {
                return sys_get_temp_dir() . ($path !== '' ? '/' . $path : '');
            }

            public function basePath(string $path = ''): string
            {
                return sys_get_temp_dir() . ($path !== '' ? '/' . $path : '');
            }
        };
        Container::setInstance($container);

        // Config::set() deep-merges into what earlier tests left behind: start clean.
        $state = new \ReflectionProperty(Config::class, 'config');
        $state->setValue(null, []);

        Config::set('bloom', $bloomConfig ?? self::defaultConfig());

        return $container;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function defaultConfig(): array
    {
        return [
            'max_capacity' => 4294967296,
            'default' => [
                'size' => 100000,
                'num_hashes' => 5,
                'persistence' => ['driver' => 'redis', 'connection' => 'default'],
                'hashing_algorithm' => 'md5',
            ],
            'keys' => [],
        ];
    }
}
