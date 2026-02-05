<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker\Tests\Unit;

use GabrielAnhaia\PhpCircuitBreaker\CircuitBreaker;
use GabrielAnhaia\PhpCircuitBreaker\CircuitBreakerConfig;
use GabrielAnhaia\PhpCircuitBreaker\CircuitState;
use GabrielAnhaia\PhpCircuitBreaker\Event\CircuitClosedEvent;
use GabrielAnhaia\PhpCircuitBreaker\Event\CircuitOpenedEvent;
use GabrielAnhaia\PhpCircuitBreaker\Event\EventDispatcherInterface;
use GabrielAnhaia\PhpCircuitBreaker\Event\FailureRecordedEvent;
use GabrielAnhaia\PhpCircuitBreaker\Event\SuccessRecordedEvent;
use GabrielAnhaia\PhpCircuitBreaker\Exception\OpenCircuitException;
use GabrielAnhaia\PhpCircuitBreaker\Storage\CircuitBreakerStorageInterface;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

final class CircuitBreakerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private CircuitBreakerStorageInterface|Mockery\MockInterface $storage;

    protected function setUp(): void
    {
        $this->storage = Mockery::mock(CircuitBreakerStorageInterface::class);
        $this->storage->shouldReceive('getOverride')->andReturn(null)->byDefault();
    }

    public function testCanPassReturnsTrueWhenClosed(): void
    {
        $this->storage->shouldReceive('getState')->andReturn(CircuitState::CLOSED);
        $cb = new CircuitBreaker($this->storage);

        $this->assertTrue($cb->canPass('svc'));
    }

    public function testCanPassReturnsTrueWhenHalfOpen(): void
    {
        $this->storage->shouldReceive('getState')->andReturn(CircuitState::HALF_OPEN);
        $cb = new CircuitBreaker($this->storage);

        $this->assertTrue($cb->canPass('svc'));
    }

    public function testCanPassReturnsFalseWhenOpen(): void
    {
        $this->storage->shouldReceive('getState')->andReturn(CircuitState::OPEN);
        $cb = new CircuitBreaker($this->storage);

        $this->assertFalse($cb->canPass('svc'));
    }

    public function testCanPassThrowsWhenOpenAndExceptionsEnabled(): void
    {
        $this->storage->shouldReceive('getState')->andReturn(CircuitState::OPEN);
        $config = new CircuitBreakerConfig(exceptionsEnabled: true);
        $cb = new CircuitBreaker($this->storage, $config);

        $this->expectException(OpenCircuitException::class);
        $cb->canPass('svc');
    }

    public function testRecordFailureBelowThresholdDoesNotOpenCircuit(): void
    {
        $config = new CircuitBreakerConfig(failureThreshold: 3);

        $this->storage->shouldReceive('getState')->andReturn(CircuitState::CLOSED);
        $this->storage->shouldReceive('recordFailure')->once();
        $this->storage->shouldReceive('getFailureCount')->andReturn(1);

        $cb = new CircuitBreaker($this->storage, $config);
        $cb->recordFailure('svc');
    }

    public function testRecordFailureAtThresholdOpensCircuit(): void
    {
        $config = new CircuitBreakerConfig(failureThreshold: 3, openTimeout: 30);

        $this->storage->shouldReceive('getState')->andReturn(CircuitState::CLOSED);
        $this->storage->shouldReceive('recordFailure')->once();
        $this->storage->shouldReceive('getFailureCount')->andReturn(3);
        $this->storage->shouldReceive('setOpen')->with('svc', 30)->once();

        $cb = new CircuitBreaker($this->storage, $config);
        $cb->recordFailure('svc');
    }

    public function testRecordFailureInHalfOpenImmediatelyOpens(): void
    {
        $config = new CircuitBreakerConfig(openTimeout: 30);

        $this->storage->shouldReceive('getState')->andReturn(CircuitState::HALF_OPEN);
        $this->storage->shouldReceive('recordFailure')->once();
        $this->storage->shouldReceive('setOpen')->with('svc', 30)->once();

        $cb = new CircuitBreaker($this->storage, $config);
        $cb->recordFailure('svc');
    }

    public function testRecordSuccessInHalfOpenWithSufficientThresholdCloses(): void
    {
        $config = new CircuitBreakerConfig(successThreshold: 2);

        $this->storage->shouldReceive('getState')->andReturn(CircuitState::HALF_OPEN);
        $this->storage->shouldReceive('recordSuccess')->once();
        $this->storage->shouldReceive('getSuccessCount')->andReturn(2);
        $this->storage->shouldReceive('setClosed')->with('svc')->once();

        $cb = new CircuitBreaker($this->storage, $config);
        $cb->recordSuccess('svc');
    }

    public function testRecordSuccessInHalfOpenBelowThresholdDoesNotClose(): void
    {
        $config = new CircuitBreakerConfig(successThreshold: 3);

        $this->storage->shouldReceive('getState')->andReturn(CircuitState::HALF_OPEN);
        $this->storage->shouldReceive('recordSuccess')->once();
        $this->storage->shouldReceive('getSuccessCount')->andReturn(1);
        $this->storage->shouldNotReceive('setClosed');

        $cb = new CircuitBreaker($this->storage, $config);
        $cb->recordSuccess('svc');
    }

    public function testRecordSuccessInClosedResetsCounts(): void
    {
        $this->storage->shouldReceive('getState')->andReturn(CircuitState::CLOSED);
        $this->storage->shouldReceive('recordSuccess')->once();
        $this->storage->shouldReceive('setClosed')->with('svc')->once();

        $cb = new CircuitBreaker($this->storage);
        $cb->recordSuccess('svc');
    }

    public function testGetStateReturnsOverrideWhenSet(): void
    {
        $this->storage->shouldReceive('getOverride')
            ->with('svc')
            ->andReturn(CircuitState::OPEN);

        $cb = new CircuitBreaker($this->storage);

        $this->assertSame(CircuitState::OPEN, $cb->getState('svc'));
    }

    public function testGetStateReturnsStorageStateWhenNoOverride(): void
    {
        $this->storage->shouldReceive('getOverride')->with('svc')->andReturn(null);
        $this->storage->shouldReceive('getState')->andReturn(CircuitState::HALF_OPEN);

        $cb = new CircuitBreaker($this->storage);

        $this->assertSame(CircuitState::HALF_OPEN, $cb->getState('svc'));
    }

    public function testForceStateSetsOverride(): void
    {
        $this->storage->shouldReceive('setOverride')
            ->with('svc', CircuitState::OPEN, 60)
            ->once();

        $cb = new CircuitBreaker($this->storage);
        $cb->forceState('svc', CircuitState::OPEN, 60);
    }

    public function testClearOverrideDelegates(): void
    {
        $this->storage->shouldReceive('clearOverride')
            ->with('svc')
            ->once();

        $cb = new CircuitBreaker($this->storage);
        $cb->clearOverride('svc');
    }

    public function testRecordFailureDispatchesEvents(): void
    {
        $config = new CircuitBreakerConfig(failureThreshold: 1, openTimeout: 30);
        $dispatcher = Mockery::mock(EventDispatcherInterface::class);

        $this->storage->shouldReceive('getState')->andReturn(CircuitState::CLOSED);
        $this->storage->shouldReceive('recordFailure');
        $this->storage->shouldReceive('getFailureCount')->andReturn(1);
        $this->storage->shouldReceive('setOpen');

        $dispatcher->shouldReceive('dispatch')
            ->with(Mockery::type(FailureRecordedEvent::class))
            ->once();
        $dispatcher->shouldReceive('dispatch')
            ->with(Mockery::type(CircuitOpenedEvent::class))
            ->once();

        $cb = new CircuitBreaker($this->storage, $config, $dispatcher);
        $cb->recordFailure('svc');
    }

    public function testRecordSuccessDispatchesEventsOnClose(): void
    {
        $config = new CircuitBreakerConfig(successThreshold: 1);
        $dispatcher = Mockery::mock(EventDispatcherInterface::class);

        $this->storage->shouldReceive('getState')->andReturn(CircuitState::HALF_OPEN);
        $this->storage->shouldReceive('recordSuccess');
        $this->storage->shouldReceive('getSuccessCount')->andReturn(1);
        $this->storage->shouldReceive('setClosed');

        $dispatcher->shouldReceive('dispatch')
            ->with(Mockery::type(SuccessRecordedEvent::class))
            ->once();
        $dispatcher->shouldReceive('dispatch')
            ->with(Mockery::type(CircuitClosedEvent::class))
            ->once();

        $cb = new CircuitBreaker($this->storage, $config, $dispatcher);
        $cb->recordSuccess('svc');
    }

    public function testDeprecatedFailedCallsRecordFailure(): void
    {
        $config = new CircuitBreakerConfig(failureThreshold: 100);

        $this->storage->shouldReceive('getState')->andReturn(CircuitState::CLOSED);
        $this->storage->shouldReceive('recordFailure')->once();
        $this->storage->shouldReceive('getFailureCount')->andReturn(1);

        $cb = new CircuitBreaker($this->storage, $config);
        $cb->failed('svc');
    }

    public function testDeprecatedSucceedCallsRecordSuccess(): void
    {
        $this->storage->shouldReceive('getState')->andReturn(CircuitState::CLOSED);
        $this->storage->shouldReceive('recordSuccess')->once();
        $this->storage->shouldReceive('setClosed')->once();

        $cb = new CircuitBreaker($this->storage);
        $cb->succeed('svc');
    }
}
