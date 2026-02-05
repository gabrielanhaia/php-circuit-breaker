<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker\Event;

use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;

final class Psr14EventDispatcherBridge implements EventDispatcherInterface
{
    public function __construct(
        private readonly PsrEventDispatcherInterface $psrDispatcher,
    ) {}

    public function addListener(string $eventClass, callable $listener): void
    {
        throw new \LogicException(
            'Cannot add listeners via the PSR-14 bridge. Register listeners with your PSR-14 implementation directly.',
        );
    }

    public function dispatch(CircuitBreakerEvent $event): void
    {
        $this->psrDispatcher->dispatch($event);
    }
}
