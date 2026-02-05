<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker\Event;

final class SimpleEventDispatcher implements EventDispatcherInterface
{
    /** @var array<class-string<CircuitBreakerEvent>, list<callable(CircuitBreakerEvent): void>> */
    private array $listeners = [];

    public function addListener(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    public function dispatch(CircuitBreakerEvent $event): void
    {
        $eventClass = $event::class;

        // Fire listeners registered for the exact event class
        foreach ($this->listeners[$eventClass] ?? [] as $listener) {
            $listener($event);
        }

        // Fire listeners registered for parent classes (e.g., CircuitBreakerEvent)
        foreach (class_parents($event) as $parentClass) {
            foreach ($this->listeners[$parentClass] ?? [] as $listener) {
                $listener($event);
            }
        }
    }
}
