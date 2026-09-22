<?php declare(strict_types=1);

namespace Skim\Events;

use Skim\Worker\Resettable;

/**
 * Synchronous event bus with priority ordering and async dispatch via queue.
 *
 * Use for decoupling side effects from request handlers. Listeners run
 * inline before the response returns. Use emitAsync() for slow side
 * effects (email, reports) that should not delay the response.
 *
 * Example:
 *   Event::on(UserCreatedEvent::class, fn($e) => Log::info("User {$e->user->id} created"));
 *   Event::emit(new UserCreatedEvent($user));
 *
 * Testing: Use off() in tearDown() to clear listeners between test cases.
 *
 * #AI:class
 */
final class Event implements \Skim\Worker\Resettable {
    /** @var array<string, list<array{fn: callable, once: bool, priority: int}>> */
    private static array $listeners = [];

    // Snapshot of listeners registered during boot/extension phase. Captured on the
    // first resetRequest() call and restored on every subsequent reset so boot-time
    // listeners survive across worker requests while request-time listeners are dropped.
    /** @var array<string, list<array{fn: callable, once: bool, priority: int}>>|null */
    private static ?array $persistentListeners = null;

    /**
     * Captures the current listener registry as the boot-time snapshot. #AI:capture_boot_snapshot
     *
     * Call once after boot/extensions are registered in worker mode. The snapshot
     * is restored by resetRequest() on every subsequent request so boot-time
     * listeners survive while request-time listeners are dropped.
     */
    public static function captureBootSnapshot(): void {
        self::$persistentListeners = self::$listeners;
    }

    /**
     * Restores the boot-time listener registry between requests in worker mode. #AI:resetRequest
     *
     * On the first call (at the end of the first worker request) the current listener
     * set is captured as the boot snapshot. Every later call resets listeners back to
     * that snapshot, so listeners registered at boot/extension time persist, while any
     * listeners registered during a request are dropped.
     */
    public static function resetRequest(): void {
        self::$listeners = self::$persistentListeners ?? [];
    }

    /**
     * Registers a listener for the given event. #AI:on
     *
     * Higher priority runs first; equal priorities run in registration
     * order. The callable receives the event object (typed events) or
     * raw payload (string events).
     *
     * Example:
     *   Event::on(UserCreatedEvent::class, fn($e) => Mailer::send($e->user));
     *   Event::on('user.created', fn($data) => Log::info($data['id']), priority: 10);
     *
     * @param string   $event    Event class-string or string name.
     * @param callable $listener Callback receiving the event object or payload.
     * @param int      $priority Higher number runs first. Default 0.
     */
    public static function on(string $event, callable $listener, int $priority = 0): void {
        self::$listeners[$event][] = ['fn' => $listener, 'once' => false, 'priority' => $priority];
        usort(self::$listeners[$event], fn($a, $b) => $b['priority'] <=> $a['priority']);
    }

    /**
     * Registers a one-time listener removed after the first dispatch. #AI:once
     *
     * Use for one-time boot tasks or warmup operations.
     *
     * @param string   $event    Event class-string or string name.
     * @param callable $listener Callback receiving the event object or payload.
     * @param int      $priority Higher number runs first. Default 0.
     */
    public static function once(string $event, callable $listener, int $priority = 0): void {
        self::$listeners[$event][] = ['fn' => $listener, 'once' => true, 'priority' => $priority];
        usort(self::$listeners[$event], fn($a, $b) => $b['priority'] <=> $a['priority']);
    }

    /**
     * Dispatches the event to all registered listeners in priority order. #AI:emit
     *
     * For typed events: pass the event object as $payload; the event name
     * is derived from get_class(). For string events: pass the name as
     * $payload and the data as $data. All listeners run synchronously.
     *
     * Example:
     *   Event::emit(new UserCreatedEvent($user));
     *   Event::emit('user.created', ['id' => $user->id]);
     *
     * @param object|string $payload Event object (typed) or event name (string).
     * @param mixed         $data    Payload for string events (ignored for typed events).
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
     * Pushes the event to the queue for async processing. #AI:emitAsync
     *
     * The listener runs in a worker process, not during the current request.
     * Requires skim/queue to be installed.
     *
     * Example:
     *   Event::emitAsync(new ReportGeneratedEvent($report));
     *
     * @param object|string $payload Event object or event name.
     * @param mixed         $data    Payload for string events.
     * @throws \RuntimeException When skim/queue is not installed.
     */
    public static function emitAsync(object|string $payload, mixed $data = null): void {
        if (!class_exists(\Skim\Queue\Queue::class)) {
            throw new \RuntimeException('emit_async() requires skim/queue. Run: php skim module:add queue');
        }
        \Skim\Queue\Queue::push(new \Skim\Queue\Internal\EmitEventJob($payload, $data));
    }

    /**
     * Removes all listeners for a specific event, or all listeners if null. #AI:off
     *
     * Use in tests to isolate event side effects between test cases. Also clears
     * the boot-time snapshot so worker-mode resetRequest() state cannot leak
     * between tests.
     *
     * Example:
     *   Event::off(UserCreatedEvent::class); // Remove listeners for one event
     *   Event::off();                           // Remove all listeners
     *
     * @param string|null $event Event name to clear, or null for all.
     */
    public static function off(?string $event = null): void {
        if ($event === null) {
            self::$listeners = [];
            self::$persistentListeners = null;
        } else {
            unset(self::$listeners[$event]);
        }
    }

