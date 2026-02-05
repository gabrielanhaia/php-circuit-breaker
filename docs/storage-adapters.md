# Storage Adapters

[< Back to README](../README.md)

All adapters implement `CircuitBreakerStorageInterface`. The circuit breaker owns all state transition logic — adapters are dumb storage.

## InMemory (Testing / Development)

```php
use GabrielAnhaia\PhpCircuitBreaker\Storage\InMemoryStorage;

$storage = new InMemoryStorage();
```

State lives in a PHP array. Useful for tests and single-request scripts.

## Redis

```php
use GabrielAnhaia\PhpCircuitBreaker\Storage\RedisStorage;

$redis = new \Redis();
$redis->connect('127.0.0.1', 6379);

$storage = new RedisStorage($redis, prefix: 'cb:');
```

Uses atomic `INCR` + `EXPIRE` in `MULTI/EXEC` transactions. No `KEYS` command. Production-recommended.

**Key scheme:**
```
{prefix}{service}:failures
{prefix}{service}:successes
{prefix}{service}:state:open
{prefix}{service}:state:half_open
{prefix}{service}:override
```

## APCu

```php
use GabrielAnhaia\PhpCircuitBreaker\Storage\ApcuStorage;

$storage = new ApcuStorage(prefix: 'cb:');
```

> **Note:** APCu is per-process. CLI and web processes have separate caches. Set `apc.enable_cli=1` for CLI usage.

## Memcached

```php
use GabrielAnhaia\PhpCircuitBreaker\Storage\MemcachedStorage;

$memcached = new \Memcached();
$memcached->addServer('127.0.0.1', 11211);

$storage = new MemcachedStorage($memcached, prefix: 'cb:');
```

## PSR-6 Cache

```php
use GabrielAnhaia\PhpCircuitBreaker\Storage\Psr6CacheStorage;

$storage = new Psr6CacheStorage($psr6CachePool, prefix: 'cb_');
```

> **Note:** PSR-6 get-increment-save is not atomic. For high-concurrency production, prefer Redis, Memcached, or APCu.

## PSR-16 SimpleCache

```php
use GabrielAnhaia\PhpCircuitBreaker\Storage\Psr16CacheStorage;

$storage = new Psr16CacheStorage($psr16Cache, prefix: 'cb_');
```

> **Note:** Same atomicity caveat as PSR-6.

## Adapter Comparison

| Adapter | Shared State | Atomic Ops | TTL Support | Best For |
|---------|:---:|:---:|:---:|---------|
| InMemory | - | - | Via clock | Testing, single-process |
| Redis | Multi-process | MULTI/EXEC | Native | Production |
| APCu | Per-process | - | Native | Single-server web |
| Memcached | Multi-process | `increment` | Native | Production |
| PSR-6 | Depends | - | Via `expiresAfter` | Framework integration |
| PSR-16 | Depends | - | Via TTL param | Framework integration |
