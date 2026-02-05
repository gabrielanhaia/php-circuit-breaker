<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker\Tests\Unit\Exception;

use GabrielAnhaia\PhpCircuitBreaker\Exception\CircuitBreakerException;
use GabrielAnhaia\PhpCircuitBreaker\Exception\OpenCircuitException;
use PHPUnit\Framework\TestCase;

final class OpenCircuitExceptionTest extends TestCase
{
    public function testServiceNameIsPreserved(): void
    {
        $exception = new OpenCircuitException('my-service');

        $this->assertSame('my-service', $exception->getServiceName());
    }

    public function testDefaultMessage(): void
    {
        $exception = new OpenCircuitException('payment-api');

        $this->assertSame(
            'Circuit breaker is open for service "payment-api".',
            $exception->getMessage(),
        );
    }

    public function testCustomMessage(): void
    {
        $exception = new OpenCircuitException('svc', 'Custom message');

        $this->assertSame('Custom message', $exception->getMessage());
    }

    public function testExtendsCircuitBreakerException(): void
    {
        $exception = new OpenCircuitException('svc');

        $this->assertInstanceOf(CircuitBreakerException::class, $exception);
    }
}
