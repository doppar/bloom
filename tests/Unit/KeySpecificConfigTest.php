<?php

declare(strict_types=1);

namespace Doppar\Bloom\Tests\Unit;

use Doppar\Bloom\Exceptions\InvalidBloomFilterConfiguration;
use Doppar\Bloom\Exceptions\InvalidBloomFilterHashFunctionsNumber;
use Doppar\Bloom\Exceptions\InvalidBloomFilterSize;
use Doppar\Bloom\Utils\KeySpecificConfig;
use PHPUnit\Framework\TestCase;

class KeySpecificConfigTest extends TestCase
{
    /** @return array<string, mixed> */
    private function config(array $keys = [], array $default = []): array
    {
        return [
            'max_capacity' => 1000000,
            'default' => $default + [
                'size' => 1000,
                'num_hashes' => 3,
                'hashing_algorithm' => 'md5',
                'persistence' => ['driver' => 'redis', 'connection' => 'default'],
            ],
            'keys' => $keys,
        ];
    }

    public function testDefaultsApplyToUnknownKeys(): void
    {
        $c = KeySpecificConfig::of('anything', $this->config());

        $this->assertSame(1000, $c->getSize());
        $this->assertSame(3, $c->getNumHashes());
        $this->assertSame('md5', $c->getHashingAlgorithm());
        $this->assertSame('redis', $c->getPersistenceDriver());
        $this->assertSame('default', $c->getPersistenceConnection());
    }

    public function testNewOptionsKeepTheHistoricalBehaviourWhenAbsent(): void
    {
        $c = KeySpecificConfig::of('x', $this->config());

        $this->assertSame('legacy', $c->getIndexing());
        $this->assertSame('', $c->getPrefix());
    }

    public function testAKeyOverridesOnlyWhatItSets(): void
    {
        $c = KeySpecificConfig::of('emails', $this->config(['emails' => ['size' => 5000]]));

        $this->assertSame(5000, $c->getSize());
        $this->assertSame(3, $c->getNumHashes(), 'num_hashes falls back to the default');
        $this->assertSame('redis', $c->getPersistenceDriver());
    }

    public function testNestedPersistenceOverrideIsMerged(): void
    {
        $c = KeySpecificConfig::of('k', $this->config(['k' => ['persistence' => ['connection' => 'other']]]));

        $this->assertSame('redis', $c->getPersistenceDriver());
        $this->assertSame('other', $c->getPersistenceConnection());
    }

    public function testIndexingAndPrefixCanBeSetPerKey(): void
    {
        $c = KeySpecificConfig::of('k', $this->config(['k' => ['indexing' => 'v2', 'prefix' => 'bloom:']]));

        $this->assertSame('v2', $c->getIndexing());
        $this->assertSame('bloom:', $c->getPrefix());
    }

    public function testLiteralDottedKeyNameWinsAndNestedPathStillWorks(): void
    {
        $keys = ['user.emails' => ['size' => 2000], 'group' => ['inner' => ['size' => 3000]]];

        $this->assertSame(2000, KeySpecificConfig::of('user.emails', $this->config($keys))->getSize());
        $this->assertSame(3000, KeySpecificConfig::of('group.inner', $this->config($keys))->getSize());
    }

    public function testMissingOrInvalidConfigurationFileIsReported(): void
    {
        foreach ([null, [], ['default' => []], ['keys' => []], ['default' => 'x', 'keys' => []]] as $bad) {
            try {
                KeySpecificConfig::of('k', $bad);
                $this->fail('expected an exception');
            } catch (InvalidBloomFilterConfiguration) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testMissingSizeThrowsADescriptiveExceptionNotATypeError(): void
    {
        $config = $this->config();
        unset($config['default']['size']);

        $this->expectException(InvalidBloomFilterSize::class);
        $this->expectExceptionMessage('[k]');
        KeySpecificConfig::of('k', $config);
    }

    public function testSizeMustExceedNumHashes(): void
    {
        $this->expectException(InvalidBloomFilterSize::class);
        KeySpecificConfig::of('k', $this->config(['k' => ['size' => 3]]));
    }

    public function testSizeAboveMaxCapacityIsRejected(): void
    {
        $this->expectException(InvalidBloomFilterSize::class);
        $this->expectExceptionMessage('maximum capacity');
        KeySpecificConfig::of('k', $this->config(['k' => ['size' => 1000001]]));
    }

    public function testInvalidNumHashes(): void
    {
        foreach ([0, -1, '3', null, 2.5] as $bad) {
            try {
                KeySpecificConfig::of('k', $this->config(['k' => ['num_hashes' => $bad]]));
                $this->fail('expected an exception for ' . var_export($bad, true));
            } catch (InvalidBloomFilterHashFunctionsNumber $e) {
                $this->assertStringContainsString('[k]', $e->getMessage());
            }
        }
    }

    public function testInvalidStringSettings(): void
    {
        foreach (['hashing_algorithm', 'persistence'] as $option) {
            $override = $option === 'persistence' ? ['persistence' => ['driver' => ['x']]] : [$option => '' ];
            try {
                KeySpecificConfig::of('k', $this->config(['k' => $override]));
                $this->fail('expected an exception for ' . $option);
            } catch (InvalidBloomFilterConfiguration) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testInvalidIndexingAndPrefix(): void
    {
        foreach ([['indexing' => 'v3'], ['indexing' => 2], ['prefix' => 5]] as $override) {
            try {
                KeySpecificConfig::of('k', $this->config(['k' => $override]));
                $this->fail('expected an exception');
            } catch (InvalidBloomFilterConfiguration) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testKeyEntryMustBeAnArray(): void
    {
        $this->expectException(InvalidBloomFilterConfiguration::class);
        KeySpecificConfig::of('k', $this->config(['k' => 'oops']));
    }
}
