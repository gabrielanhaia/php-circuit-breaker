<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker\Tests\Unit;

use GabrielAnhaia\PhpCircuitBreaker\CircuitBreakerConfig;
use PHPUnit\Framework\TestCase;

final class CircuitBreakerConfigTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $config = new CircuitBreakerConfig();

        $this->assertSame(5, $config->getFailureThreshold());
        $this->assertSame(1, $config->getSuccessThreshold());
        $this->assertSame(20, $config->getTimeWindow());
        $this->assertSame(30, $config->getOpenTimeout());
        $this->assertSame(20, $config->getHalfOpenTimeout());
        $this->assertFalse($config->isExceptionsEnabled());
    }

    public function testCustomValues(): void
    {
        $config = new CircuitBreakerConfig(
            failureThreshold: 10,
            successThreshold: 3,
            timeWindow: 60,
            openTimeout: 120,
            halfOpenTimeout: 45,
            exceptionsEnabled: true,
        );

        $this->assertSame(10, $config->getFailureThreshold());
        $this->assertSame(3, $config->getSuccessThreshold());
        $this->assertSame(60, $config->getTimeWindow());
        $this->assertSame(120, $config->getOpenTimeout());
        $this->assertSame(45, $config->getHalfOpenTimeout());
        $this->assertTrue($config->isExceptionsEnabled());
    }
}
