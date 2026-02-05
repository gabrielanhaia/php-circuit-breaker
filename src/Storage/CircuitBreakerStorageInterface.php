<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker\Storage;

use GabrielAnhaia\PhpCircuitBreaker\CircuitState;

interface CircuitBreakerStorageInterface
{
    public function getState(string $serviceName): CircuitState;

    public function recordFailure(string $serviceName, int $timeWindowSeconds): void;

    public function getFailureCount(string $serviceName): int;

    public function recordSuccess(string $serviceName, int $timeWindowSeconds): void;

    public function getSuccessCount(string $serviceName): int;

    public function setOpen(string $serviceName, int $ttlSeconds): void;

    public function setHalfOpen(string $serviceName, int $ttlSeconds): void;

    public function setClosed(string $serviceName): void;

    public function setOverride(string $serviceName, CircuitState $state, ?int $ttlSeconds = null): void;

    public function clearOverride(string $serviceName): void;

    public function getOverride(string $serviceName): ?CircuitState;
}
