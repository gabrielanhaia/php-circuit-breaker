<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker\Clock;

/** @internal */
final class TestClock implements Clock
{
    private \DateTimeImmutable $current;

    public function __construct(?\DateTimeImmutable $start = null)
    {
        $this->current = $start ?? new \DateTimeImmutable();
    }

    public function now(): \DateTimeImmutable
    {
        return $this->current;
    }

    public function advance(int $seconds): void
    {
        $this->current = $this->current->modify("+{$seconds} seconds");
    }
}
