<?php

declare(strict_types=1);

namespace Doppar\Bloom\Utils;

use Exception;
use Phaseolies\Support\Lens;
use Doppar\Bloom\Exceptions\InvalidBloomFilterSize;
use Doppar\Bloom\Exceptions\InvalidBloomFilterConfiguration;
use Doppar\Bloom\Exceptions\InvalidBloomFilterHashFunctionsNumber;

final class KeySpecificConfig
{
    /**
     * @var integer
     */
    private $numHashes;

    /**
     * @var int
     */
    private $size;

    /**
     * @var string
     */
    private $hashingAlgorithm;

    /**
     * @var string
     */
    private $persistenceDriver;

    /**
     * @var string
     */
    private $persistenceConnection;

    /**
     * @var string
     */
    private $key;

    /**
     * BloomFilterConfig constructor.
     * @param string $key
     * @param int $numHashes
     * @param int $size
     * @param string $hashingAlgorithm
     * @param string $persistenceDriver
     * @param string $persistenceConnection
     */
    private function __construct(
        string $key,
        int $numHashes,
        int $size,
        string $hashingAlgorithm,
        string $persistenceDriver,
        string $persistenceConnection,
    ) {
        $this->key = $key;
        $this->numHashes = $numHashes;
        $this->size = $size;
        $this->hashingAlgorithm = $hashingAlgorithm;
        $this->persistenceDriver = $persistenceDriver;
        $this->persistenceConnection = $persistenceConnection;
    }

    /**
     * @param string $key
     * @param array $bloomConfig
     * @return KeySpecificConfig
     * @throws InvalidBloomFilterConfiguration
     * @throws InvalidBloomFilterHashFunctionsNumber
     * @throws InvalidBloomFilterSize
     */
    public static function of(string $key, $bloomConfig = []): self
    {
        if (
            !is_array($bloomConfig) ||
            empty($bloomConfig) ||
            !isset($bloomConfig["default"]) ||
            !isset($bloomConfig["keys"])
        ) {
            throw new InvalidBloomFilterConfiguration(
                "Bloom filter configuration file [bloom.php] is empty, invalid or misplaced.",
                1,
            );
        }

        $keySpecificConfig = Lens::grab(
            $bloomConfig,
            "keys.{$key}",
            $bloomConfig["default"],
        );

        $numHashes = self::validatedNumHashes(
            Lens::grab($keySpecificConfig, "num_hashes"),
        );

        $size = self::validatedSize(
            Lens::grab($keySpecificConfig, "size"),
            $numHashes,
        );

        $hashingAlgorithm = Lens::grab($keySpecificConfig, "hashing_algorithm");

        $persistenceDriver = Lens::grab(
            $keySpecificConfig,
            "persistence.driver",
        );

        $persistenceConnection = Lens::grab(
            $keySpecificConfig,
            "persistence.connection",
        );

        if (!is_string($hashingAlgorithm)) {
            throw new Exception("Invalid hashing algorithm", 1);
        }

        if (!is_string($persistenceDriver)) {
            throw new Exception("Invalid persistence driver $persistenceDriver", 1);
        }

        if (!is_string($persistenceConnection)) {
            throw new Exception("Invalid persistence connection $persistenceConnection", 1);
        }

        return new static(
            $key,
            $numHashes,
            $size,
            $hashingAlgorithm,
            $persistenceDriver,
            $persistenceConnection,
        );
    }

    /**
     * @param $size
     * @return int|string
     * @throws InvalidBloomFilterSize
     */
    private static function validatedSize(int $size, int $numHashes): int
    {
        if (!is_integer($size) || $size <= $numHashes) {
            throw new InvalidBloomFilterSize(
                "Invalid bloom filter size $size",
                1,
            );
        }

        $size = intval($size);

        return $size;
    }

    /**
     * @param $num
     * @return int|string
     * @throws InvalidBloomFilterHashFunctionsNumber
     */
    private static function validatedNumHashes($num): int
    {
        if (!is_integer($num) || $num <= 0) {
            throw new InvalidBloomFilterHashFunctionsNumber($num, 1);
        }

        return intval($num);
    }

    /**
     * @return int
     */
    public function getNumHashes(): int
    {
        return $this->numHashes;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return string
     */
    public function getHashingAlgorithm(): string
    {
        return $this->hashingAlgorithm;
    }

    /**
     * @return string
     */
    public function getPersistenceDriver(): string
    {
        return $this->persistenceDriver;
    }

    /**
     * @return string
     */
    public function getPersistenceConnection(): string
    {
        return $this->persistenceConnection;
    }
}
