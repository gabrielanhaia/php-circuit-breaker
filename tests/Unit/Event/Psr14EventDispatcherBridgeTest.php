<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker\Tests\Unit\Event;

use GabrielAnhaia\PhpCircuitBreaker\Event\CircuitOpenedEvent;
use GabrielAnhaia\PhpCircuitBreaker\Event\Psr14EventDispatcherBridge;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;

final class Psr14EventDispatcherBridgeTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testDispatchDelegatesToPsrDispatcher(): void
    {
        $psrDispatcher = Mockery::mock(PsrEventDispatcherInterface::class);
        $bridge = new Psr14EventDispatcherBridge($psrDispatcher);

        $event = new CircuitOpenedEvent('svc', new \DateTimeImmutable());

        $psrDispatcher->shouldReceive('dispatch')
            ->with($event)
            ->once()
            ->andReturn($event);

        $bridge->dispatch($event);
    }

    public function testAddListenerThrowsLogicException(): void
    {
        $psrDispatcher = Mockery::mock(PsrEventDispatcherInterface::class);
        $bridge = new Psr14EventDispatcherBridge($psrDispatcher);

        $this->expectException(\LogicException::class);
        $bridge->addListener(CircuitOpenedEvent::class, static function (): void {});
    }
}
