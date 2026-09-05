<?php declare(strict_types=1);

namespace Skim\Dev;

/**
 * Per-request structured trace recording middleware, queries, controller calls, and response. #AI:class
 *
 * Use when you need a timeline of what happened during a request. In dev
 * (APP_DEBUG=true), the full timeline is captured and stored in sys.last_trace.
 * In production, only errors and a summary are emitted to error_log.
 *
 * Example:
 *   RequestTrace::start($req_id, 'GET', '/users');
 *   RequestTrace::event('middleware.auth', ['ms' => 2.1]);
 *   RequestTrace::event('controller.index');
 *   $trace = RequestTrace::finish(200);
 *
 * Testing: Call reset() between requests to clear state.
 *
 * #AI:class
 */
final class RequestTrace {
    private static ?array  $current    = null;
    private static float   $startTime = 0.0;
    private static bool    $enabled    = false;

    /**
     * Enables trace collection. #AI:enable
     */
    public static function enable(): void  { self::$enabled = true; }

    /**
     * Disables trace collection and clears the current trace. #AI:disable
     */
    public static function disable(): void {
        self::$enabled = false;
        self::$current = null;
    }

    /**
     * Returns whether trace collection is enabled. #AI:isEnabled
     */
    public static function isEnabled(): bool { return self::$enabled; }

    /**
     * Begins a new per-request trace, discarding any previous unfinished trace. #AI:start
     *
     * No-op when not enabled.
     *
     * @param string $requestId Unique request identifier.
     * @param string $method     HTTP method (GET, POST, etc.).
     * @param string $path       Request path.
     */
    public static function start(string $requestId, string $method, string $path): void {
        if (!self::$enabled) {
            return;
        }
        self::$startTime = microtime(true);
        self::$current = [
            'request_id'        => $requestId,
            'method'            => $method,
            'path'              => $path,
            'timeline'          => [],
            'extensions_active' => [],
            'errors'            => [],
        ];
    }

    /**
     * Appends a timestamped event to the current trace timeline. #AI:event
     *
     * No-op when trace not started or not enabled. The $context array is
     * merged into the event entry alongside 't' (elapsed seconds) and 'event'.
     *
     * @param string $event   Event name (e.g. 'middleware.auth', 'db.query').
     * @param array  $context Additional key-value data merged into the event entry.
     */
    public static function event(string $event, array $context = []): void {
        if (self::$current === null) {
            return;
        }
        self::$current['timeline'][] = array_merge(
            ['t' => round(microtime(true) - self::$startTime, 6), 'event' => $event],
            $context,
        );
    }

    /**
     * Records a Throwable into the errors list with timestamp and class. #AI:error
     *
     * No-op when trace not started.
     *
     * @param \Throwable $e The exception to record.
     */
    public static function error(\Throwable $e): void {
        if (self::$current === null) {
            return;
        }
        self::$current['errors'][] = [
            't'       => round(microtime(true) - self::$startTime, 6),
            'class'   => get_class($e),
            'message' => $e->getMessage(),
        ];
    }

    /**
     * Sets the list of active extension names on the current trace. #AI:setExtensions
     *
     * No-op when trace not started.
     *
     * @param string[] $names Extension names active for this request.
     */
    public static function setExtensions(array $names): void {
        if (self::$current !== null) {
            self::$current['extensions_active'] = array_values($names);
        }
    }

    /**
     * Closes the current trace, recording final status and total duration. #AI:finish
     *
     * Returns the completed trace array and clears the current trace state.
     * Returns empty array when no trace was started.
     *
     * @param int $status HTTP response status code.
     * @return array Completed trace array, or empty array when not started.
     */
    public static function finish(int $status): array {
        if (self::$current === null) {
            return [];
        }
        self::$current['status']      = $status;
        self::$current['duration_ms'] = round((microtime(true) - self::$startTime) * 1000, 2);
        $trace         = self::$current;
        self::$current = null;
        return $trace;
    }

    /**
     * Returns the in-progress trace without closing it. #AI:current
     *
     * @return array|null Current trace array, or null when not started.
     */
    public static function current(): ?array {
        return self::$current;
    }

    /**
     * Clears all trace state without disabling the tracer. #AI:reset
     *
     * Call in tests between requests to isolate per-request traces.
     * Does NOT disable the tracer — only clears collected data.
     */
    public static function reset(): void {
        self::$current    = null;
        self::$startTime = 0.0;
    }
}

