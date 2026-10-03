<?php

namespace Doppar\Bloom\Facades;

use Doppar\Bloom\BloomFilter;
use Phaseolies\Facade\BaseFacade;

/**
 * Class Bloom
 * @package Doppar\Bloom\Facades
 *
 * @method static BloomFilter key(string $key, ?string $keySuffix = null)
 * @method static array{size: int, num_hashes: int} optimalConfig(int $expectedItems, float $falsePositiveRate)
 */
class Bloom extends BaseFacade
{
    protected static function getFacadeAccessor(): string
    {
        return 'bloom.manager';
    }
}