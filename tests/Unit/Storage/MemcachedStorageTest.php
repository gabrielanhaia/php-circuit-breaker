<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker\Tests\Unit\Storage;

use GabrielAnhaia\PhpCircuitBreaker\CircuitState;
use GabrielAnhaia\PhpCircuitBreaker\Storage\MemcachedStorage;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * @requires extension memcached
 */
final class MemcachedStorageTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private \Memcached|Mockery\MockInterface $memcached;
    private MemcachedStorage $storage;

    protected function setUp(): void
    {
        $this->memcached = Mockery::mock(\Memcached::class);
        $this->storage = new MemcachedStorage($this->memcached, 'cb:');
    }

    public function testGetStateReturnsClosedWhenNotFound(): void
    {
        $this->memcached->shouldReceive('get')->with('cb:svc:state')->andReturn(false);
        $this->memcached->shouldReceive('getResultCode')->andReturn(\Memcached::RES_NOTFOUND);

        $this->assertSame(CircuitState::CLOSED, $this->storage->getState('svc'));
    }

    public function testGetStateReturnsOpenWhenSet(): void
    {
        $this->memcached->shouldReceive('get')->with('cb:svc:state')->andReturn('open');
        $this->memcached->shouldReceive('getResultCode')->andReturn(\Memcached::RES_SUCCESS);

        $this->assertSame(CircuitState::OPEN, $this->storage->getState('svc'));
    }

    public function testRecordFailureIncrementsOrSets(): void
    {
        $this->memcached->shouldReceive('increment')->with('cb:svc:failures')->andReturn(false);
        $this->memcached->shouldReceive('set')->with('cb:svc:failures', 1, 60)->once();
        $this->memcached->shouldReceive('delete')->with('cb:svc:successes')->once();

        $this->storage->recordFailure('svc', 60);
    }

    public function testRecordFailureIncrements(): void
    {
        $this->memcached->shouldReceive('increment')->with('cb:svc:failures')->andReturn(2);
        $this->memcached->shouldReceive('touch')->with('cb:svc:failures', 60)->once();
        $this->memcached->shouldReceive('delete')->with('cb:svc:successes')->once();

        $this->storage->recordFailure('svc', 60);
    }

    public function testGetFailureCountReturnsValue(): void
    {
        $this->memcached->shouldReceive('get')->with('cb:svc:failures')->andReturn(3);
        $this->memcached->shouldReceive('getResultCode')->andReturn(\Memcached::RES_SUCCESS);

        $this->assertSame(3, $this->storage->getFailureCount('svc'));
    }

    public function testGetFailureCountReturnsZeroWhenNotFound(): void
    {
        $this->memcached->shouldReceive('get')->with('cb:svc:failures')->andReturn(false);
        $this->memcached->shouldReceive('getResultCode')->andReturn(\Memcached::RES_NOTFOUND);

        $this->assertSame(0, $this->storage->getFailureCount('svc'));
    }

    public function testRecordSuccessIncrementsOrSets(): void
    {
        $this->memcached->shouldReceive('increment')->with('cb:svc:successes')->andReturn(false);
        $this->memcached->shouldReceive('set')->with('cb:svc:successes', 1, 60)->once();

        $this->storage->recordSuccess('svc', 60);
    }

    public function testSetOpenSetsStateAndCleansUp(): void
    {
        $this->memcached->shouldReceive('set')->with('cb:svc:state', 'open', 30)->once();
        $this->memcached->shouldReceive('delete')->with('cb:svc:failures')->once();
        $this->memcached->shouldReceive('delete')->with('cb:svc:successes')->once();

        $this->storage->setOpen('svc', 30);
    }

    public function testSetHalfOpenSetsStateAndCleansUp(): void
    {
        $this->memcached->shouldReceive('set')->with('cb:svc:state', 'half_open', 20)->once();
        $this->memcached->shouldReceive('delete')->with('cb:svc:successes')->once();

        $this->storage->setHalfOpen('svc', 20);
    }

    public function testSetClosedDeletesAll(): void
    {
        $this->memcached->shouldReceive('delete')->with('cb:svc:state')->once();
        $this->memcached->shouldReceive('delete')->with('cb:svc:failures')->once();
        $this->memcached->shouldReceive('delete')->with('cb:svc:successes')->once();

        $this->storage->setClosed('svc');
    }

    public function testOverrideSetAndGet(): void
    {
        $this->memcached->shouldReceive('set')
            ->with('cb:svc:override', 'open', 60)
            ->once();

        $this->storage->setOverride('svc', CircuitState::OPEN, 60);
    }

    public function testClearOverride(): void
    {
        $this->memcached->shouldReceive('delete')
            ->with('cb:svc:override')
            ->once();

        $this->storage->clearOverride('svc');
    }

    public function testGetOverrideReturnsState(): void
    {
        $this->memcached->shouldReceive('get')->with('cb:svc:override')->andReturn('open');
        $this->memcached->shouldReceive('getResultCode')->andReturn(\Memcached::RES_SUCCESS);

        $this->assertSame(CircuitState::OPEN, $this->storage->getOverride('svc'));
    }

    public function testGetOverrideReturnsNullWhenNotFound(): void
    {
        $this->memcached->shouldReceive('get')->with('cb:svc:override')->andReturn(false);
        $this->memcached->shouldReceive('getResultCode')->andReturn(\Memcached::RES_NOTFOUND);

        $this->assertNull($this->storage->getOverride('svc'));
    }
}
