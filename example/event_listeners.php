<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use GabrielAnhaia\PhpCircuitBreaker\CircuitBreaker;
use GabrielAnhaia\PhpCircuitBreaker\CircuitBreakerConfig;
use GabrielAnhaia\PhpCircuitBreaker\Event\CircuitBreakerEvent;
use GabrielAnhaia\PhpCircuitBreaker\Event\CircuitClosedEvent;
use GabrielAnhaia\PhpCircuitBreaker\Event\CircuitOpenedEvent;
use GabrielAnhaia\PhpCircuitBreaker\Event\FailureRecordedEvent;
use GabrielAnhaia\PhpCircuitBreaker\Event\SimpleEventDispatcher;
use GabrielAnhaia\PhpCircuitBreaker\Event\SuccessRecordedEvent;
use GabrielAnhaia\PhpCircuitBreaker\Storage\InMemoryStorage;

$dispatcher = new SimpleEventDispatcher();

// Log when circuits open
$dispatcher->addListener(CircuitOpenedEvent::class, function (CircuitOpenedEvent $event): void {
    echo "[ALERT] Circuit OPENED for {$event->getServiceName()} at {$event->getOccurredAt()->format('H:i:s')}\n";
});

// Log when circuits close
$dispatcher->addListener(CircuitClosedEvent::class, function (CircuitClosedEvent $event): void {
    echo "[INFO] Circuit CLOSED for {$event->getServiceName()} at {$event->getOccurredAt()->format('H:i:s')}\n";
});

// Count all events (using base class listener)
$eventCount = 0;
$dispatcher->addListener(CircuitBreakerEvent::class, function () use (&$eventCount): void {
    $eventCount++;
});

$storage = new InMemoryStorage();
$config = new CircuitBreakerConfig(failureThreshold: 2, successThreshold: 1, openTimeout: 1);
$cb = new CircuitBreaker($storage, $config, $dispatcher);

$service = 'email-service';

echo "Recording failures...\n";
$cb->recordFailure($service);
$cb->recordFailure($service); // This triggers OPEN

echo "\nWaiting for circuit to transition to half-open...\n";
sleep(2);

echo "\nRecording success...\n";
$cb->recordSuccess($service); // This triggers CLOSED

echo "\nTotal events dispatched: {$eventCount}\n";
