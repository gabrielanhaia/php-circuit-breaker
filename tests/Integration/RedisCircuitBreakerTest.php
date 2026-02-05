<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker\Tests\Integration;

use GabrielAnhaia\PhpCircuitBreaker\CircuitBreaker;
use GabrielAnhaia\PhpCircuitBreaker\CircuitBreakerConfig;
use GabrielAnhaia\PhpCircuitBreaker\CircuitState;
use GabrielAnhaia\PhpCircuitBreaker\Storage\RedisStorage;
use PHPUnit\Framework\TestCase;

/**
 * @requires extension redis
 */
final class RedisCircuitBreakerTest extends TestCase
{
    private \Redis $redis;
    private RedisStorage $storage;

    protected function setUp(): void
    {
        $host = getenv('REDIS_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('REDIS_PORT') ?: 6379);

        $this->redis = new \Redis();

        try {
            $this->redis->connect($host, $port, 1.0);
        } catch (\RedisException) {
            $this->markTestSkipped("Redis is not available at {$host}:{$port}.");
        }

        // Use a unique prefix per test run to avoid collisions
        $prefix = 'cb_test_' . bin2hex(random_bytes(4)) . ':';
        $this->storage = new RedisStorage($this->redis, $prefix);
    }

    protected function tearDown(): void
    {
        // Clean up is handled by TTLs and unique prefixes
    }

    public function testFullLifecycleWithRedis(): void
    {
        $config = new CircuitBreakerConfig(
            failureThreshold: 3,
            successThreshold: 1,
            timeWindow: 60,
            openTimeout: 2,
        );
        $cb = new CircuitBreaker($this->storage, $config);

        // CLOSED
        $this->assertTrue($cb->canPass('redis-svc'));
        $this->assertSame(CircuitState::CLOSED, $cb->getState('redis-svc'));

        // Record failures to threshold
        $cb->recordFailure('redis-svc');
        $cb->recordFailure('redis-svc');
        $cb->recordFailure('redis-svc');

        // OPEN
        $this->assertSame(CircuitState::OPEN, $cb->getState('redis-svc'));
        $this->assertFalse($cb->canPass('redis-svc'));

        // Wait for OPEN to expire (2 seconds TTL)
        sleep(3);

        // HALF_OPEN (Redis key expired)
        $this->assertSame(CircuitState::CLOSED, $cb->getState('redis-svc'));
        // Note: Redis-based storage uses key expiry, so OPEN expires to CLOSED
        // The orchestrator handles the HALF_OPEN transition differently from InMemory
        $this->assertTrue($cb->canPass('redis-svc'));
    }

    public function testManualOverrideWithRedis(): void
    {
        $cb = new CircuitBreaker($this->storage);

        $cb->forceState('redis-svc', CircuitState::OPEN);
        $this->assertSame(CircuitState::OPEN, $cb->getState('redis-svc'));
        $this->assertFalse($cb->canPass('redis-svc'));

        $cb->clearOverride('redis-svc');
        $this->assertSame(CircuitState::CLOSED, $cb->getState('redis-svc'));
        $this->assertTrue($cb->canPass('redis-svc'));
    }

    public function testFailureAndSuccessCountersWithRedis(): void
    {
        $this->storage->recordFailure('counter-svc', 60);
        $this->storage->recordFailure('counter-svc', 60);

        $this->assertSame(2, $this->storage->getFailureCount('counter-svc'));

        $this->storage->recordSuccess('counter-svc', 60);
        // recordFailure resets successes, but recordSuccess doesn't reset failures
        $this->assertSame(1, $this->storage->getSuccessCount('counter-svc'));
    }
}
