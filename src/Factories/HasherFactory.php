<?php

declare(strict_types=1);

namespace Doppar\Bloom\Factories;

use Doppar\Bloom\Contracts\Hasher;
use Doppar\Bloom\Utils\HasherMD5Impl;
use Doppar\Bloom\Utils\HasherMurmurImpl;
use Doppar\Bloom\Exceptions\UnsupportedHashingAlgorithm;

class HasherFactory
{
    const MD5_HASH_ALGORITHM = 'md5';

    const MURMUR_HASH_ALGORITHM = 'murmur';

    /**
     * @param $algorithm
     * @return Hasher
     * @throws UnsupportedHashingAlgorithm
     */
    public function make(string $algorithm): Hasher
    {
        switch (strtolower($algorithm)) {
            case self::MD5_HASH_ALGORITHM:
                return new HasherMD5Impl();
            case self::MURMUR_HASH_ALGORITHM:
                return new HasherMurmurImpl();
            default:
                throw new UnsupportedHashingAlgorithm($algorithm, 1);
        }
    }
}