<?php
declare(strict_types=1);

namespace App\Application\Ports;

/**
 * EventBus — порт событий приложения.
 * В MVP можно держать sync in-memory bus, позже заменить на Symfony EventDispatcher или Messenger.
 * Symfony EventDispatcher: https://symfony.com/doc/current/components/event_dispatcher.html
 * Symfony Messenger: https://symfony.com/doc/current/messenger.html
 */
interface EventBus
{
    /**
     * Subscribe handler to event name.
     *
     * @param callable(array $payload):void $handler
     */
    public function on(string $eventName, callable $handler): void;

    /**
     * Emit event with payload.
     */
    public function emit(string $eventName, array $payload): void;
}