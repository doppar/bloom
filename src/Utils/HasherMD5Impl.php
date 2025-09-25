<?php

declare(strict_types=1);

namespace Doppar\Bloom\Utils;

use Doppar\Bloom\Contracts\Hasher;

class HasherMD5Impl implements Hasher
{
    /**
     * Generate a hash value using MD5 and CRC32.
     *
     * Steps:
     * 1. Build input string as "{seed}__{value}".
     * 2. Compute MD5 hash of the string.
     * 3. Apply CRC32 to reduce the hash to a 32-bit integer.
     * 4. Return the absolute value to guarantee a non-negative result.
     *
     * @param int $seed Seed value to diversify hashes.
     * @param string $value Input string to hash.
     * @return int Non-negative integer hash value.
     */
    #[\Override]
    public function hash(int $seed, string $value): int
    {
        $input = sprintf("%d__%s", $seed, $value);

        return abs(crc32(md5($input)));
    }
}