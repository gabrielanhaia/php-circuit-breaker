<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker\Tests\Unit\Storage;

use GabrielAnhaia\PhpCircuitBreaker\CircuitState;
use GabrielAnhaia\PhpCircuitBreaker\Clock\TestClock;
use GabrielAnhaia\PhpCircuitBreaker\Storage\InMemoryStorage;
use PHPUnit\Framework\TestCase;

final class InMemoryStorageTest extends TestCase
{
    private TestClock $clock;
    private InMemoryStorage $storage;

    protected function setUp(): void
    {
        $this->clock = new TestClock(new \DateTimeImmutable('2024-01-01 00:00:00'));
        $this->storage = new InMemoryStorage($this->clock);
    }

    public function testDefaultStateIsClosed(): void
    {
        $this->assertSame(CircuitState::CLOSED, $this->storage->getState('svc'));
    }

    public function testRecordFailureIncrementsCount(): void
    {
        $this->storage->recordFailure('svc', 60);
        $this->storage->recordFailure('svc', 60);
        $this->storage->recordFailure('svc', 60);

        $this->assertSame(3, $this->storage->getFailureCount('svc'));
    }

    public function testFailuresExpireOutsideTimeWindow(): void
    {
        $this->storage->recordFailure('svc', 10);
        $this->storage->recordFailure('svc', 10);

        $this->clock->advance(11);

        $this->storage->recordFailure('svc', 10);

        $this->assertSame(1, $this->storage->getFailureCount('svc'));
    }

    public function testRecordSuccessIncrementsCount(): void
    {
        $this->storage->recordSuccess('svc', 60);
        $this->storage->recordSuccess('svc', 60);

        $this->assertSame(2, $this->storage->getSuccessCount('svc'));
    }

    public function testRecordFailureResetsSuccessCount(): void
    {
        $this->storage->recordSuccess('svc', 60);
        $this->storage->recordSuccess('svc', 60);
        $this->storage->recordFailure('svc', 60);

        $this->assertSame(0, $this->storage->getSuccessCount('svc'));
    }

    public function testSetOpenChangesState(): void
    {
        $this->storage->setOpen('svc', 30);

        $this->assertSame(CircuitState::OPEN, $this->storage->getState('svc'));
    }

    public function testOpenExpiresIntoHalfOpen(): void
    {
        $this->storage->setOpen('svc', 30);

        $this->clock->advance(31);

        $this->assertSame(CircuitState::HALF_OPEN, $this->storage->getState('svc'));
    }

    public function testSetHalfOpenChangesState(): void
    {
        $this->storage->setHalfOpen('svc', 20);

        $this->assertSame(CircuitState::HALF_OPEN, $this->storage->getState('svc'));
    }

    public function testHalfOpenExpiresToClosed(): void
    {
        $this->storage->setHalfOpen('svc', 20);

        $this->clock->advance(21);

        $this->assertSame(CircuitState::CLOSED, $this->storage->getState('svc'));
    }

    public function testSetClosedResetsState(): void
    {
        $this->storage->setOpen('svc', 30);
        $this->storage->setClosed('svc');

        $this->assertSame(CircuitState::CLOSED, $this->storage->getState('svc'));
        $this->assertSame(0, $this->storage->getFailureCount('svc'));
        $this->assertSame(0, $this->storage->getSuccessCount('svc'));
    }

    public function testSetOpenResetsCounters(): void
    {
        $this->storage->recordFailure('svc', 60);
        $this->storage->recordSuccess('svc', 60);
        $this->storage->setOpen('svc', 30);

        $this->assertSame(0, $this->storage->getFailureCount('svc'));
        $this->assertSame(0, $this->storage->getSuccessCount('svc'));
    }

    public function testSetHalfOpenResetsSuccessCount(): void
    {
        $this->storage->recordSuccess('svc', 60);
        $this->storage->setHalfOpen('svc', 20);

        $this->assertSame(0, $this->storage->getSuccessCount('svc'));
    }

    public function testOverrideReturnsNullByDefault(): void
    {
        $this->assertNull($this->storage->getOverride('svc'));
    }

    public function testSetAndGetOverride(): void
    {
        $this->storage->setOverride('svc', CircuitState::OPEN);

        $this->assertSame(CircuitState::OPEN, $this->storage->getOverride('svc'));
    }

    public function testClearOverride(): void
    {
        $this->storage->setOverride('svc', CircuitState::OPEN);
        $this->storage->clearOverride('svc');

        $this->assertNull($this->storage->getOverride('svc'));
    }

    public function testOverrideExpiresAfterTtl(): void
    {
        $this->storage->setOverride('svc', CircuitState::OPEN, 60);

        $this->assertSame(CircuitState::OPEN, $this->storage->getOverride('svc'));

        $this->clock->advance(61);

        $this->assertNull($this->storage->getOverride('svc'));
    }

    public function testOverrideWithoutTtlDoesNotExpire(): void
    {
        $this->storage->setOverride('svc', CircuitState::OPEN);

        $this->clock->advance(9999);

        $this->assertSame(CircuitState::OPEN, $this->storage->getOverride('svc'));
    }

    public function testServicesAreIsolated(): void
    {
        $this->storage->recordFailure('svc-a', 60);
        $this->storage->setOpen('svc-b', 30);

        $this->assertSame(1, $this->storage->getFailureCount('svc-a'));
        $this->assertSame(0, $this->storage->getFailureCount('svc-b'));
        $this->assertSame(CircuitState::CLOSED, $this->storage->getState('svc-a'));
        $this->assertSame(CircuitState::OPEN, $this->storage->getState('svc-b'));
    }

    public function testGetFailureCountReturnsZeroForUnknownService(): void
    {
        $this->assertSame(0, $this->storage->getFailureCount('unknown'));
    }

    public function testGetSuccessCountReturnsZeroForUnknownService(): void
    {
        $this->assertSame(0, $this->storage->getSuccessCount('unknown'));
    }
}
