<?php

declare(strict_types=1);

namespace Doppar\Bloom\Factories;

use Doppar\Bloom\Contracts\Hasher;
use Doppar\Bloom\Utils\HasherMD5Impl;
use Doppar\Bloom\Utils\HasherMurmurImpl;
use Doppar\Bloom\Exceptions\UnsupportedHashingAlgorithm;

class HasherFactory
{
    /**
     * Create a Hasher implementation based on the given algorithm name.
     *
     * @param string $algorithm
     * @return Hasher
     * @throws UnsupportedHashingAlgorithm
     */
    public function make(string $algorithm): Hasher
    {
        return match (strtolower($algorithm)) {
            'md5' => new HasherMD5Impl(),
            'murmur' => new HasherMurmurImpl(),
            default => throw new UnsupportedHashingAlgorithm($algorithm, 1),
        };
    }
}
