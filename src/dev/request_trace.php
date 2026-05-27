<?php declare(strict_types=1);

namespace skim\dev;

// Per-request structured trace — records middleware, queries, controller calls, and response.
// In dev (APP_DEBUG=true): full timeline captured and stored in sys.last_trace.
// In production: only errors and a summary are emitted to error_log.
//
// Lifecycle: request_trace::start() at dispatch entry, event() throughout,
// finish() at response send. reset() in tests between requests.
final class request_trace {
    private static ?array  $current    = null;
    private static float   $start_time = 0.0;
    private static bool    $enabled    = false;

    /**
     * @ai-contract enables trace collection; no-op when already enabled
     */
    public static function enable(): void  { self::$enabled = true; }

    /**
     * @ai-contract disables trace collection and clears current trace
     */
    public static function disable(): void {
        self::$enabled = false;
        self::$current = null;
    }

    public static function is_enabled(): bool { return self::$enabled; }

    /**
     * @ai-contract begins a new per-request trace; discards any previous unfinished trace
     * @ai-contract no-op when not enabled
     */
    public static function start(string $request_id, string $method, string $path): void {
        if (!self::$enabled) {
            return;
        }
        self::$start_time = microtime(true);
        self::$current = [
            'request_id'        => $request_id,
            'method'            => $method,
            'path'              => $path,
            'timeline'          => [],
            'extensions_active' => [],
            'errors'            => [],
        ];
    }

    /**
     * @ai-contract appends a timestamped event to the current trace timeline
     * @ai-contract no-op when trace not started or not enabled
     * @ai-contract $context is merged into the event entry alongside 't' and 'event'
     */
    public static function event(string $event, array $context = []): void {
        if (self::$current === null) {
            return;
        }
        self::$current['timeline'][] = array_merge(
            ['t' => round(microtime(true) - self::$start_time, 6), 'event' => $event],
            $context,
        );
    }

    /**
     * @ai-contract records a Throwable into the errors list with timestamp and class
     * @ai-contract no-op when trace not started
     */
    public static function error(\Throwable $e): void {
        if (self::$current === null) {
            return;
        }
        self::$current['errors'][] = [
            't'       => round(microtime(true) - self::$start_time, 6),
            'class'   => get_class($e),
            'message' => $e->getMessage(),
        ];
    }

    /**
     * @ai-contract sets the list of active extension names on the current trace
     * @ai-contract no-op when trace not started
     */
    public static function set_extensions(array $names): void {
        if (self::$current !== null) {
            self::$current['extensions_active'] = array_values($names);
        }
    }

    /**
     * @ai-contract closes the current trace, records final status and duration
     * @ai-contract returns the completed trace array; returns [] when not started
     */
    public static function finish(int $status): array {
        if (self::$current === null) {
            return [];
        }
        self::$current['status']      = $status;
        self::$current['duration_ms'] = round((microtime(true) - self::$start_time) * 1000, 2);
        $trace         = self::$current;
        self::$current = null;
        return $trace;
    }

    /**
     * @ai-contract returns the in-progress trace without closing it, or null when not started
     */
    public static function current(): ?array {
        return self::$current;
    }

    /**
     * @ai-contract clears all state — use in tests between requests
     * @ai-contract does NOT disable the tracer, only clears collected data
     */
    public static function reset(): void {
        self::$current    = null;
        self::$start_time = 0.0;
    }
}
