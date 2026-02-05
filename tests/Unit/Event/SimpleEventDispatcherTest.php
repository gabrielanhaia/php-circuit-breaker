<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker\Tests\Unit\Event;

use GabrielAnhaia\PhpCircuitBreaker\Event\CircuitBreakerEvent;
use GabrielAnhaia\PhpCircuitBreaker\Event\CircuitClosedEvent;
use GabrielAnhaia\PhpCircuitBreaker\Event\CircuitOpenedEvent;
use GabrielAnhaia\PhpCircuitBreaker\Event\SimpleEventDispatcher;
use PHPUnit\Framework\TestCase;

final class SimpleEventDispatcherTest extends TestCase
{
    public function testDispatchCallsRegisteredListener(): void
    {
        $dispatcher = new SimpleEventDispatcher();
        $received = [];

        $dispatcher->addListener(CircuitOpenedEvent::class, static function (CircuitBreakerEvent $e) use (&$received): void {
            $received[] = $e;
        });

        $event = new CircuitOpenedEvent('svc', new \DateTimeImmutable());
        $dispatcher->dispatch($event);

        $this->assertCount(1, $received);
        $this->assertSame($event, $received[0]);
    }

    public function testDispatchCallsMultipleListeners(): void
    {
        $dispatcher = new SimpleEventDispatcher();
        $count = 0;

        $dispatcher->addListener(CircuitOpenedEvent::class, static function () use (&$count): void {
            $count++;
        });
        $dispatcher->addListener(CircuitOpenedEvent::class, static function () use (&$count): void {
            $count++;
        });

        $dispatcher->dispatch(new CircuitOpenedEvent('svc', new \DateTimeImmutable()));

        $this->assertSame(2, $count);
    }

    public function testDispatchIgnoresUnrelatedListeners(): void
    {
        $dispatcher = new SimpleEventDispatcher();
        $called = false;

        $dispatcher->addListener(CircuitClosedEvent::class, static function () use (&$called): void {
            $called = true;
        });

        $dispatcher->dispatch(new CircuitOpenedEvent('svc', new \DateTimeImmutable()));

        $this->assertFalse($called);
    }

    public function testBaseClassListenerReceivesAllEvents(): void
    {
        $dispatcher = new SimpleEventDispatcher();
        $received = [];

        $dispatcher->addListener(CircuitBreakerEvent::class, static function (CircuitBreakerEvent $e) use (&$received): void {
            $received[] = $e::class;
        });

        $dispatcher->dispatch(new CircuitOpenedEvent('svc', new \DateTimeImmutable()));
        $dispatcher->dispatch(new CircuitClosedEvent('svc', new \DateTimeImmutable()));

        $this->assertSame([CircuitOpenedEvent::class, CircuitClosedEvent::class], $received);
    }

    public function testNoListenersDoesNotThrow(): void
    {
        $dispatcher = new SimpleEventDispatcher();

        $dispatcher->dispatch(new CircuitOpenedEvent('svc', new \DateTimeImmutable()));

        $this->addToAssertionCount(1);
    }

    public function testEventCarriesServiceNameAndTimestamp(): void
    {
        $now = new \DateTimeImmutable('2024-06-01 12:00:00');
        $event = new CircuitOpenedEvent('payment-api', $now);

        $this->assertSame('payment-api', $event->getServiceName());
        $this->assertSame($now, $event->getOccurredAt());
    }
}
