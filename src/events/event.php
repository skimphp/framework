<?php declare(strict_types=1);

namespace skim\events;

// Synchronous event bus. Listeners run inline before the response returns.
// Use emit_async() for side effects that should not delay the response (email, reports).
//
// Listener registration is global per-process — register in boot, not in controllers.
// Priority: higher number = runs first. Default 0. Use 10+ for auth/logging that must run early.
// Typed event classes (recommended): refactor-safe, IDE completion, explicit contracts.
// String events ('user.created'): fine for simple cases, no type safety.
final class event {
    /** @var array<string, list<array{fn: callable, once: bool, priority: int}>> */
    private static array $listeners = [];

    /**
     * @ai-contract registers listener for $event — string name or class-string
     * @ai-contract higher $priority = runs first; equal priorities run in registration order
     * @ai-contract callable receives event object (typed) or raw payload (string events)
     */
    public static function on(string $event, callable $listener, int $priority = 0): void {
        self::$listeners[$event][] = ['fn' => $listener, 'once' => false, 'priority' => $priority];
        usort(self::$listeners[$event], fn($a, $b) => $b['priority'] <=> $a['priority']);
    }

    /**
     * @ai-contract same as on(), but listener is removed after first call
     * @ai-contract use for one-time boot tasks or warmup operations
     */
    public static function once(string $event, callable $listener, int $priority = 0): void {
        self::$listeners[$event][] = ['fn' => $listener, 'once' => true, 'priority' => $priority];
        usort(self::$listeners[$event], fn($a, $b) => $b['priority'] <=> $a['priority']);
    }

    /**
     * @ai-contract dispatches $payload to all registered listeners in priority order
     * @ai-contract for typed events: $payload is the event object, $event = get_class($payload)
     * @ai-contract for string events: pass the payload as second argument
     * @ai-contract listeners run synchronously — all complete before emit() returns
     */
    public static function emit(object|string $payload, mixed $data = null): void {
        $event = is_object($payload) ? get_class($payload) : $payload;
        $arg   = is_object($payload) ? $payload : $data;

        if (!isset(self::$listeners[$event])) {
            return;
        }

        $remaining = [];
        foreach (self::$listeners[$event] as $entry) {
            ($entry['fn'])($arg);
            if (!$entry['once']) {
                $remaining[] = $entry;
            }
        }
        self::$listeners[$event] = $remaining;
    }

    /**
     * @ai-contract pushes event to the queue — listener runs in a worker process
     * @ai-contract requires skim/queue to be installed; throws if queue not available
     * @ai-contract use for slow side effects: email, reports, external API calls
     */
    public static function emit_async(object|string $payload, mixed $data = null): void {
        if (!class_exists(\skim\queue\queue::class)) {
            throw new \RuntimeException('emit_async() requires skim/queue. Run: php skim module:add queue');
        }
        \skim\queue\queue::push(new \skim\queue\internal\emit_event_job($payload, $data));
    }

    /**
     * @ai-contract removes all listeners for $event, or all listeners if null
     * @ai-contract use in tests to isolate event side effects between test cases
     */
    public static function off(?string $event = null): void {
        if ($event === null) {
            self::$listeners = [];
        } else {
            unset(self::$listeners[$event]);
        }
    }

    /**
     * @ai-contract for tests — check how many listeners are registered for $event
     */
    public static function listener_count(string $event): int {
        return count(self::$listeners[$event] ?? []);
    }
}
