<?php
namespace App\Core;

/**
 * Minimal synchronous event system (singleton-capable).
 */
class EventEmitter
{
    private static ?EventEmitter $shared = null;
    /** @var array<string, callable[]> */
    private array $listeners = [];

    public static function shared(): EventEmitter
    {
        if (self::$shared === null) {
            self::$shared = new self();
        }
        return self::$shared;
    }

    public function on(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    public function emit(string $event, $data = []): void
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            try {
                $listener($data);
            } catch (\Throwable $e) {
                error_log('[EventEmitter] listener for ' . $event . ' failed: ' . $e->getMessage());
            }
        }
    }

    public function off(string $event, ?callable $listener = null): void
    {
        if ($listener === null) {
            unset($this->listeners[$event]);
            return;
        }
        if (!isset($this->listeners[$event])) {
            return;
        }
        $this->listeners[$event] = array_values(array_filter(
            $this->listeners[$event],
            fn($l) => $l !== $listener
        ));
    }
}
