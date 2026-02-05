<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker\Event;

interface EventDispatcherInterface
{
    /**
     * @param class-string<CircuitBreakerEvent> $eventClass
     * @param callable(CircuitBreakerEvent): void $listener
     */
    public function addListener(string $eventClass, callable $listener): void;

    public function dispatch(CircuitBreakerEvent $event): void;
}
