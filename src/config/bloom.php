<?php

/**
 * Bloom Filter Configuration
 *
 * This configuration file defines the global settings and key-specific configurations
 * for the Bloom filter implementation. The Bloom filter is a probabilistic data structure
 * that efficiently tests whether an element is a member of a set with minimal memory usage,
 * trading accuracy for space efficiency.
 *
 * Key Concepts:
 * - False positives are possible, but false negatives are not
 * - Larger sizes reduce false positive probability
 * - More hash functions improve accuracy but increase computation time
 *
 * @package Doppar\Bloom
 */

return [
    /**
     * Maximum theoretical capacity of the Bloom filter
     *
     * This represents the maximum number of bits available for the filter.
     * For Redis-based persistence, this is typically 2^32 (4,294,967,296 bits).
     *
     * @var int
     */
    "max_capacity" => 4294967296, // 2^32 bits


    "default" => [
        /**
         * Size of the Bloom filter in bits
         *
         * Larger sizes reduce the probability of false positives but increase memory usage.
         * Optimal size can be calculated based on expected number of elements and desired false positive rate.
         *
         * Formula: size = - (n * ln(p)) / (ln(2)^2)
         * Where n = expected elements, p = desired false positive rate
         *
         * @var int
         */
        "size" => 100000000, // 100 million bits ≈ 12.5 MB

        /**
         * Number of hash functions to use
         *
         * More hash functions reduce false positives but increase computation time.
         * Optimal number: k = (size / n) * ln(2)
         *
         * @var int
         */
        "num_hashes" => 5,

        /**
         * Persistence layer configuration
         *
         * Defines how the Bloom filter data is stored and retrieved.
         *
         * @var array
         */
        "persistence" => [
            /**
             * Storage driver for Bloom filter data
             *
             * Supported drivers: 'redis'
             *
             * @var string
             */
            "driver" => "redis",

            /**
             * Connection name for the persistence driver
             *
             * @var string
             */
            "connection" => "default",
        ],

        /**
         * Hashing algorithm for generating bit positions
         *
         * Supported algorithms: 'md5', 'murmur'
         *
         * - md5: Cryptographically secure, consistent across platforms
         * - murmur: Faster, non-cryptographic, better performance
         *
         * @var string
         */
        "hashing_algorithm" => "md5",
    ],

    /**
     * Key-specific Bloom filter configurations
     *
     * Define custom configurations for specific Bloom filter keys.
     * Each key can override the default settings with optimized values
     * based on expected usage patterns and performance requirements.
     *
     * Example configuration:
     *
     * 'user_recommendations' => [
     *     'size' => 5500000,           // 5.5 million bits
     *     'num_hashes' => 10,          // 10 hash functions
     *     'persistence' => [
     *         'driver' => 'redis',
     *         'connection' => 'default'
     *     ],
     *     'hashing_algorithm' => 'md5',
     * ]
     *
     * @var array
     */
    "keys" => [
        // Key-specific configurations can be added here
        // Each key should map to an array of configuration options
        // that override the default settings
    ],
];
