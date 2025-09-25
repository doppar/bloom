<?php

declare(strict_types=1);

namespace Doppar\Bloom\Utils;

use Doppar\Bloom\Utils\Murmur;
use Doppar\Bloom\Contracts\Hasher;

class HasherMurmurImpl implements Hasher
{
    /**
     * Generate a hash value using MurmurHash3 algorithm.
     *
     * Steps:
     * 1. Build input string as "{seed}__{value}".
     * 2. Apply MurmurHash3 to get an integer result.
     *
     * @param int $seed  Seed value to diversify hashes.
     * @param string $value Input string to hash.
     * @return int Integer hash value.
     */
    public function hash(int $seed, string $value): int
    {
        $input = sprintf("%d__%s", $seed, $value);

        return Murmur::hash3_int($input);
    }
}