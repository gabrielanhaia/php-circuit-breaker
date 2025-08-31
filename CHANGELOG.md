# Changelog

## 2.0.0
- Require PHP 8.1+
- Replace `eloquent/enumeration` with native PHP enums
  - New `GabrielAnhaia\\PhpCircuitBreaker\\CircuitStateEnum`
  - `CircuitBreakerAdapter::getState()` returns `CircuitStateEnum`
- Update tests for PHPUnit 10
- Migrate CI to GitHub Actions
- Redis operations use `setEx` for TTL handling
- Documentation overhaul

## 1.x
- PHP 7.4+/8.0+ support
- `CircuitState` backed by `eloquent/enumeration`
- Travis CI
- Initial release and documentation
