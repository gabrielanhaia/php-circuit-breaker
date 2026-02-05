<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker\Storage;

use GabrielAnhaia\PhpCircuitBreaker\CircuitState;
use GabrielAnhaia\PhpCircuitBreaker\Exception\StorageException;

final class ApcuStorage implements CircuitBreakerStorageInterface
{
    private string $prefix;

    public function __construct(string $prefix = 'cb:')
    {
        if (!extension_loaded('apcu')) {
            throw new StorageException('The APCu extension is not loaded.');
        }

        $this->prefix = $prefix;
    }

    public function getState(string $serviceName): CircuitState
    {
        $value = apcu_fetch($this->key($serviceName, 'state'));
        if ($value === false) {
            return CircuitState::CLOSED;
        }

        \assert(\is_string($value));

        return CircuitState::from($value);
    }

    public function recordFailure(string $serviceName, int $timeWindowSeconds): void
    {
        $key = $this->key($serviceName, 'failures');

        $stored = apcu_fetch($key);
        if ($stored === false) {
            apcu_store($key, 1, $timeWindowSeconds);
        } else {
            \assert(\is_int($stored));
            apcu_store($key, $stored + 1, $timeWindowSeconds);
        }

        apcu_delete($this->key($serviceName, 'successes'));
    }

    public function getFailureCount(string $serviceName): int
    {
        $value = apcu_fetch($this->key($serviceName, 'failures'));
        if ($value === false) {
            return 0;
        }

        \assert(\is_int($value));

        return $value;
    }

    public function recordSuccess(string $serviceName, int $timeWindowSeconds): void
    {
        $key = $this->key($serviceName, 'successes');

        $stored = apcu_fetch($key);
        if ($stored === false) {
            apcu_store($key, 1, $timeWindowSeconds);
        } else {
            \assert(\is_int($stored));
            apcu_store($key, $stored + 1, $timeWindowSeconds);
        }
    }

    public function getSuccessCount(string $serviceName): int
    {
        $value = apcu_fetch($this->key($serviceName, 'successes'));
        if ($value === false) {
            return 0;
        }

        \assert(\is_int($value));

        return $value;
    }

    public function setOpen(string $serviceName, int $ttlSeconds): void
    {
        apcu_store($this->key($serviceName, 'state'), CircuitState::OPEN->value, $ttlSeconds);
        apcu_delete($this->key($serviceName, 'failures'));
        apcu_delete($this->key($serviceName, 'successes'));
    }

    public function setHalfOpen(string $serviceName, int $ttlSeconds): void
    {
        apcu_store($this->key($serviceName, 'state'), CircuitState::HALF_OPEN->value, $ttlSeconds);
        apcu_delete($this->key($serviceName, 'successes'));
    }

    public function setClosed(string $serviceName): void
    {
        apcu_delete($this->key($serviceName, 'state'));
        apcu_delete($this->key($serviceName, 'failures'));
        apcu_delete($this->key($serviceName, 'successes'));
    }

    public function setOverride(string $serviceName, CircuitState $state, ?int $ttlSeconds = null): void
    {
        apcu_store(
            $this->key($serviceName, 'override'),
            $state->value,
            $ttlSeconds ?? 0,
        );
    }

    public function clearOverride(string $serviceName): void
    {
        apcu_delete($this->key($serviceName, 'override'));
    }

    public function getOverride(string $serviceName): ?CircuitState
    {
        $value = apcu_fetch($this->key($serviceName, 'override'));
        if ($value === false) {
            return null;
        }

        \assert(\is_string($value));

        return CircuitState::from($value);
    }

    private function key(string $serviceName, string $suffix): string
    {
        return $this->prefix . $serviceName . ':' . $suffix;
    }
}
