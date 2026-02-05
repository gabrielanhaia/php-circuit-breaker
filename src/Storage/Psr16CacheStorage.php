<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker\Storage;

use GabrielAnhaia\PhpCircuitBreaker\CircuitState;
use Psr\SimpleCache\CacheInterface;

final class Psr16CacheStorage implements CircuitBreakerStorageInterface
{
    private string $prefix;

    public function __construct(
        private readonly CacheInterface $cache,
        string $prefix = 'cb_',
    ) {
        $this->prefix = $prefix;
    }

    public function getState(string $serviceName): CircuitState
    {
        /** @var string|null $value */
        $value = $this->cache->get($this->key($serviceName, 'state'));
        if ($value === null) {
            return CircuitState::CLOSED;
        }

        return CircuitState::from($value);
    }

    public function recordFailure(string $serviceName, int $timeWindowSeconds): void
    {
        $key = $this->key($serviceName, 'failures');

        /** @var int|null $stored */
        $stored = $this->cache->get($key);
        $count = $stored ?? 0;

        $this->cache->set($key, $count + 1, $timeWindowSeconds);

        $this->cache->delete($this->key($serviceName, 'successes'));
    }

    public function getFailureCount(string $serviceName): int
    {
        /** @var int|null $value */
        $value = $this->cache->get($this->key($serviceName, 'failures'));

        return $value ?? 0;
    }

    public function recordSuccess(string $serviceName, int $timeWindowSeconds): void
    {
        $key = $this->key($serviceName, 'successes');

        /** @var int|null $stored */
        $stored = $this->cache->get($key);
        $count = $stored ?? 0;

        $this->cache->set($key, $count + 1, $timeWindowSeconds);
    }

    public function getSuccessCount(string $serviceName): int
    {
        /** @var int|null $value */
        $value = $this->cache->get($this->key($serviceName, 'successes'));

        return $value ?? 0;
    }

    public function setOpen(string $serviceName, int $ttlSeconds): void
    {
        $this->cache->set($this->key($serviceName, 'state'), CircuitState::OPEN->value, $ttlSeconds);
        $this->cache->delete($this->key($serviceName, 'failures'));
        $this->cache->delete($this->key($serviceName, 'successes'));
    }

    public function setHalfOpen(string $serviceName, int $ttlSeconds): void
    {
        $this->cache->set($this->key($serviceName, 'state'), CircuitState::HALF_OPEN->value, $ttlSeconds);
        $this->cache->delete($this->key($serviceName, 'successes'));
    }

    public function setClosed(string $serviceName): void
    {
        $this->cache->delete($this->key($serviceName, 'state'));
        $this->cache->delete($this->key($serviceName, 'failures'));
        $this->cache->delete($this->key($serviceName, 'successes'));
    }

    public function setOverride(string $serviceName, CircuitState $state, ?int $ttlSeconds = null): void
    {
        $this->cache->set(
            $this->key($serviceName, 'override'),
            $state->value,
            $ttlSeconds,
        );
    }

    public function clearOverride(string $serviceName): void
    {
        $this->cache->delete($this->key($serviceName, 'override'));
    }

    public function getOverride(string $serviceName): ?CircuitState
    {
        /** @var string|null $value */
        $value = $this->cache->get($this->key($serviceName, 'override'));
        if ($value === null) {
            return null;
        }

        return CircuitState::from($value);
    }

    private function key(string $serviceName, string $suffix): string
    {
        return $this->prefix . $serviceName . '_' . $suffix;
    }
}
