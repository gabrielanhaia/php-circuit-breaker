<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker;

final class CircuitBreakerConfig
{
    public function __construct(
        private readonly int $failureThreshold = 5,
        private readonly int $successThreshold = 1,
        private readonly int $timeWindow = 20,
        private readonly int $openTimeout = 30,
        private readonly int $halfOpenTimeout = 20,
        private readonly bool $exceptionsEnabled = false,
    ) {}

    public function getFailureThreshold(): int
    {
        return $this->failureThreshold;
    }

    public function getSuccessThreshold(): int
    {
        return $this->successThreshold;
    }

    public function getTimeWindow(): int
    {
        return $this->timeWindow;
    }

    public function getOpenTimeout(): int
    {
        return $this->openTimeout;
    }

    public function getHalfOpenTimeout(): int
    {
        return $this->halfOpenTimeout;
    }

    public function isExceptionsEnabled(): bool
    {
        return $this->exceptionsEnabled;
    }
}
