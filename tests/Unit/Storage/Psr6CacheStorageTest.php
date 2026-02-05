<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker\Tests\Unit\Storage;

use GabrielAnhaia\PhpCircuitBreaker\CircuitState;
use GabrielAnhaia\PhpCircuitBreaker\Storage\Psr6CacheStorage;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class Psr6CacheStorageTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private CacheItemPoolInterface|Mockery\MockInterface $cache;
    private Psr6CacheStorage $storage;

    protected function setUp(): void
    {
        $this->cache = Mockery::mock(CacheItemPoolInterface::class);
        $this->storage = new Psr6CacheStorage($this->cache, 'cb_');
    }

    private function mockItem(string $key, bool $isHit, mixed $value = null): CacheItemInterface|Mockery\MockInterface
    {
        $item = Mockery::mock(CacheItemInterface::class);
        $item->shouldReceive('isHit')->andReturn($isHit);
        if ($isHit) {
            $item->shouldReceive('get')->andReturn($value);
        }
        $this->cache->shouldReceive('getItem')->with($key)->andReturn($item);
        return $item;
    }

    public function testGetStateReturnsClosedWhenNoItem(): void
    {
        $this->mockItem('cb_svc_state', false);

        $this->assertSame(CircuitState::CLOSED, $this->storage->getState('svc'));
    }

    public function testGetStateReturnsOpenWhenSet(): void
    {
        $this->mockItem('cb_svc_state', true, 'open');

        $this->assertSame(CircuitState::OPEN, $this->storage->getState('svc'));
    }

    public function testRecordFailureIncrementsCounter(): void
    {
        $item = $this->mockItem('cb_svc_failures', true, 2);
        $item->shouldReceive('set')->with(3)->once();
        $item->shouldReceive('expiresAfter')->with(60)->once();
        $this->cache->shouldReceive('save')->with($item)->once();
        $this->cache->shouldReceive('deleteItem')->with('cb_svc_successes')->once();

        $this->storage->recordFailure('svc', 60);
    }

    public function testRecordFailureStartsFromZero(): void
    {
        $item = $this->mockItem('cb_svc_failures', false);
        $item->shouldReceive('get')->never();
        $item->shouldReceive('set')->with(1)->once();
        $item->shouldReceive('expiresAfter')->with(60)->once();
        $this->cache->shouldReceive('save')->with($item)->once();
        $this->cache->shouldReceive('deleteItem')->with('cb_svc_successes')->once();

        $this->storage->recordFailure('svc', 60);
    }

    public function testGetFailureCountReturnsValue(): void
    {
        $this->mockItem('cb_svc_failures', true, 5);

        $this->assertSame(5, $this->storage->getFailureCount('svc'));
    }

    public function testGetFailureCountReturnsZeroWhenMissing(): void
    {
        $this->mockItem('cb_svc_failures', false);

        $this->assertSame(0, $this->storage->getFailureCount('svc'));
    }

    public function testRecordSuccessIncrementsCounter(): void
    {
        $item = $this->mockItem('cb_svc_successes', true, 1);
        $item->shouldReceive('set')->with(2)->once();
        $item->shouldReceive('expiresAfter')->with(60)->once();
        $this->cache->shouldReceive('save')->with($item)->once();

        $this->storage->recordSuccess('svc', 60);
    }

    public function testSetOpenSetsStateAndCleansUp(): void
    {
        $item = Mockery::mock(CacheItemInterface::class);
        $this->cache->shouldReceive('getItem')->with('cb_svc_state')->andReturn($item);
        $item->shouldReceive('set')->with('open')->once();
        $item->shouldReceive('expiresAfter')->with(30)->once();
        $this->cache->shouldReceive('save')->with($item)->once();
        $this->cache->shouldReceive('deleteItem')->with('cb_svc_failures')->once();
        $this->cache->shouldReceive('deleteItem')->with('cb_svc_successes')->once();

        $this->storage->setOpen('svc', 30);
    }

    public function testSetClosedDeletesAll(): void
    {
        $this->cache->shouldReceive('deleteItem')->with('cb_svc_state')->once();
        $this->cache->shouldReceive('deleteItem')->with('cb_svc_failures')->once();
        $this->cache->shouldReceive('deleteItem')->with('cb_svc_successes')->once();

        $this->storage->setClosed('svc');
    }

    public function testOverrideSetAndGet(): void
    {
        $item = Mockery::mock(CacheItemInterface::class);
        $this->cache->shouldReceive('getItem')->with('cb_svc_override')->andReturn($item);
        $item->shouldReceive('set')->with('open')->once();
        $item->shouldReceive('expiresAfter')->with(60)->once();
        $this->cache->shouldReceive('save')->with($item)->once();

        $this->storage->setOverride('svc', CircuitState::OPEN, 60);
    }

    public function testGetOverrideReturnsNullWhenMissing(): void
    {
        $this->mockItem('cb_svc_override', false);

        $this->assertNull($this->storage->getOverride('svc'));
    }

    public function testGetOverrideReturnsState(): void
    {
        $this->mockItem('cb_svc_override', true, 'half_open');

        $this->assertSame(CircuitState::HALF_OPEN, $this->storage->getOverride('svc'));
    }
}
