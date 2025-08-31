<?php

namespace GabrielAnhaia\PhpCircuitBreaker;

/**
 * Native enum alternative to CircuitState for PHP 8.1+.
 * This does not replace CircuitState to keep backward compatibility.
 */
enum CircuitStateEnum: string
{
    case OPEN = 'open';
    case CLOSED = 'close';
    case HALF_OPEN = 'half_open';
}

