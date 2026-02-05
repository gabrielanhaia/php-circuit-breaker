<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker\Clock;

/** @internal */
final class SystemClock implements Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
