<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker\Storage;

use GabrielAnhaia\PhpCircuitBreaker\CircuitState;
use GabrielAnhaia\PhpCircuitBreaker\Exception\StorageException;

final class RedisStorage implements CircuitBreakerStorageInterface
{
    private string $prefix;

    public function __construct(
        private readonly \Redis $redis,
        string $prefix = 'cb:',
    ) {
        $this->prefix = $prefix;
    }

    public function getState(string $serviceName): CircuitState
    {
        try {
            if ($this->redis->exists($this->key($serviceName, 'state:open'))) {
                return CircuitState::OPEN;
            }

            if ($this->redis->exists($this->key($serviceName, 'state:half_open'))) {
                return CircuitState::HALF_OPEN;
            }

            return CircuitState::CLOSED;
        } catch (\RedisException $e) {
            throw new StorageException($e->getMessage(), 0, $e);
        }
    }

    public function recordFailure(string $serviceName, int $timeWindowSeconds): void
    {
        $key = $this->key($serviceName, 'failures');

        try {
            $this->redis->multi();
            $this->redis->incr($key);
            $this->redis->expire($key, $timeWindowSeconds);
            $this->redis->del($this->key($serviceName, 'successes'));
            $this->redis->exec();
        } catch (\RedisException $e) {
            throw new StorageException($e->getMessage(), 0, $e);
        }
    }

    public function getFailureCount(string $serviceName): int
    {
        try {
            $value = $this->redis->get($this->key($serviceName, 'failures'));

            if ($value === false) {
                return 0;
            }

            /** @var int|string $value */
            return (int) $value;
        } catch (\RedisException $e) {
            throw new StorageException($e->getMessage(), 0, $e);
        }
    }

    public function recordSuccess(string $serviceName, int $timeWindowSeconds): void
    {
        $key = $this->key($serviceName, 'successes');

        try {
            $this->redis->multi();
            $this->redis->incr($key);
            $this->redis->expire($key, $timeWindowSeconds);
            $this->redis->exec();
        } catch (\RedisException $e) {
            throw new StorageException($e->getMessage(), 0, $e);
        }
    }

    public function getSuccessCount(string $serviceName): int
    {
        try {
            $value = $this->redis->get($this->key($serviceName, 'successes'));

            if ($value === false) {
                return 0;
            }

            /** @var int|string $value */
            return (int) $value;
        } catch (\RedisException $e) {
            throw new StorageException($e->getMessage(), 0, $e);
        }
    }

    public function setOpen(string $serviceName, int $ttlSeconds): void
    {
        try {
            $this->redis->multi();
            $this->redis->setex($this->key($serviceName, 'state:open'), $ttlSeconds, '1');
            $this->redis->del($this->key($serviceName, 'state:half_open'));
            $this->redis->del($this->key($serviceName, 'failures'));
            $this->redis->del($this->key($serviceName, 'successes'));
            $this->redis->exec();
        } catch (\RedisException $e) {
            throw new StorageException($e->getMessage(), 0, $e);
        }
    }

    public function setHalfOpen(string $serviceName, int $ttlSeconds): void
    {
        try {
            $this->redis->multi();
            $this->redis->setex($this->key($serviceName, 'state:half_open'), $ttlSeconds, '1');
            $this->redis->del($this->key($serviceName, 'state:open'));
            $this->redis->del($this->key($serviceName, 'successes'));
            $this->redis->exec();
        } catch (\RedisException $e) {
            throw new StorageException($e->getMessage(), 0, $e);
        }
    }

    public function setClosed(string $serviceName): void
    {
        try {
            $this->redis->del(
                $this->key($serviceName, 'state:open'),
                $this->key($serviceName, 'state:half_open'),
                $this->key($serviceName, 'failures'),
                $this->key($serviceName, 'successes'),
            );
        } catch (\RedisException $e) {
            throw new StorageException($e->getMessage(), 0, $e);
        }
    }

    public function setOverride(string $serviceName, CircuitState $state, ?int $ttlSeconds = null): void
    {
        $key = $this->key($serviceName, 'override');

        try {
            if ($ttlSeconds !== null) {
                $this->redis->setex($key, $ttlSeconds, $state->value);
            } else {
                $this->redis->set($key, $state->value);
            }
        } catch (\RedisException $e) {
            throw new StorageException($e->getMessage(), 0, $e);
        }
    }

    public function clearOverride(string $serviceName): void
    {
        try {
            $this->redis->del($this->key($serviceName, 'override'));
        } catch (\RedisException $e) {
            throw new StorageException($e->getMessage(), 0, $e);
        }
    }

    public function getOverride(string $serviceName): ?CircuitState
    {
        try {
            $value = $this->redis->get($this->key($serviceName, 'override'));

            if ($value === false) {
                return null;
            }

            /** @var string $value */
            return CircuitState::from($value);
        } catch (\RedisException $e) {
            throw new StorageException($e->getMessage(), 0, $e);
        }
    }

    private function key(string $serviceName, string $suffix): string
    {
        return $this->prefix . $serviceName . ':' . $suffix;
    }
}
