# Architecture

[< Back to README](../README.md)

## Class Diagram

```mermaid
classDiagram
    class CircuitBreaker {
        +canPass(service) bool
        +recordFailure(service) void
        +recordSuccess(service) void
        +getState(service) CircuitState
        +forceState(service, state, ttl?) void
        +clearOverride(service) void
    }

    class CircuitBreakerConfig {
        +failureThreshold int
        +successThreshold int
        +timeWindow int
        +openTimeout int
        +halfOpenTimeout int
        +exceptionsEnabled bool
    }

    class CircuitState {
        <<enum>>
        CLOSED
        OPEN
        HALF_OPEN
    }

    class CircuitBreakerStorageInterface {
        <<interface>>
        +getState(service) CircuitState
        +recordFailure(service, ttl) void
        +getFailureCount(service) int
        +recordSuccess(service, ttl) void
        +getSuccessCount(service) int
        +setOpen(service, ttl) void
        +setHalfOpen(service, ttl) void
        +setClosed(service) void
        +setOverride(service, state, ttl?) void
        +clearOverride(service) void
        +getOverride(service) CircuitState?
    }

    class EventDispatcherInterface {
        <<interface>>
        +addListener(eventClass, listener) void
        +dispatch(event) void
    }

    CircuitBreaker --> CircuitBreakerConfig
    CircuitBreaker --> CircuitBreakerStorageInterface
    CircuitBreaker --> EventDispatcherInterface
    CircuitBreaker --> CircuitState

    InMemoryStorage ..|> CircuitBreakerStorageInterface
    RedisStorage ..|> CircuitBreakerStorageInterface
    ApcuStorage ..|> CircuitBreakerStorageInterface
    MemcachedStorage ..|> CircuitBreakerStorageInterface
    Psr6CacheStorage ..|> CircuitBreakerStorageInterface
    Psr16CacheStorage ..|> CircuitBreakerStorageInterface

    SimpleEventDispatcher ..|> EventDispatcherInterface
    Psr14EventDispatcherBridge ..|> EventDispatcherInterface
```

## Directory Structure

```
src/
├── CircuitBreaker.php              # Main orchestrator
├── CircuitBreakerConfig.php        # Immutable config value object
├── CircuitState.php                # State enum (CLOSED, OPEN, HALF_OPEN)
├── Clock/
│   ├── Clock.php                   # Internal clock interface
│   ├── SystemClock.php             # Production clock
│   └── TestClock.php               # Manual time control for tests
├── Storage/
│   ├── CircuitBreakerStorageInterface.php
│   ├── InMemoryStorage.php
│   ├── RedisStorage.php
│   ├── ApcuStorage.php
│   ├── MemcachedStorage.php
│   ├── Psr6CacheStorage.php
│   └── Psr16CacheStorage.php
├── Event/
│   ├── CircuitBreakerEvent.php     # Abstract base event
│   ├── CircuitOpenedEvent.php
│   ├── CircuitClosedEvent.php
│   ├── CircuitHalfOpenEvent.php
│   ├── FailureRecordedEvent.php
│   ├── SuccessRecordedEvent.php
│   ├── EventDispatcherInterface.php
│   ├── SimpleEventDispatcher.php
│   └── Psr14EventDispatcherBridge.php
└── Exception/
    ├── CircuitBreakerException.php # Base exception
    ├── OpenCircuitException.php    # Thrown when circuit is open
    └── StorageException.php        # Storage backend errors
```

## Request Flow

```mermaid
flowchart TD
    A[canPass?] --> B{Override?}
    B -->|Yes| C{Override State}
    C -->|OPEN| D[Reject / Throw]
    C -->|CLOSED/HALF_OPEN| E[Allow]
    B -->|No| F{Storage State}
    F -->|OPEN| D
    F -->|CLOSED| E
    F -->|HALF_OPEN| E
    E --> G[Call Service]
    G -->|Success| H[recordSuccess]
    G -->|Failure| I[recordFailure]
    H --> J{Half-Open + \n successes ≥ threshold?}
    J -->|Yes| K[→ Closed]
    J -->|No| L[Keep State]
    I --> M{Half-Open?}
    M -->|Yes| N[→ Open]
    M -->|No| O{failures ≥ threshold?}
    O -->|Yes| N
    O -->|No| L
```
