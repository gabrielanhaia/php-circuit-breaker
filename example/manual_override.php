<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use GabrielAnhaia\PhpCircuitBreaker\CircuitBreaker;
use GabrielAnhaia\PhpCircuitBreaker\CircuitBreakerConfig;
use GabrielAnhaia\PhpCircuitBreaker\CircuitState;
use GabrielAnhaia\PhpCircuitBreaker\Storage\InMemoryStorage;

$storage = new InMemoryStorage();
$config = new CircuitBreakerConfig();
$cb = new CircuitBreaker($storage, $config);

$service = 'inventory-api';

echo "Initial state: {$cb->getState($service)->value}\n";
echo "Can pass: " . ($cb->canPass($service) ? 'yes' : 'no') . "\n\n";

// Force the circuit open (e.g., planned maintenance)
echo "Forcing circuit OPEN for maintenance...\n";
$cb->forceState($service, CircuitState::OPEN);
echo "State: {$cb->getState($service)->value}\n";
echo "Can pass: " . ($cb->canPass($service) ? 'yes' : 'no') . "\n\n";

// Clear the override
echo "Maintenance complete. Clearing override...\n";
$cb->clearOverride($service);
echo "State: {$cb->getState($service)->value}\n";
echo "Can pass: " . ($cb->canPass($service) ? 'yes' : 'no') . "\n\n";

// Force closed (bypass the circuit breaker)
echo "Forcing circuit CLOSED (bypass mode)...\n";
$cb->forceState($service, CircuitState::CLOSED);
echo "State: {$cb->getState($service)->value}\n";
echo "Can pass: " . ($cb->canPass($service) ? 'yes' : 'no') . "\n";
