<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker\Storage;

use GabrielAnhaia\PhpCircuitBreaker\CircuitState;

final class MemcachedStorage implements CircuitBreakerStorageInterface
{
    private string $prefix;

    public function __construct(
        private readonly \Memcached $memcached,
        string $prefix = 'cb:',
    ) {
        $this->prefix = $prefix;
    }

    public function getState(string $serviceName): CircuitState
    {
        /** @var string|false $value */
        $value = $this->memcached->get($this->key($serviceName, 'state'));
        if ($this->memcached->getResultCode() === \Memcached::RES_NOTFOUND) {
            return CircuitState::CLOSED;
        }

        return CircuitState::from((string) $value);
    }

    public function recordFailure(string $serviceName, int $timeWindowSeconds): void
    {
        $key = $this->key($serviceName, 'failures');
        if (!$this->memcached->increment($key)) {
            $this->memcached->set($key, 1, $timeWindowSeconds);
        } else {
            $this->memcached->touch($key, $timeWindowSeconds);
        }

        $this->memcached->delete($this->key($serviceName, 'successes'));
    }

    public function getFailureCount(string $serviceName): int
    {
        /** @var int|string|false $value */
        $value = $this->memcached->get($this->key($serviceName, 'failures'));
        if ($this->memcached->getResultCode() === \Memcached::RES_NOTFOUND) {
            return 0;
        }

        return (int) $value;
    }

    public function recordSuccess(string $serviceName, int $timeWindowSeconds): void
    {
        $key = $this->key($serviceName, 'successes');
        if (!$this->memcached->increment($key)) {
            $this->memcached->set($key, 1, $timeWindowSeconds);
        } else {
            $this->memcached->touch($key, $timeWindowSeconds);
        }
    }

    public function getSuccessCount(string $serviceName): int
    {
        /** @var int|string|false $value */
        $value = $this->memcached->get($this->key($serviceName, 'successes'));
        if ($this->memcached->getResultCode() === \Memcached::RES_NOTFOUND) {
            return 0;
        }

        return (int) $value;
    }

    public function setOpen(string $serviceName, int $ttlSeconds): void
    {
        $this->memcached->set($this->key($serviceName, 'state'), CircuitState::OPEN->value, $ttlSeconds);
        $this->memcached->delete($this->key($serviceName, 'failures'));
        $this->memcached->delete($this->key($serviceName, 'successes'));
    }

    public function setHalfOpen(string $serviceName, int $ttlSeconds): void
    {
        $this->memcached->set($this->key($serviceName, 'state'), CircuitState::HALF_OPEN->value, $ttlSeconds);
        $this->memcached->delete($this->key($serviceName, 'successes'));
    }

    public function setClosed(string $serviceName): void
    {
        $this->memcached->delete($this->key($serviceName, 'state'));
        $this->memcached->delete($this->key($serviceName, 'failures'));
        $this->memcached->delete($this->key($serviceName, 'successes'));
    }

    public function setOverride(string $serviceName, CircuitState $state, ?int $ttlSeconds = null): void
    {
        $this->memcached->set(
            $this->key($serviceName, 'override'),
            $state->value,
            $ttlSeconds ?? 0,
        );
    }

    public function clearOverride(string $serviceName): void
    {
        $this->memcached->delete($this->key($serviceName, 'override'));
    }

    public function getOverride(string $serviceName): ?CircuitState
    {
        /** @var string|false $value */
        $value = $this->memcached->get($this->key($serviceName, 'override'));
        if ($this->memcached->getResultCode() === \Memcached::RES_NOTFOUND) {
            return null;
        }

        return CircuitState::from((string) $value);
    }

    private function key(string $serviceName, string $suffix): string
    {
        return $this->prefix . $serviceName . ':' . $suffix;
    }
}
