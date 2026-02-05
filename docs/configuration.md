# Configuration

[< Back to README](../README.md)

All settings are defined via the immutable `CircuitBreakerConfig` value object:

```php
$config = new CircuitBreakerConfig(
    failureThreshold:  5,     // Failures within window to trip the circuit
    successThreshold:  1,     // Consecutive successes to close a half-open circuit
    timeWindow:        20,    // Seconds to track failures
    openTimeout:       30,    // Seconds the circuit stays open before half-open
    halfOpenTimeout:   20,    // Seconds the half-open state can last
    exceptionsEnabled: false, // Throw OpenCircuitException instead of returning false
);
```

| Parameter | Default | Description |
|-----------|---------|-------------|
| `failureThreshold` | `5` | Number of failures within `timeWindow` to open the circuit |
| `successThreshold` | `1` | Consecutive successes needed in half-open to close the circuit |
| `timeWindow` | `20` | Seconds over which failures are counted |
| `openTimeout` | `30` | How long (seconds) the circuit remains open |
| `halfOpenTimeout` | `20` | How long (seconds) the half-open state can last before auto-closing |
| `exceptionsEnabled` | `false` | If `true`, `canPass()` throws `OpenCircuitException` instead of returning `false` |

## Exception Mode

```php
use GabrielAnhaia\PhpCircuitBreaker\Exception\OpenCircuitException;

$config = new CircuitBreakerConfig(exceptionsEnabled: true);
$cb = new CircuitBreaker($storage, $config);

try {
    $cb->canPass('payment-api');
} catch (OpenCircuitException $e) {
    echo $e->getServiceName(); // "payment-api"
}
```
