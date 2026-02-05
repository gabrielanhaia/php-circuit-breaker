<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use GabrielAnhaia\PhpCircuitBreaker\CircuitBreaker;
use GabrielAnhaia\PhpCircuitBreaker\CircuitBreakerConfig;
use GabrielAnhaia\PhpCircuitBreaker\Storage\InMemoryStorage;

$storage = new InMemoryStorage();
$config = new CircuitBreakerConfig(
    failureThreshold: 3,
    successThreshold: 1,
    openTimeout: 10,
);

$cb = new CircuitBreaker($storage, $config);

$service = 'payment-api';

// Simulate a series of calls
for ($i = 1; $i <= 10; $i++) {
    echo "Request #{$i}: ";

    if (!$cb->canPass($service)) {
        echo "BLOCKED (circuit is open)\n";
        continue;
    }

    // Simulate: first 3 calls fail, rest succeed
    if ($i <= 3) {
        $cb->recordFailure($service);
        echo "FAILED (recorded failure)\n";
    } else {
        $cb->recordSuccess($service);
        echo "SUCCESS\n";
    }

    echo "  State: {$cb->getState($service)->value}\n";
}
