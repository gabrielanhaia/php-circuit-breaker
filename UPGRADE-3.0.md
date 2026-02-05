# Upgrading from v2.x to v3.0

This guide covers all breaking changes and how to migrate your code.

## PHP Version

v3.0 requires PHP 8.1+ (same as v2). No PHP version change needed.

## Constructor Changes

**Before (v2):**
```php
use GabrielAnhaia\PhpCircuitBreaker\Adapter\Redis\RedisCircuitBreaker;
use GabrielAnhaia\PhpCircuitBreaker\CircuitBreaker;

$adapter = new RedisCircuitBreaker($redis);
$cb = new CircuitBreaker($adapter, [
    'exceptions_on' => false,
    'time_window' => 20,
    'time_out_open' => 30,
    'time_out_half_open' => 20,
    'total_failures' => 5,
]);
```

**After (v3):**
```php
use GabrielAnhaia\PhpCircuitBreaker\CircuitBreaker;
use GabrielAnhaia\PhpCircuitBreaker\CircuitBreakerConfig;
use GabrielAnhaia\PhpCircuitBreaker\Storage\RedisStorage;

$storage = new RedisStorage($redis);
$config = new CircuitBreakerConfig(
    failureThreshold: 5,
    timeWindow: 20,
    openTimeout: 30,
    halfOpenTimeout: 20,
    exceptionsEnabled: false,
);
$cb = new CircuitBreaker($storage, $config);
```

## Enum Rename

| v2 | v3 |
|----|-----|
| `CircuitStateEnum` | `CircuitState` |
| `CircuitStateEnum::CLOSED` (value: `'close'`) | `CircuitState::CLOSED` (value: `'closed'`) |
| `CircuitStateEnum::OPEN` | `CircuitState::OPEN` |
| `CircuitStateEnum::HALF_OPEN` | `CircuitState::HALF_OPEN` |

If you used the backing string value `'close'`, update it to `'closed'`.

## Adapter → Storage

The abstract class `CircuitBreakerAdapter` has been replaced by the `CircuitBreakerStorageInterface` interface.

| v2 Class | v3 Class |
|----------|----------|
| `GabrielAnhaia\PhpCircuitBreaker\Contract\CircuitBreakerAdapter` | `GabrielAnhaia\PhpCircuitBreaker\Storage\CircuitBreakerStorageInterface` |
| `GabrielAnhaia\PhpCircuitBreaker\Adapter\Redis\RedisCircuitBreaker` | `GabrielAnhaia\PhpCircuitBreaker\Storage\RedisStorage` |

If you wrote a custom adapter extending `CircuitBreakerAdapter`, implement `CircuitBreakerStorageInterface` instead. The new interface has additional methods: `recordSuccess`, `getSuccessCount`, `setOverride`, `clearOverride`, `getOverride`.

## Exception Renames

| v2 | v3 |
|----|-----|
| `AdapterException` | `StorageException` |
| `CircuitException` | `OpenCircuitException` |

Both now extend `CircuitBreakerException` (which extends `\RuntimeException`).

## Alert Interface → Event System

The `Alert` interface has been removed. Use the event system instead:

**Before (v2):**
```php
use GabrielAnhaia\PhpCircuitBreaker\Contract\Alert;

class MyAlert implements Alert {
    public function emmitOpenCircuit(string $serviceName) {
        // handle open circuit
    }
}

$cb = new CircuitBreaker($adapter, $settings, new MyAlert());
```

**After (v3):**
```php
use GabrielAnhaia\PhpCircuitBreaker\Event\SimpleEventDispatcher;
use GabrielAnhaia\PhpCircuitBreaker\Event\CircuitOpenedEvent;

$dispatcher = new SimpleEventDispatcher();
$dispatcher->addListener(CircuitOpenedEvent::class, function (CircuitOpenedEvent $e): void {
    // handle open circuit
    error_log("Circuit opened: {$e->getServiceName()}");
});

$cb = new CircuitBreaker($storage, $config, $dispatcher);
```

## Method Renames

The old method names still work (deprecated, removed in v4):

| v2 | v3 | Status |
|----|-----|--------|
| `$cb->failed($service)` | `$cb->recordFailure($service)` | Deprecated alias kept |
| `$cb->succeed($service)` | `$cb->recordSuccess($service)` | Deprecated alias kept |
| `$cb->canPass($service)` | `$cb->canPass($service)` | Unchanged |

## Removed Files

The following files/directories no longer exist:

- `src/Contract/` (entire directory)
- `src/Adapter/` (entire directory)
- `src/CircuitStateEnum.php`
- `src/Exception/AdapterException.php`
- `src/Exception/CircuitException.php`
- `tests/TestCase.php`
- `tests/Unit/Adapter/`
- `phpunit.xml` (replaced by `phpunit.xml.dist`)

## ext-redis No Longer Required

`ext-redis` moved from `require` to `suggest`. If you use `RedisStorage`, ensure the extension is installed. Otherwise, you can now use `InMemoryStorage`, `ApcuStorage`, `MemcachedStorage`, `Psr6CacheStorage`, or `Psr16CacheStorage` without the Redis extension.

## New Features

- **Success threshold** — configure `successThreshold` (default: 1) to require multiple successes before closing
- **Manual override** — `forceState()` / `clearOverride()` for maintenance windows
- **Event system** — listen for `CircuitOpenedEvent`, `CircuitClosedEvent`, `FailureRecordedEvent`, etc.
- **State inspection** — `getState()` returns the effective state (override > storage)
- **5 new storage adapters** — InMemory, APCu, Memcached, PSR-6, PSR-16

## Test Namespace Change

```
Tests\              →  GabrielAnhaia\PhpCircuitBreaker\Tests\
```

Update your `phpunit.xml` if you extended the test base class.
