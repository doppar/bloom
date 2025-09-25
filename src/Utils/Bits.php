<?php

declare(strict_types=1);

namespace Doppar\Bloom\Utils;

class Bits
{
    /**
     * Array of bit values retrieved from persistence layer
     *
     * @var array<int> Array of integers (0 or 1) representing bit states
     */
    private $values;

    /**
     * Values constructor.
     * @param array $values
     */
    public function __construct(array $values)
    {
        $this->values = $values;
    }

    /**
     * Test if all bit positions are set (indicating probable membership)
     *
     * @return bool
     */
    public function test(): bool
    {
        if (empty($this->values)) {
            return false;
        }

        foreach ($this->values as $value) {
            if (! $value) {
                return false;
            }
        }

        return true;
    }
}