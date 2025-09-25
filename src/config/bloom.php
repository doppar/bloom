<?php

/*
|--------------------------------------------------------------------------
| Bloom Filter Configuration
|--------------------------------------------------------------------------
|
| This configuration file defines the global settings and key-specific
| configurations for the Bloom filter implementation. A Bloom filter is
| a probabilistic data structure that efficiently tests whether an
| element is a member of a set with minimal memory usage, trading
| accuracy for space efficiency.
|
| Key Concepts:
| - False positives are possible, but false negatives are not
| - Larger sizes reduce false positive probability
| - More hash functions improve accuracy but increase computation time
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum Capacity
    |--------------------------------------------------------------------------
    |
    | Maximum theoretical capacity of the Bloom filter, in bits.
    | For Redis-based persistence, this is typically 2^32 (4,294,967,296 bits).
    |
    */

    "max_capacity" => 4294967296, // 2^32 bits

    /*
    |--------------------------------------------------------------------------
    | Default Bloom Filter Settings
    |--------------------------------------------------------------------------
    |
    | Default configuration values for size, number of hash functions, 
    | persistence layer, and hashing algorithm. These values can be 
    | overridden by key-specific settings.
    |
    */

    "default" => [

        /*
        |--------------------------------------------------------------------------
        | Size
        |--------------------------------------------------------------------------
        |
        | Size of the Bloom filter in bits. Larger sizes reduce the probability
        | of false positives but increase memory usage. Optimal size can be
        | calculated based on expected elements and desired false positive rate.
        |
        | Formula: size = - (n * ln(p)) / (ln(2)^2)
        | Where n = expected elements, p = desired false positive rate
        |
        */

        "size" => 100000000, // 100 million bits ≈ 12.5 MB

        /*
        |--------------------------------------------------------------------------
        | Number of Hash Functions
        |--------------------------------------------------------------------------
        |
        | More hash functions reduce false positives but increase computation
        | time. Optimal number: k = (size / n) * ln(2)
        |
        */

        "num_hashes" => 5,

        /*
        |--------------------------------------------------------------------------
        | Persistence Layer
        |--------------------------------------------------------------------------
        |
        | Defines how Bloom filter data is stored and retrieved.
        |
        */

        "persistence" => [
            "driver" => "redis",
            "connection" => "default",
        ],

        /*
        |--------------------------------------------------------------------------
        | Hashing Algorithm
        |--------------------------------------------------------------------------
        |
        | Algorithm used to generate bit positions. Supported: 'md5', 'murmur'
        |
        | - md5: Cryptographically secure, consistent across platforms
        | - murmur: Faster, non-cryptographic, better performance
        |
        */

        "hashing_algorithm" => "md5",
    ],

    /*
    |--------------------------------------------------------------------------
    | Key-specific Configurations
    |--------------------------------------------------------------------------
    |
    | Define custom configurations for specific Bloom filter keys.
    | Each key can override the default settings based on usage patterns
    | and performance requirements.
    |
    | Example:
    |
    | 'user_recommendations' => [
    |     'size' => 5500000,
    |     'num_hashes' => 10,
    |     'persistence' => [
    |         'driver' => 'redis',
    |         'connection' => 'default'
    |     ],
    |     'hashing_algorithm' => 'md5',
    | ]
    |
    */

    "keys" => [
        // Add key-specific configurations here.
    ],
];
