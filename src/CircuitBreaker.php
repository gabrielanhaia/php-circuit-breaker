<?php

declare(strict_types=1);

namespace GabrielAnhaia\PhpCircuitBreaker;

use GabrielAnhaia\PhpCircuitBreaker\Clock\Clock;
use GabrielAnhaia\PhpCircuitBreaker\Clock\SystemClock;
use GabrielAnhaia\PhpCircuitBreaker\Event\CircuitBreakerEvent;
use GabrielAnhaia\PhpCircuitBreaker\Event\CircuitClosedEvent;
use GabrielAnhaia\PhpCircuitBreaker\Event\CircuitOpenedEvent;
use GabrielAnhaia\PhpCircuitBreaker\Event\EventDispatcherInterface;
use GabrielAnhaia\PhpCircuitBreaker\Event\FailureRecordedEvent;
use GabrielAnhaia\PhpCircuitBreaker\Event\SuccessRecordedEvent;
use GabrielAnhaia\PhpCircuitBreaker\Exception\OpenCircuitException;
use GabrielAnhaia\PhpCircuitBreaker\Storage\CircuitBreakerStorageInterface;

final class CircuitBreaker
{
    private Clock $clock;

    public function __construct(
        private readonly CircuitBreakerStorageInterface $storage,
        private readonly CircuitBreakerConfig $config = new CircuitBreakerConfig(),
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        ?Clock $clock = null,
    ) {
        $this->clock = $clock ?? new SystemClock();
    }

    public function canPass(string $serviceName): bool
    {
        $state = $this->getState($serviceName);

        if ($state === CircuitState::OPEN) {
            if ($this->config->isExceptionsEnabled()) {
                throw new OpenCircuitException($serviceName);
            }
            return false;
        }

        return true;
    }

    public function recordFailure(string $serviceName): void
    {
        $state = $this->getState($serviceName);

        $this->storage->recordFailure($serviceName, $this->config->getTimeWindow());
        $this->dispatchEvent(new FailureRecordedEvent($serviceName, $this->clock->now()));

        if ($state === CircuitState::HALF_OPEN) {
            $this->openCircuit($serviceName);
            return;
        }

        if ($this->storage->getFailureCount($serviceName) >= $this->config->getFailureThreshold()) {
            $this->openCircuit($serviceName);
        }
    }

    public function recordSuccess(string $serviceName): void
    {
        $state = $this->getState($serviceName);

        $this->storage->recordSuccess($serviceName, $this->config->getTimeWindow());
        $this->dispatchEvent(new SuccessRecordedEvent($serviceName, $this->clock->now()));

        if ($state === CircuitState::HALF_OPEN) {
            if ($this->storage->getSuccessCount($serviceName) >= $this->config->getSuccessThreshold()) {
                $this->storage->setClosed($serviceName);
                $this->dispatchEvent(new CircuitClosedEvent($serviceName, $this->clock->now()));
            }
            return;
        }

        if ($state === CircuitState::CLOSED) {
            $this->storage->setClosed($serviceName);
        }
    }

    public function getState(string $serviceName): CircuitState
    {
        $override = $this->storage->getOverride($serviceName);
        if ($override !== null) {
            return $override;
        }

        return $this->storage->getState($serviceName);
    }

    public function forceState(string $serviceName, CircuitState $state, ?int $ttl = null): void
    {
        $this->storage->setOverride($serviceName, $state, $ttl);
    }

    public function clearOverride(string $serviceName): void
    {
        $this->storage->clearOverride($serviceName);
    }

    /**
     * @deprecated Use recordFailure() instead. Will be removed in v4.
     */
    public function failed(string $serviceName): void
    {
        $this->recordFailure($serviceName);
    }

    /**
     * @deprecated Use recordSuccess() instead. Will be removed in v4.
     */
    public function succeed(string $serviceName): void
    {
        $this->recordSuccess($serviceName);
    }

    private function openCircuit(string $serviceName): void
    {
        $this->storage->setOpen($serviceName, $this->config->getOpenTimeout());
        $this->dispatchEvent(new CircuitOpenedEvent($serviceName, $this->clock->now()));
    }

    private function dispatchEvent(CircuitBreakerEvent $event): void
    {
        $this->eventDispatcher?->dispatch($event);
    }
}
