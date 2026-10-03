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
        | - md5: Consistent across platforms (the hash is reduced to 32 bits, so
        |   this is for bit placement only, not for security)
        | - murmur: Faster, non-cryptographic, better performance
        |
        */

        "hashing_algorithm" => "md5",

        /*
        |--------------------------------------------------------------------------
        | Indexing Strategy
        |--------------------------------------------------------------------------
        |
        | How a hash becomes a bit position.
        |
        | - legacy: the original calculation. Kept as the default because a
        |   filter that already holds data must keep using the positions it was
        |   written with, otherwise it would answer "not present" for items
        |   that were added. It has a flaw: with an even size, about 75% of the
        |   bits are used by half of the hashes, so the false positive rate is
        |   roughly double what size and num_hashes predict.
        | - v2: uniform positions, the false positive rate matches the maths.
        |   Use it for every NEW filter. Never switch a filter that already
        |   holds data: clear() it first and add its items again.
        |
        */

        "indexing" => "legacy",

        /*
        |--------------------------------------------------------------------------
        | Storage Key Prefix
        |--------------------------------------------------------------------------
        |
        | Prepended to the Redis key of every filter, e.g. "bloom:". Without a
        | prefix a filter named "users" shares its Redis key with anything else
        | called "users", and clear() would delete it. Set it for new filters;
        | changing it later points the filter at a different (empty) key.
        |
        */

        "prefix" => "",
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
    | A key only needs the settings that differ from the defaults above.
    | Bloom::optimalConfig(1_000_000, 0.01) returns a size and num_hashes for
    | an expected number of items and false positive rate.
    |
    | Example:
    |
    | 'user_recommendations' => [
    |     'size' => 5500000,
    |     'num_hashes' => 10,
    |     'indexing' => 'v2',
    |     'prefix' => 'bloom:',
    | ]
    |
    */

    "keys" => [
        // Add key-specific configurations here.
    ],
];
