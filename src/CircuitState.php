<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker;

enum CircuitState: string
{
    case CLOSED = 'closed';
    case OPEN = 'open';
    case HALF_OPEN = 'half_open';
}
