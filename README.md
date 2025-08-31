![CI](https://github.com/gabrielanhaia/php-circuit-breaker/actions/workflows/ci.yml/badge.svg)
![Licence](https://img.shields.io/badge/licence-MIT-blue)

# PHP Circuit Breaker

Resiliency pattern for PHP microservices. This library implements a circuit breaker using Redis for state tracking. It protects your services from cascading failures by stopping calls to unhealthy dependencies and gradually allowing traffic as they recover.

Learn more about circuit breakers: https://martinfowler.com/bliki/CircuitBreaker.html

## Features
- Simple API: `canPass`, `succeed`, `failed`
- Redis-backed state with configurable windows/timeouts
- Pluggable alert hook to notify when circuits open
- PHP 8.1+ native enum for states in v2

## Versions
- 2.x (current): PHP 8.1+, native enums, PHPUnit 10, GitHub Actions
- 1.x (legacy): PHP 7.4+/8.0+, uses `eloquent/enumeration` and Travis CI

See CHANGELOG for details and migration notes.

## Requirements
- PHP 8.1+
- Redis server
- `ext-redis` PHP extension

## Install
- v2 (recommended): `composer require gabrielanhaia/php-circuit-breaker:^2.0`
- v1 (legacy): `composer require gabrielanhaia/php-circuit-breaker:^1.0`

## Quick Start
```php
use GabrielAnhaia\PhpCircuitBreaker\Adapter\Redis\RedisCircuitBreaker;
use GabrielAnhaia\PhpCircuitBreaker\CircuitBreaker;

$settings = [
    'exceptions_on' => false,
    'time_window' => 20,
    'time_out_open' => 30,
    'time_out_half_open' => 20,
    'total_failures' => 5,
];

$redis = new \Redis();
$redis->connect('127.0.0.1', 6379);

$driver = new RedisCircuitBreaker($redis);
$cb = new CircuitBreaker($driver, $settings);

$service = 'PAYMENTS_API';
if (!$cb->canPass($service)) {
    // Short-circuit
    return;
}

try {
    // Call dependency...
    $cb->succeed($service);
} catch (\Throwable $e) {
    $cb->failed($service);
}
```

## Configuration
- `exceptions_on` (bool): throw when circuit is open (default: false)
- `time_window` (int): seconds to track failures (default: 20)
- `time_out_open` (int): seconds to keep circuit open (default: 30)
- `time_out_half_open` (int): additional seconds before half-open closes (default: 20)
- `total_failures` (int): failures within window to open (default: 5)

## Circuit State
In v2+, states are represented with a native enum: `GabrielAnhaia\PhpCircuitBreaker\CircuitStateEnum` with cases `OPEN`, `CLOSED`, `HALF_OPEN`.
You don’t normally need to consume this directly unless you’re writing custom adapters.

## Alerts
Implement `GabrielAnhaia\PhpCircuitBreaker\Contract\Alert` and pass it to `CircuitBreaker` to receive callbacks when a circuit opens.

```php
use GabrielAnhaia\PhpCircuitBreaker\Contract\Alert;

class LoggerAlert implements Alert {
    public function emmitOpenCircuit(string $serviceName)
    {
        error_log("Circuit opened: {$serviceName}");
    }
}
```

## Redis Keys
Keys are namespaced per service:
- `circuit_breaker:{SERVICE}:open`
- `circuit_breaker:{SERVICE}:half_open`
- `circuit_breaker:{SERVICE}:total_failures:*`

## Migration (1.x → 2.x)
Breaking changes:
- Replace `CircuitState::OPEN()` style calls with native enum values if referenced in your code, e.g. `CircuitStateEnum::OPEN`.
- `CircuitBreakerAdapter::getState(string): CircuitStateEnum` now returns the native enum.

Otherwise, `CircuitBreaker` public API is unchanged.

## Development
- Run tests: `vendor/bin/phpunit --configuration phpunit.xml`
- GitHub Actions runs on PHP 8.1–8.4

## License
MIT

---

Created by: Gabriel Anhaia — https://www.linkedin.com/in/gabrielanhaia