    /**
     * Returns the number of listeners registered for an event. #AI:listenerCount
     *
     * @param string $event Event class-string or string name.
     */
    public static function listenerCount(string $event): int {
        return count(self::$listeners[$event] ?? []);
    }

    /**
     * Returns the total number of listeners across all events. #AI:total_listener_count
     *
     * Used by the leak detector to detect listener accumulation in worker mode.
     */
    public static function totalListenerCount(): int {
        $total = 0;
        foreach (self::$listeners as $listeners) {
            $total += count($listeners);
        }
        return $total;
    }
}

#AI:class
#AI symbol: Skim\Events\Event
#AI source_path: src/Events/Event.php
#AI title: event
#AI description: Synchronous event bus with priority ordering, one-time listeners, and async dispatch via queue.
#AI role: static event bus
#AI layer: events
#AI badges: [facade; events; pubsub; async; priority]
#AI intro: `event` is the static event bus for decoupling side effects. Listeners run synchronously in priority order during emit(). Async dispatch via emitAsync() delegates to the queue worker.
#AI lifecycle: static, listeners registered during boot phase; global per-process
#AI test_seam: off() to clear listeners; listenerCount() for assertions
#AI invariants: [Listeners run in priority order (higher first); once() listeners are removed after first call; emit() is synchronous — all listeners complete before returning]
#AI core_behaviors: [Typed event classes dispatch by class name; String events dispatch by name; Priority sorting on registration; One-time listener cleanup after dispatch]
#AI owns: listener registry
#AI entry_points: [on; once; emit; emitAsync; off; listenerCount]
#AI config_reads: []
#AI non_goals: [Does not support wildcard event patterns; Does not persist events; Does not guarantee delivery for emitAsync — depends on queue worker]
#AI side_effects: [emit() runs listeners inline; emitAsync() pushes to queue]
#AI flow: Event::on(class, fn) -> register; Event::emit(obj) -> get_class -> iterate listeners by priority -> call each
#AI section_order: [Registration; Dispatch; Testing Hooks]

#AI:on
#AI group: Registration
#AI frequency: high
#AI signature: public static function on(string $event, callable $listener, int $priority = 0): void
#AI contract: Registers a persistent listener for the event. Higher priority runs first; equal priorities run in registration order.
#AI param_details: [{name: $event | type: string | required: true | desc: Event class-string or string name.}; {name: $listener | type: callable | required: true | desc: Callback receiving the event object or payload.}; {name: $priority | type: int | required: false | desc: Higher number runs first. Default 0.}]
#AI side_effects: [Adds listener to static registry]

#AI:once
#AI group: Registration
#AI frequency: low
#AI signature: public static function once(string $event, callable $listener, int $priority = 0): void
#AI contract: Registers a one-time listener that is automatically removed after the first dispatch.
#AI param_details: [{name: $event | type: string | required: true | desc: Event class-string or string name.}; {name: $listener | type: callable | required: true | desc: Callback receiving the event object or payload.}; {name: $priority | type: int | required: false | desc: Higher number runs first. Default 0.}]
#AI side_effects: [Adds one-time listener to static registry]

#AI:emit
#AI group: Dispatch
#AI frequency: high
#AI signature: public static function emit(object|string $payload, mixed $data = null): void
#AI contract: Dispatches the event to all registered listeners in priority order. For typed events, the object is passed directly. For string events, $data is the payload. All listeners run synchronously.
#AI param_details: [{name: $payload | type: object|string | required: true | desc: Event object (typed) or event name (string).}; {name: $data | type: mixed | required: false | desc: Payload for string events. Ignored for typed events.}]
#AI side_effects: [Runs all matching listeners inline; Removes once-listeners after execution]

#AI:emitAsync
#AI group: Dispatch
#AI frequency: medium
#AI signature: public static function emitAsync(object|string $payload, mixed $data = null): void
#AI contract: Pushes the event to the queue for async processing in a worker process. Requires skim/queue to be installed.
#AI param_details: [{name: $payload | type: object|string | required: true | desc: Event object or event name.}; {name: $data | type: mixed | required: false | desc: Payload for string events.}]
#AI throws_details: [{type: \RuntimeException | desc: When skim/queue is not installed.}]
#AI side_effects: [Pushes job to queue]

#AI:off
#AI group: Testing Hooks
#AI frequency: medium
#AI signature: public static function off(?string $event = null): void
#AI contract: Removes all listeners for a specific event, or all listeners when $event is null.
#AI param_details: [{name: $event | type: ?string | required: false | desc: Event name to clear, or null for all listeners.}]
#AI side_effects: [Clears listener registry]

#AI:listenerCount
#AI group: Testing Hooks
#AI frequency: low
#AI signature: public static function listenerCount(string $event): int
#AI contract: Returns the number of listeners currently registered for the given event.
#AI param_details: [{name: $event | type: string | required: true | desc: Event class-string or string name.}]
#AI return_detail: {type: int | desc: Number of registered listeners.}

#AI:resetRequest
#AI group: Testing Hooks
#AI frequency: internal
#AI signature: public static function resetRequest(): void
#AI contract: Clears the listener registry between requests in worker mode.
#AI side_effects: [Empties the static $listeners array]