#AI:class
#AI symbol: Skim\Dev\RequestTrace
#AI source_path: src/Dev/RequestTrace.php
#AI title: RequestTrace
#AI description: Per-request structured trace that records middleware, queries, controller calls, and response as a timestamped timeline.
#AI role: request timeline tracer
#AI layer: dev
#AI badges: [dev; debug; trace; timeline; static]
#AI intro: `RequestTrace` captures a structured timeline of events during a single HTTP request. In dev mode (APP_DEBUG=true), the full timeline is stored in sys.last_trace. In production, only errors and summaries are emitted.
#AI lifecycle: start() at dispatch entry, event() throughout, finish() at response send, reset() in tests
#AI fallback: all methods are no-ops when disabled or when trace not started
#AI test_seam: enable()/disable() to toggle, reset() to clear between tests
#AI invariants: [all methods are no-ops when disabled or trace not started; start() discards previous unfinished trace; finish() clears current trace state; reset() does not disable the tracer]
#AI core_behaviors: [Records timestamped timeline events with elapsed time; Records Throwables with class and message; Tracks active extensions; Computes total duration on finish]
#AI scope_items: [{name: $current | mutable: true | desc: Current in-progress trace array, null when not started.}; {name: $startTime | mutable: true | desc: microtime(true) at trace start.}; {name: $enabled | mutable: true | desc: Boolean toggle for trace collection.}]
#AI owns: current trace, startTime, enabled flag
#AI entry_points: [enable; disable; start; event; error; setExtensions; finish; current; reset]
#AI config_reads: []
#AI non_goals: [Does not persist traces across requests; Does not replace structured logging; Does not sample or filter events]
#AI side_effects: [mutates static $current array on each event/error call]
#AI flow: start() -> event()* -> finish() -> trace array; reset() between requests
#AI lifecycle_steps: [enable(); -> start(requestId, method, path); -> event() throughout request; -> error() on exceptions; -> setExtensions(); -> finish(status) returns trace; -> reset() in tests]
#AI section_order: [Control; Recording; Reading; Architecture]
#AI architectural_notes: Static facade pattern — all state is process-local. Designed for dev-mode request introspection, not production tracing.

#AI:enable
#AI group: Control
#AI frequency: low
#AI signature: public static function enable(): void
#AI contract: Enables trace collection. Called by App::run() when APP_DEBUG=true.

#AI:disable
#AI group: Control
#AI frequency: low
#AI signature: public static function disable(): void
#AI contract: Disables trace collection and clears the current in-progress trace.

#AI:isEnabled
#AI group: Control
#AI frequency: low
#AI signature: public static function isEnabled(): bool
#AI contract: Returns whether trace collection is currently enabled.

#AI:start
#AI group: Recording
#AI frequency: high
#AI signature: public static function start(string $requestId, string $method, string $path): void
#AI contract: Begins a new per-request trace, discarding any previous unfinished trace. No-op when not enabled.
#AI param_details: [{name: $requestId | type: string | required: true | desc: Unique request identifier.}; {name: $method | type: string | required: true | desc: HTTP method (GET, POST, etc.).}; {name: $path | type: string | required: true | desc: Request path.}]

#AI:event
#AI group: Recording
#AI frequency: high
#AI signature: public static function event(string $event, array $context = []): void
#AI contract: Appends a timestamped event to the current trace timeline. The context array is merged into the event entry alongside elapsed time and event name. No-op when trace not started.
#AI param_details: [{name: $event | type: string | required: true | desc: Event name (e.g. 'middleware.auth', 'db.query').}; {name: $context | type: array | required: false | desc: Additional key-value data merged into the event entry.}]

#AI:error
#AI group: Recording
#AI frequency: medium
#AI signature: public static function error(\Throwable $e): void
#AI contract: Records a Throwable into the errors list with elapsed timestamp, exception class, and message. No-op when trace not started.
#AI param_details: [{name: $e | type: \Throwable | required: true | desc: The exception to record.}]

#AI:setExtensions
#AI group: Recording
#AI frequency: low
#AI signature: public static function setExtensions(array $names): void
#AI contract: Sets the list of active extension names on the current trace. No-op when trace not started.
#AI param_details: [{name: $names | type: string[] | required: true | desc: Extension names active for this request.}]

#AI:finish
#AI group: Reading
#AI frequency: high
#AI signature: public static function finish(int $status): array
#AI contract: Closes the current trace, recording the HTTP status code and total duration in milliseconds. Returns the completed trace array and clears the current state.
#AI param_details: [{name: $status | type: int | required: true | desc: HTTP response status code.}]
#AI return_detail: {type: array | desc: Completed trace array with timeline, errors, extensions, status, and duration_ms. Empty array when not started.}

#AI:current
#AI group: Reading
#AI frequency: low
#AI signature: public static function current(): ?array
#AI contract: Returns the in-progress trace without closing it, or null when no trace is active.
#AI return_detail: {type: ?array | desc: Current trace array, or null when not started.}

#AI:reset
#AI group: Reading
#AI frequency: low
#AI signature: public static function reset(): void
#AI contract: Clears all trace state (current trace and start time) without disabling the tracer. Call in tests between requests.
#AI side_effects: [clears static $current and $startTime]
