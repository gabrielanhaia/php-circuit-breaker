<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker\Tests\Unit\Storage;

use GabrielAnhaia\PhpCircuitBreaker\CircuitState;
use GabrielAnhaia\PhpCircuitBreaker\Storage\Psr16CacheStorage;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

final class Psr16CacheStorageTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private CacheInterface|Mockery\MockInterface $cache;
    private Psr16CacheStorage $storage;

    protected function setUp(): void
    {
        $this->cache = Mockery::mock(CacheInterface::class);
        $this->storage = new Psr16CacheStorage($this->cache, 'cb_');
    }

    public function testGetStateReturnsClosedByDefault(): void
    {
        $this->cache->shouldReceive('get')->with('cb_svc_state')->andReturn(null);

        $this->assertSame(CircuitState::CLOSED, $this->storage->getState('svc'));
    }

    public function testGetStateReturnsOpenWhenSet(): void
    {
        $this->cache->shouldReceive('get')->with('cb_svc_state')->andReturn('open');

        $this->assertSame(CircuitState::OPEN, $this->storage->getState('svc'));
    }

    public function testRecordFailureIncrementsCounter(): void
    {
        $this->cache->shouldReceive('get')->with('cb_svc_failures')->andReturn(2);
        $this->cache->shouldReceive('set')->with('cb_svc_failures', 3, 60)->once();
        $this->cache->shouldReceive('delete')->with('cb_svc_successes')->once();

        $this->storage->recordFailure('svc', 60);
    }

    public function testRecordFailureStartsFromZero(): void
    {
        $this->cache->shouldReceive('get')->with('cb_svc_failures')->andReturn(null);
        $this->cache->shouldReceive('set')->with('cb_svc_failures', 1, 60)->once();
        $this->cache->shouldReceive('delete')->with('cb_svc_successes')->once();

        $this->storage->recordFailure('svc', 60);
    }

    public function testGetFailureCountReturnsValue(): void
    {
        $this->cache->shouldReceive('get')->with('cb_svc_failures')->andReturn(5);

        $this->assertSame(5, $this->storage->getFailureCount('svc'));
    }

    public function testGetFailureCountReturnsZeroWhenNull(): void
    {
        $this->cache->shouldReceive('get')->with('cb_svc_failures')->andReturn(null);

        $this->assertSame(0, $this->storage->getFailureCount('svc'));
    }

    public function testRecordSuccessIncrementsCounter(): void
    {
        $this->cache->shouldReceive('get')->with('cb_svc_successes')->andReturn(1);
        $this->cache->shouldReceive('set')->with('cb_svc_successes', 2, 60)->once();

        $this->storage->recordSuccess('svc', 60);
    }

    public function testSetOpenSetsStateAndCleansUp(): void
    {
        $this->cache->shouldReceive('set')->with('cb_svc_state', 'open', 30)->once();
        $this->cache->shouldReceive('delete')->with('cb_svc_failures')->once();
        $this->cache->shouldReceive('delete')->with('cb_svc_successes')->once();

        $this->storage->setOpen('svc', 30);
    }

    public function testSetHalfOpenSetsState(): void
    {
        $this->cache->shouldReceive('set')->with('cb_svc_state', 'half_open', 20)->once();
        $this->cache->shouldReceive('delete')->with('cb_svc_successes')->once();

        $this->storage->setHalfOpen('svc', 20);
    }

    public function testSetClosedDeletesAll(): void
    {
        $this->cache->shouldReceive('delete')->with('cb_svc_state')->once();
        $this->cache->shouldReceive('delete')->with('cb_svc_failures')->once();
        $this->cache->shouldReceive('delete')->with('cb_svc_successes')->once();

        $this->storage->setClosed('svc');
    }

    public function testOverrideSetAndGet(): void
    {
        $this->cache->shouldReceive('set')->with('cb_svc_override', 'open', 60)->once();

        $this->storage->setOverride('svc', CircuitState::OPEN, 60);
    }

    public function testOverrideWithoutTtl(): void
    {
        $this->cache->shouldReceive('set')->with('cb_svc_override', 'open', null)->once();

        $this->storage->setOverride('svc', CircuitState::OPEN);
    }

    public function testClearOverride(): void
    {
        $this->cache->shouldReceive('delete')->with('cb_svc_override')->once();

        $this->storage->clearOverride('svc');
    }

    public function testGetOverrideReturnsState(): void
    {
        $this->cache->shouldReceive('get')->with('cb_svc_override')->andReturn('open');

        $this->assertSame(CircuitState::OPEN, $this->storage->getOverride('svc'));
    }

    public function testGetOverrideReturnsNullWhenNotSet(): void
    {
        $this->cache->shouldReceive('get')->with('cb_svc_override')->andReturn(null);

        $this->assertNull($this->storage->getOverride('svc'));
    }
}
