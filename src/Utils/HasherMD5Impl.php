<?php

declare(strict_types=1);

namespace Doppar\Bloom\Utils;

use Doppar\Bloom\Contracts\Hasher;

class HasherMD5Impl implements Hasher
{
    /**
     * @inheritDoc
     */
    public function hash(int $seed, string $value): int
    {
        $input = sprintf("%d__%s", $seed, $value);

        return abs(crc32(md5($input)));
    }
}