<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use GabrielAnhaia\PhpCircuitBreaker\CircuitBreaker;
use GabrielAnhaia\PhpCircuitBreaker\CircuitBreakerConfig;
use GabrielAnhaia\PhpCircuitBreaker\Storage\RedisStorage;

// Connect to Redis
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

$storage = new RedisStorage($redis, prefix: 'myapp:cb:');
$config = new CircuitBreakerConfig(
    failureThreshold: 5,
    openTimeout: 30,
);

$cb = new CircuitBreaker($storage, $config);

$service = 'user-service';

if (!$cb->canPass($service)) {
    echo "Circuit is open for {$service}. Using fallback.\n";
    exit(1);
}

try {
    // Simulate calling the service
    echo "Calling {$service}...\n";
    // $result = httpClient->get('https://user-service/api/users');
    $cb->recordSuccess($service);
    echo "Call succeeded. State: {$cb->getState($service)->value}\n";
} catch (Throwable $e) {
    $cb->recordFailure($service);
    echo "Call failed: {$e->getMessage()}. State: {$cb->getState($service)->value}\n";
}
