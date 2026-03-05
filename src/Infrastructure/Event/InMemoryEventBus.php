<?php
declare(strict_types=1);

namespace App\Infrastructure\Event;

use App\Application\Ports\EventBus;

/**
 * Minimal event bus (sync).
 * TODO (later): replace with Symfony EventDispatcher or Messenger.
 * Ref: https://symfony.com/doc/current/components/event_dispatcher.html
 */
final class InMemoryEventBus implements EventBus
{
  /** @var array<string, list<callable>> */
  private array $handlers = [];

  public function on(string $eventName, callable $handler): void
  {
    $this->handlers[$eventName] ??= [];
    $this->handlers[$eventName][] = $handler;
  }

  public function emit(string $eventName, array $payload): void
  {
    foreach ($this->handlers[$eventName] ?? [] as $h) {
      $h($payload);
    }
  }
}
