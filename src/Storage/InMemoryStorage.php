<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker\Storage;

use GabrielAnhaia\PhpCircuitBreaker\CircuitState;
use GabrielAnhaia\PhpCircuitBreaker\Clock\Clock;
use GabrielAnhaia\PhpCircuitBreaker\Clock\SystemClock;

final class InMemoryStorage implements CircuitBreakerStorageInterface
{
    /** @var array<string, CircuitState> */
    private array $states = [];

    /** @var array<string, int> state expiry timestamps */
    private array $stateExpiry = [];

    /** @var array<string, list<int>> failure timestamps */
    private array $failures = [];

    /** @var array<string, int> success counts */
    private array $successes = [];

    /** @var array<string, CircuitState> */
    private array $overrides = [];

    /** @var array<string, int|null> override expiry timestamps (null = no expiry) */
    private array $overrideExpiry = [];

    private Clock $clock;

    public function __construct(?Clock $clock = null)
    {
        $this->clock = $clock ?? new SystemClock();
    }

    public function getState(string $serviceName): CircuitState
    {
        $this->evictExpired($serviceName);

        return $this->states[$serviceName] ?? CircuitState::CLOSED;
    }

    public function recordFailure(string $serviceName, int $timeWindowSeconds): void
    {
        $now = $this->clock->now()->getTimestamp();
        $this->failures[$serviceName][] = $now;

        // Evict failures outside the time window
        $cutoff = $now - $timeWindowSeconds;
        $this->failures[$serviceName] = array_values(
            array_filter(
                $this->failures[$serviceName],
                static fn(int $ts): bool => $ts > $cutoff,
            ),
        );

        // Reset success count on failure
        $this->successes[$serviceName] = 0;
    }

    public function getFailureCount(string $serviceName): int
    {
        if (!isset($this->failures[$serviceName])) {
            return 0;
        }

        return count($this->failures[$serviceName]);
    }

    public function recordSuccess(string $serviceName, int $timeWindowSeconds): void
    {
        $this->successes[$serviceName] = ($this->successes[$serviceName] ?? 0) + 1;
    }

    public function getSuccessCount(string $serviceName): int
    {
        return $this->successes[$serviceName] ?? 0;
    }

    public function setOpen(string $serviceName, int $ttlSeconds): void
    {
        $this->states[$serviceName] = CircuitState::OPEN;
        $this->stateExpiry[$serviceName] = $this->clock->now()->getTimestamp() + $ttlSeconds;
        $this->failures[$serviceName] = [];
        $this->successes[$serviceName] = 0;
    }

    public function setHalfOpen(string $serviceName, int $ttlSeconds): void
    {
        $this->states[$serviceName] = CircuitState::HALF_OPEN;
        $this->stateExpiry[$serviceName] = $this->clock->now()->getTimestamp() + $ttlSeconds;
        $this->successes[$serviceName] = 0;
    }

    public function setClosed(string $serviceName): void
    {
        $this->states[$serviceName] = CircuitState::CLOSED;
        unset($this->stateExpiry[$serviceName]);
        $this->failures[$serviceName] = [];
        $this->successes[$serviceName] = 0;
    }

    public function setOverride(string $serviceName, CircuitState $state, ?int $ttlSeconds = null): void
    {
        $this->overrides[$serviceName] = $state;
        $this->overrideExpiry[$serviceName] = $ttlSeconds !== null
            ? $this->clock->now()->getTimestamp() + $ttlSeconds
            : null;
    }

    public function clearOverride(string $serviceName): void
    {
        unset($this->overrides[$serviceName], $this->overrideExpiry[$serviceName]);
    }

    public function getOverride(string $serviceName): ?CircuitState
    {
        if (!isset($this->overrides[$serviceName])) {
            return null;
        }

        $expiry = $this->overrideExpiry[$serviceName] ?? null;
        if ($expiry !== null && $this->clock->now()->getTimestamp() >= $expiry) {
            $this->clearOverride($serviceName);
            return null;
        }

        return $this->overrides[$serviceName];
    }

    private function evictExpired(string $serviceName): void
    {
        if (!isset($this->stateExpiry[$serviceName])) {
            return;
        }

        $now = $this->clock->now()->getTimestamp();

        if ($now >= $this->stateExpiry[$serviceName]) {
            $currentState = $this->states[$serviceName] ?? null;

            if ($currentState === CircuitState::OPEN) {
                // OPEN expires -> transition to HALF_OPEN
                $this->states[$serviceName] = CircuitState::HALF_OPEN;
                // Keep an expiry for HALF_OPEN, but the orchestrator will manage the exact TTL
                unset($this->stateExpiry[$serviceName]);
            } elseif ($currentState === CircuitState::HALF_OPEN) {
                // HALF_OPEN expires -> transition to CLOSED
                $this->states[$serviceName] = CircuitState::CLOSED;
                unset($this->stateExpiry[$serviceName]);
                $this->failures[$serviceName] = [];
                $this->successes[$serviceName] = 0;
            }
        }
    }
}
