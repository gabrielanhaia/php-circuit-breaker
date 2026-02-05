<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker\Exception;

class OpenCircuitException extends CircuitBreakerException
{
    private string $serviceName;

    public function __construct(
        string $serviceName,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $this->serviceName = $serviceName;

        if ($message === '') {
            $message = sprintf('Circuit breaker is open for service "%s".', $serviceName);
        }

        parent::__construct($message, $code, $previous);
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }
}
