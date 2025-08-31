# Migration Guide: 1.x → 2.x

Version 2.0 modernizes the library for PHP 8.1+ and native enums. This guide summarizes the required changes if your code referenced internal types directly.

## Requirements

- PHP 8.1+
- PHPUnit 10 for development
- `ext-redis`

## Install

```
composer require gabrielanhaia/php-circuit-breaker:^2.0
```

## Circuit State Type

- 1.x: `GabrielAnhaia\PhpCircuitBreaker\CircuitState` (eloquent/enumeration)
- 2.x: `GabrielAnhaia\PhpCircuitBreaker\CircuitStateEnum` (native PHP enum)

If you used the state type in your own code:

```php
// 1.x
use GabrielAnhaia\PhpCircuitBreaker\CircuitState;
if ($adapter->getState('service') === CircuitState::OPEN()) { /* ... */ }

// 2.x
use GabrielAnhaia\PhpCircuitBreaker\CircuitStateEnum;
if ($adapter->getState('service') === CircuitStateEnum::OPEN) { /* ... */ }
```

## Adapter Signature

`CircuitBreakerAdapter::getState(string $service): CircuitStateEnum`

If you implement a custom adapter, update the return type and return a `CircuitStateEnum` case.

## Redis TTL API

Internal Redis writes now use `setEx($key, $ttl, $value)` for forward compatibility with recent `phpredis` versions. No action required unless you copied that code.

## Public API Stability

`CircuitBreaker` methods are unchanged:

- `canPass(string $service): bool`
- `succeed(string $service): void`
- `failed(string $service): void`

Optional alert hook remains the same (`Contract\Alert`).

## CI and Tooling

- Travis CI has been removed. GitHub Actions workflow is included for PHP 8.1–8.4.
- PHPUnit upgraded to v10. If you run tests locally, ensure your dev environment uses PHP 8.1+.

## Questions

Open an issue if you need help upgrading a custom adapter or have suggestions for the next release.

