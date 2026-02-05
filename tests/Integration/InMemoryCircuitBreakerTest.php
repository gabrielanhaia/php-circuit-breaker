<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker\Tests\Integration;

use GabrielAnhaia\PhpCircuitBreaker\CircuitBreaker;
use GabrielAnhaia\PhpCircuitBreaker\CircuitBreakerConfig;
use GabrielAnhaia\PhpCircuitBreaker\CircuitState;
use GabrielAnhaia\PhpCircuitBreaker\Clock\TestClock;
use GabrielAnhaia\PhpCircuitBreaker\Event\CircuitClosedEvent;
use GabrielAnhaia\PhpCircuitBreaker\Event\CircuitOpenedEvent;
use GabrielAnhaia\PhpCircuitBreaker\Event\SimpleEventDispatcher;
use GabrielAnhaia\PhpCircuitBreaker\Exception\OpenCircuitException;
use GabrielAnhaia\PhpCircuitBreaker\Storage\InMemoryStorage;
use PHPUnit\Framework\TestCase;

final class InMemoryCircuitBreakerTest extends TestCase
{
    private TestClock $clock;
    private InMemoryStorage $storage;

    protected function setUp(): void
    {
        $this->clock = new TestClock(new \DateTimeImmutable('2024-01-01 00:00:00'));
        $this->storage = new InMemoryStorage($this->clock);
    }

    public function testFullLifecycleClosedToOpenToHalfOpenToClosed(): void
    {
        $config = new CircuitBreakerConfig(
            failureThreshold: 3,
            successThreshold: 2,
            openTimeout: 30,
        );
        $cb = new CircuitBreaker($this->storage, $config, clock: $this->clock);

        // CLOSED: can pass
        $this->assertTrue($cb->canPass('svc'));
        $this->assertSame(CircuitState::CLOSED, $cb->getState('svc'));

        // Record failures to reach threshold
        $cb->recordFailure('svc');
        $cb->recordFailure('svc');
        $this->assertTrue($cb->canPass('svc')); // Still CLOSED (2 < 3)

        $cb->recordFailure('svc');
        // Now OPEN (3 >= 3)
        $this->assertSame(CircuitState::OPEN, $cb->getState('svc'));
        $this->assertFalse($cb->canPass('svc'));

        // Wait for open timeout to expire -> HALF_OPEN
        $this->clock->advance(31);
        $this->assertSame(CircuitState::HALF_OPEN, $cb->getState('svc'));
        $this->assertTrue($cb->canPass('svc'));

        // Record successes to meet threshold
        $cb->recordSuccess('svc');
        $this->assertSame(CircuitState::HALF_OPEN, $cb->getState('svc')); // 1 < 2

        $cb->recordSuccess('svc');
        // Now CLOSED (2 >= 2)
        $this->assertSame(CircuitState::CLOSED, $cb->getState('svc'));
        $this->assertTrue($cb->canPass('svc'));
    }

    public function testHalfOpenFailureReopensCircuit(): void
    {
        $config = new CircuitBreakerConfig(
            failureThreshold: 2,
            openTimeout: 10,
        );
        $cb = new CircuitBreaker($this->storage, $config, clock: $this->clock);

        // Open the circuit
        $cb->recordFailure('svc');
        $cb->recordFailure('svc');
        $this->assertSame(CircuitState::OPEN, $cb->getState('svc'));

        // Wait for HALF_OPEN
        $this->clock->advance(11);
        $this->assertSame(CircuitState::HALF_OPEN, $cb->getState('svc'));

        // Failure in HALF_OPEN -> immediately OPEN again
        $cb->recordFailure('svc');
        $this->assertSame(CircuitState::OPEN, $cb->getState('svc'));
    }

    public function testExceptionsOnOpenCircuit(): void
    {
        $config = new CircuitBreakerConfig(
            failureThreshold: 1,
            exceptionsEnabled: true,
        );
        $cb = new CircuitBreaker($this->storage, $config, clock: $this->clock);

        $cb->recordFailure('svc');

        $this->expectException(OpenCircuitException::class);
        $cb->canPass('svc');
    }

    public function testManualOverrideForceOpen(): void
    {
        $cb = new CircuitBreaker($this->storage, clock: $this->clock);

        $this->assertTrue($cb->canPass('svc'));

        $cb->forceState('svc', CircuitState::OPEN);
        $this->assertSame(CircuitState::OPEN, $cb->getState('svc'));
        $this->assertFalse($cb->canPass('svc'));

        $cb->clearOverride('svc');
        $this->assertSame(CircuitState::CLOSED, $cb->getState('svc'));
        $this->assertTrue($cb->canPass('svc'));
    }

    public function testManualOverrideWithTtl(): void
    {
        $cb = new CircuitBreaker($this->storage, clock: $this->clock);

        $cb->forceState('svc', CircuitState::OPEN, 60);
        $this->assertSame(CircuitState::OPEN, $cb->getState('svc'));

        $this->clock->advance(61);
        // Override expired, falls back to storage state
        $this->assertSame(CircuitState::CLOSED, $cb->getState('svc'));
    }

    public function testEventDispatcherReceivesEvents(): void
    {
        $dispatcher = new SimpleEventDispatcher();
        $events = [];

        $dispatcher->addListener(CircuitOpenedEvent::class, static function (CircuitOpenedEvent $e) use (&$events): void {
            $events[] = 'opened:' . $e->getServiceName();
        });
        $dispatcher->addListener(CircuitClosedEvent::class, static function (CircuitClosedEvent $e) use (&$events): void {
            $events[] = 'closed:' . $e->getServiceName();
        });

        $config = new CircuitBreakerConfig(
            failureThreshold: 1,
            successThreshold: 1,
            openTimeout: 10,
        );
        $cb = new CircuitBreaker($this->storage, $config, $dispatcher, $this->clock);

        $cb->recordFailure('payment');
        $this->clock->advance(11);
        $cb->recordSuccess('payment');

        $this->assertSame(['opened:payment', 'closed:payment'], $events);
    }

    public function testMultipleServicesAreIsolated(): void
    {
        $config = new CircuitBreakerConfig(failureThreshold: 2);
        $cb = new CircuitBreaker($this->storage, $config, clock: $this->clock);

        $cb->recordFailure('svc-a');
        $cb->recordFailure('svc-a');

        $this->assertSame(CircuitState::OPEN, $cb->getState('svc-a'));
        $this->assertSame(CircuitState::CLOSED, $cb->getState('svc-b'));
        $this->assertTrue($cb->canPass('svc-b'));
    }

    public function testSuccessInClosedStateResets(): void
    {
        $config = new CircuitBreakerConfig(failureThreshold: 5);
        $cb = new CircuitBreaker($this->storage, $config, clock: $this->clock);

        $cb->recordFailure('svc');
        $cb->recordFailure('svc');
        $cb->recordSuccess('svc');

        // Failures should be cleared by setClosed
        $this->assertSame(0, $this->storage->getFailureCount('svc'));
    }

    public function testDeprecatedAliasesWork(): void
    {
        $config = new CircuitBreakerConfig(failureThreshold: 1, openTimeout: 10);
        $cb = new CircuitBreaker($this->storage, $config, clock: $this->clock);

        $cb->failed('svc');
        $this->assertSame(CircuitState::OPEN, $cb->getState('svc'));

        $this->clock->advance(11);
        $cb->succeed('svc');
        $this->assertSame(CircuitState::CLOSED, $cb->getState('svc'));
    }
}
