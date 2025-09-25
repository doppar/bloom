<?php

declare(strict_types=1);

namespace Doppar\Bloom\Contracts;

interface Hasher
{
    /**
     * Generate a deterministic hash value for the given input
     *
     * @param int $seed
     * @param string $value
     * @return int
     */
    public function hash(int $seed, string $value): int;
}