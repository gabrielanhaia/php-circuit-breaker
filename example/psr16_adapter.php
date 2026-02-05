<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use GabrielAnhaia\PhpCircuitBreaker\CircuitBreaker;
use GabrielAnhaia\PhpCircuitBreaker\CircuitBreakerConfig;
use GabrielAnhaia\PhpCircuitBreaker\Storage\Psr16CacheStorage;

// Example with a PSR-16 SimpleCache implementation
// Replace with your own PSR-16 implementation (e.g., Symfony Cache, Laravel Cache)
// $cache = new YourPsr16CacheImplementation();
// $storage = new Psr16CacheStorage($cache, prefix: 'cb_');

echo "PSR-16 Adapter Example\n";
echo "=======================\n\n";
echo "Usage:\n";
echo "  \$cache = new YourPsr16CacheImplementation();\n";
echo "  \$storage = new Psr16CacheStorage(\$cache, prefix: 'cb_');\n";
echo "  \$cb = new CircuitBreaker(\$storage, \$config);\n\n";
echo "The PSR-16 adapter works with any SimpleCache implementation.\n";
echo "Note: get-increment-save is not atomic. For production, prefer Redis or Memcached.\n";
