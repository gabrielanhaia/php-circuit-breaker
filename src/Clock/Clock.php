<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker\Clock;

/** @internal */
interface Clock
{
    public function now(): \DateTimeImmutable;
}
