<?php
declare(strict_types=1);

/**
 * EventEmitter - Lightweight synchronous event/listener system.
 *
 * Usable both statically (global event bus) and as an instance.
 * Listeners receive the emitted payload. "once" listeners auto-remove
 * after firing a single time.
 */
class EventEmitter
{
    /** @var array<string, array<int, callable>> */
    protected array $listeners = [];

    /** @var array<string, array<int, callable>> */
    protected array $onceListeners = [];

    protected static ?EventEmitter $instance = null;

    /**
     * Shared global emitter instance.
     */
    public static function global(): EventEmitter
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register a persistent listener for an event.
     */
    public function on(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    /**
     * Register a listener that fires at most once.
     */
    public function once(string $event, callable $listener): void
    {
        $this->onceListeners[$event][] = $listener;
    }

    /**
     * Remove listeners. With no $listener, removes all for the event.
     */
    public function off(string $event, ?callable $listener = null): void
    {
        if ($listener === null) {
            unset($this->listeners[$event], $this->onceListeners[$event]);
            return;
        }

        foreach (['listeners', 'onceListeners'] as $bucket) {
            if (!empty($this->{$bucket}[$event])) {
                $this->{$bucket}[$event] = array_values(array_filter(
                    $this->{$bucket}[$event],
                    static fn ($l) => $l !== $listener
                ));
            }
        }
    }

    /**
     * Emit an event, invoking all matching listeners in order.
     * Returns the (possibly mutated) payload.
     */
    public function emit(string $event, mixed $payload = null): mixed
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $result = $listener($payload, $event);
            if ($result !== null) {
                $payload = $result;
            }
        }

        if (!empty($this->onceListeners[$event])) {
            $onceListeners = $this->onceListeners[$event];
            unset($this->onceListeners[$event]);
            foreach ($onceListeners as $listener) {
                $result = $listener($payload, $event);
                if ($result !== null) {
                    $payload = $result;
                }
            }
        }

        return $payload;
    }

    public function listeners(string $event): array
    {
        return array_merge(
            $this->listeners[$event] ?? [],
            $this->onceListeners[$event] ?? []
        );
    }

    public function hasListeners(string $event): bool
    {
        return !empty($this->listeners[$event]) || !empty($this->onceListeners[$event]);
    }
}
