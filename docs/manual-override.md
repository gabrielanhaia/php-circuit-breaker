# Manual Override & State Inspection

[< Back to README](../README.md)

## Manual Override

Force a circuit into a specific state for maintenance windows, testing, or incident response:

```php
// Force the circuit open (reject all traffic)
$cb->forceState('payment-api', CircuitState::OPEN);

// Force open with auto-expiry (maintenance window)
$cb->forceState('payment-api', CircuitState::OPEN, ttl: 3600); // 1 hour

// Force closed (allow traffic despite failures)
$cb->forceState('payment-api', CircuitState::CLOSED);

// Remove override, return to automatic behavior
$cb->clearOverride('payment-api');
```

Overrides take precedence over storage state. They are stored via the same storage backend and can have optional TTLs.

## State Inspection

Query the effective state of any service at any time:

```php
$state = $cb->getState('payment-api');
// Returns: CircuitState::CLOSED, CircuitState::OPEN, or CircuitState::HALF_OPEN

// The effective state respects overrides:
$cb->forceState('payment-api', CircuitState::OPEN);
$cb->getState('payment-api'); // CircuitState::OPEN (override)
$cb->clearOverride('payment-api');
$cb->getState('payment-api'); // CircuitState::CLOSED (from storage)
```
