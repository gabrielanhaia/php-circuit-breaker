<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker\Event;

abstract class CircuitBreakerEvent
{
    public function __construct(
        private readonly string $serviceName,
        private readonly \DateTimeImmutable $occurredAt,
    ) {}

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
