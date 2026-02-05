<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker\Tests\Unit\Storage;

use GabrielAnhaia\PhpCircuitBreaker\CircuitState;
use GabrielAnhaia\PhpCircuitBreaker\Exception\StorageException;
use GabrielAnhaia\PhpCircuitBreaker\Storage\RedisStorage;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * @requires extension redis
 */
final class RedisStorageTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private \Redis|Mockery\MockInterface $redis;
    private RedisStorage $storage;

    protected function setUp(): void
    {
        $this->redis = Mockery::mock(\Redis::class);
        $this->storage = new RedisStorage($this->redis, 'cb:');
    }

    public function testGetStateReturnsOpenWhenKeyExists(): void
    {
        $this->redis->shouldReceive('exists')
            ->with('cb:svc:state:open')
            ->andReturn(true);

        $this->assertSame(CircuitState::OPEN, $this->storage->getState('svc'));
    }

    public function testGetStateReturnsHalfOpenWhenKeyExists(): void
    {
        $this->redis->shouldReceive('exists')
            ->with('cb:svc:state:open')
            ->andReturn(false);
        $this->redis->shouldReceive('exists')
            ->with('cb:svc:state:half_open')
            ->andReturn(true);

        $this->assertSame(CircuitState::HALF_OPEN, $this->storage->getState('svc'));
    }

    public function testGetStateReturnsClosedByDefault(): void
    {
        $this->redis->shouldReceive('exists')
            ->with('cb:svc:state:open')
            ->andReturn(false);
        $this->redis->shouldReceive('exists')
            ->with('cb:svc:state:half_open')
            ->andReturn(false);

        $this->assertSame(CircuitState::CLOSED, $this->storage->getState('svc'));
    }

    public function testRecordFailureUsesTransaction(): void
    {
        $this->redis->shouldReceive('multi')->once();
        $this->redis->shouldReceive('incr')->with('cb:svc:failures')->once();
        $this->redis->shouldReceive('expire')->with('cb:svc:failures', 60)->once();
        $this->redis->shouldReceive('del')->with('cb:svc:successes')->once();
        $this->redis->shouldReceive('exec')->once();

        $this->storage->recordFailure('svc', 60);
    }

    public function testGetFailureCountReturnsValue(): void
    {
        $this->redis->shouldReceive('get')
            ->with('cb:svc:failures')
            ->andReturn('5');

        $this->assertSame(5, $this->storage->getFailureCount('svc'));
    }

    public function testGetFailureCountReturnsZeroWhenNoKey(): void
    {
        $this->redis->shouldReceive('get')
            ->with('cb:svc:failures')
            ->andReturn(false);

        $this->assertSame(0, $this->storage->getFailureCount('svc'));
    }

    public function testRecordSuccessUsesTransaction(): void
    {
        $this->redis->shouldReceive('multi')->once();
        $this->redis->shouldReceive('incr')->with('cb:svc:successes')->once();
        $this->redis->shouldReceive('expire')->with('cb:svc:successes', 60)->once();
        $this->redis->shouldReceive('exec')->once();

        $this->storage->recordSuccess('svc', 60);
    }

    public function testGetSuccessCountReturnsValue(): void
    {
        $this->redis->shouldReceive('get')
            ->with('cb:svc:successes')
            ->andReturn('3');

        $this->assertSame(3, $this->storage->getSuccessCount('svc'));
    }

    public function testSetOpenSetsKeyAndCleansUp(): void
    {
        $this->redis->shouldReceive('multi')->once();
        $this->redis->shouldReceive('setex')->with('cb:svc:state:open', 30, '1')->once();
        $this->redis->shouldReceive('del')->with('cb:svc:state:half_open')->once();
        $this->redis->shouldReceive('del')->with('cb:svc:failures')->once();
        $this->redis->shouldReceive('del')->with('cb:svc:successes')->once();
        $this->redis->shouldReceive('exec')->once();

        $this->storage->setOpen('svc', 30);
    }

    public function testSetHalfOpenSetsKeyAndCleansUp(): void
    {
        $this->redis->shouldReceive('multi')->once();
        $this->redis->shouldReceive('setex')->with('cb:svc:state:half_open', 20, '1')->once();
        $this->redis->shouldReceive('del')->with('cb:svc:state:open')->once();
        $this->redis->shouldReceive('del')->with('cb:svc:successes')->once();
        $this->redis->shouldReceive('exec')->once();

        $this->storage->setHalfOpen('svc', 20);
    }

    public function testSetClosedDeletesAllKeys(): void
    {
        $this->redis->shouldReceive('del')
            ->with(
                'cb:svc:state:open',
                'cb:svc:state:half_open',
                'cb:svc:failures',
                'cb:svc:successes',
            )
            ->once();

        $this->storage->setClosed('svc');
    }

    public function testSetOverrideWithTtl(): void
    {
        $this->redis->shouldReceive('setex')
            ->with('cb:svc:override', 60, 'open')
            ->once();

        $this->storage->setOverride('svc', CircuitState::OPEN, 60);
    }

    public function testSetOverrideWithoutTtl(): void
    {
        $this->redis->shouldReceive('set')
            ->with('cb:svc:override', 'open')
            ->once();

        $this->storage->setOverride('svc', CircuitState::OPEN);
    }

    public function testClearOverrideDeletesKey(): void
    {
        $this->redis->shouldReceive('del')
            ->with('cb:svc:override')
            ->once();

        $this->storage->clearOverride('svc');
    }

    public function testGetOverrideReturnsState(): void
    {
        $this->redis->shouldReceive('get')
            ->with('cb:svc:override')
            ->andReturn('open');

        $this->assertSame(CircuitState::OPEN, $this->storage->getOverride('svc'));
    }

    public function testGetOverrideReturnsNullWhenNoKey(): void
    {
        $this->redis->shouldReceive('get')
            ->with('cb:svc:override')
            ->andReturn(false);

        $this->assertNull($this->storage->getOverride('svc'));
    }

    public function testRedisExceptionWrappedInStorageException(): void
    {
        $this->redis->shouldReceive('exists')
            ->andThrow(new \RedisException('Connection lost'));

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Connection lost');

        $this->storage->getState('svc');
    }
}
