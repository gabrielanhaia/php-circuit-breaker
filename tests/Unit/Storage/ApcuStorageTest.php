<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker\Tests\Unit\Storage;

use GabrielAnhaia\PhpCircuitBreaker\CircuitState;
use GabrielAnhaia\PhpCircuitBreaker\Storage\ApcuStorage;
use PHPUnit\Framework\TestCase;

/**
 * @requires extension apcu
 */
final class ApcuStorageTest extends TestCase
{
    private ApcuStorage $storage;

    protected function setUp(): void
    {
        if (!ini_get('apc.enable_cli')) {
            $this->markTestSkipped('APCu CLI mode is not enabled (apc.enable_cli=1 required).');
        }

        apcu_clear_cache();
        $this->storage = new ApcuStorage('test_cb:');
    }

    public function testDefaultStateIsClosed(): void
    {
        $this->assertSame(CircuitState::CLOSED, $this->storage->getState('svc'));
    }

    public function testRecordAndGetFailureCount(): void
    {
        $this->storage->recordFailure('svc', 60);
        $this->storage->recordFailure('svc', 60);

        $this->assertSame(2, $this->storage->getFailureCount('svc'));
    }

    public function testRecordFailureResetsSuccessCount(): void
    {
        $this->storage->recordSuccess('svc', 60);
        $this->storage->recordFailure('svc', 60);

        $this->assertSame(0, $this->storage->getSuccessCount('svc'));
    }

    public function testRecordAndGetSuccessCount(): void
    {
        $this->storage->recordSuccess('svc', 60);
        $this->storage->recordSuccess('svc', 60);

        $this->assertSame(2, $this->storage->getSuccessCount('svc'));
    }

    public function testSetOpenAndGetState(): void
    {
        $this->storage->setOpen('svc', 30);

        $this->assertSame(CircuitState::OPEN, $this->storage->getState('svc'));
        $this->assertSame(0, $this->storage->getFailureCount('svc'));
    }

    public function testSetHalfOpenAndGetState(): void
    {
        $this->storage->setHalfOpen('svc', 20);

        $this->assertSame(CircuitState::HALF_OPEN, $this->storage->getState('svc'));
    }

    public function testSetClosedClearsAll(): void
    {
        $this->storage->setOpen('svc', 30);
        $this->storage->setClosed('svc');

        $this->assertSame(CircuitState::CLOSED, $this->storage->getState('svc'));
        $this->assertSame(0, $this->storage->getFailureCount('svc'));
        $this->assertSame(0, $this->storage->getSuccessCount('svc'));
    }

    public function testOverrideSetAndGet(): void
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

    public function testGetOverrideReturnsNullByDefault(): void
    {
        $this->assertNull($this->storage->getOverride('svc'));
    }
}
