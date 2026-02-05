<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker\Storage;

use GabrielAnhaia\PhpCircuitBreaker\CircuitState;
use Psr\Cache\CacheItemPoolInterface;

final class Psr6CacheStorage implements CircuitBreakerStorageInterface
{
    private string $prefix;

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        string $prefix = 'cb_',
    ) {
        $this->prefix = $prefix;
    }

    public function getState(string $serviceName): CircuitState
    {
        $item = $this->cache->getItem($this->key($serviceName, 'state'));
        if (!$item->isHit()) {
            return CircuitState::CLOSED;
        }

        /** @var string $stateValue */
        $stateValue = $item->get();

        return CircuitState::from($stateValue);
    }

    public function recordFailure(string $serviceName, int $timeWindowSeconds): void
    {
        $key = $this->key($serviceName, 'failures');
        $item = $this->cache->getItem($key);

        /** @var int $count */
        $count = $item->isHit() ? $item->get() : 0;

        $item->set($count + 1);
        $item->expiresAfter($timeWindowSeconds);
        $this->cache->save($item);

        $this->cache->deleteItem($this->key($serviceName, 'successes'));
    }

    public function getFailureCount(string $serviceName): int
    {
        $item = $this->cache->getItem($this->key($serviceName, 'failures'));

        if (!$item->isHit()) {
            return 0;
        }

        /** @var int $count */
        $count = $item->get();

        return $count;
    }

    public function recordSuccess(string $serviceName, int $timeWindowSeconds): void
    {
        $key = $this->key($serviceName, 'successes');
        $item = $this->cache->getItem($key);

        /** @var int $count */
        $count = $item->isHit() ? $item->get() : 0;

        $item->set($count + 1);
        $item->expiresAfter($timeWindowSeconds);
        $this->cache->save($item);
    }

    public function getSuccessCount(string $serviceName): int
    {
        $item = $this->cache->getItem($this->key($serviceName, 'successes'));

        if (!$item->isHit()) {
            return 0;
        }

        /** @var int $count */
        $count = $item->get();

        return $count;
    }

    public function setOpen(string $serviceName, int $ttlSeconds): void
    {
        $item = $this->cache->getItem($this->key($serviceName, 'state'));
        $item->set(CircuitState::OPEN->value);
        $item->expiresAfter($ttlSeconds);
        $this->cache->save($item);

        $this->cache->deleteItem($this->key($serviceName, 'failures'));
        $this->cache->deleteItem($this->key($serviceName, 'successes'));
    }

    public function setHalfOpen(string $serviceName, int $ttlSeconds): void
    {
        $item = $this->cache->getItem($this->key($serviceName, 'state'));
        $item->set(CircuitState::HALF_OPEN->value);
        $item->expiresAfter($ttlSeconds);
        $this->cache->save($item);

        $this->cache->deleteItem($this->key($serviceName, 'successes'));
    }

    public function setClosed(string $serviceName): void
    {
        $this->cache->deleteItem($this->key($serviceName, 'state'));
        $this->cache->deleteItem($this->key($serviceName, 'failures'));
        $this->cache->deleteItem($this->key($serviceName, 'successes'));
    }

    public function setOverride(string $serviceName, CircuitState $state, ?int $ttlSeconds = null): void
    {
        $item = $this->cache->getItem($this->key($serviceName, 'override'));
        $item->set($state->value);
        if ($ttlSeconds !== null) {
            $item->expiresAfter($ttlSeconds);
        }
        $this->cache->save($item);
    }

    public function clearOverride(string $serviceName): void
    {
        $this->cache->deleteItem($this->key($serviceName, 'override'));
    }

    public function getOverride(string $serviceName): ?CircuitState
    {
        $item = $this->cache->getItem($this->key($serviceName, 'override'));
        if (!$item->isHit()) {
            return null;
        }

        /** @var string $stateValue */
        $stateValue = $item->get();

        return CircuitState::from($stateValue);
    }

    private function key(string $serviceName, string $suffix): string
    {
        // PSR-6 forbids {}()/\@: in keys, use underscores
        return $this->prefix . str_replace(['{', '}', '(', ')', '/', '\\', '@', ':'], '_', $serviceName) . '_' . $suffix;
    }
}
