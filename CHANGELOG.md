# Changelog

## 3.0.0

### Breaking Changes
- Rename `CircuitStateEnum` → `CircuitState`; backing value `'close'` → `'closed'`
- Replace `CircuitBreakerAdapter` abstract class with `CircuitBreakerStorageInterface`
- Typed constructor: `new CircuitBreaker($storage, $config, $dispatcher)`
- Remove `Alert` interface — use event listeners instead
- Rename `AdapterException` → `StorageException`, `CircuitException` → `OpenCircuitException`
- Move `ext-redis` from `require` to `suggest`
- Remove `KeyHelper` class; Redis adapter uses `INCR`-based counting
- Test namespace changed to `GabrielAnhaia\PhpCircuitBreaker\Tests\`

### New Features
- **Immutable config**: `CircuitBreakerConfig` value object with named arguments
- **Success threshold**: configurable consecutive successes to close half-open circuits
- **Manual override**: `forceState()` / `clearOverride()` with optional TTL
- **State inspection**: `getState()` returns effective state (override > storage)
- **Event system**: `CircuitOpenedEvent`, `CircuitClosedEvent`, `CircuitHalfOpenEvent`, `FailureRecordedEvent`, `SuccessRecordedEvent`
- **SimpleEventDispatcher**: built-in lightweight dispatcher with parent-class listener support
- **PSR-14 bridge**: `Psr14EventDispatcherBridge` wraps any PSR-14 dispatcher
- **5 new storage adapters**: `InMemoryStorage`, `ApcuStorage`, `MemcachedStorage`, `Psr6CacheStorage`, `Psr16CacheStorage`
- **Clock abstraction**: `TestClock` for deterministic time-based testing

### Quality
- PHPStan at level `max`
- PHP-CS-Fixer with PER-CS + PHP 8.1 migration rules
- CI: 3 jobs (tests on PHP 8.1–8.4, static analysis, code style)
- Coverage uploaded to Codecov

### Deprecated
- `failed()` → use `recordFailure()` (removed in v4)
- `succeed()` → use `recordSuccess()` (removed in v4)

## 2.0.0
- Require PHP 8.1+
- Replace `eloquent/enumeration` with native PHP enums
- Update tests for PHPUnit 10
- Migrate CI to GitHub Actions
- Redis operations use `setEx` for TTL handling
- Documentation overhaul

## 1.x
- PHP 7.4+/8.0+ support
- `CircuitState` backed by `eloquent/enumeration`
- Travis CI
- Initial release and documentation
