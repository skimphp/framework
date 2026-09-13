<?php declare(strict_types=1);

namespace Skim\Dev;

/**
 * Static event collector that records DB queries, cache ops, view renders, and log entries. #AI:class
 *
 * Use when debugging performance or inspecting what the framework did during
 * a request. All methods are no-ops when disabled — zero overhead in production.
 * Called by db.php, cache.php, view.php, and log.php after every operation.
 *
 * Example:
 *   Profiler::enable();
 *   // ... request processing ...
 *   $summary = Profiler::summary();  // ['db' => ['count' => 5, 'ms' => 12.3], ...]
 *   $events  = Profiler::events();  // raw event array for toolbar
 *
 * Testing: Call reset() between requests to clear collected data.
 *
 * #AI:class
 */
final class Profiler {
    private static array $events   = [];
    private static bool  $enabled  = false;

    // custom panel registry: id → {id, label, icon, html, data, template}
    private static array $panels   = [];

    /**
     * Enables event collection. #AI:enable
     */
    public static function enable(): void  { self::$enabled = true; }

    /**
     * Disables event collection. #AI:disable
     */
    public static function disable(): void { self::$enabled = false; }

    /**
     * Records a database query event with interpolated SQL. #AI:db
     *
     * Called by db.php after every query. No-op when disabled.
     *
     * @param string $sql        Interpolated SQL string (not raw template).
     * @param float  $ms         Execution time in milliseconds.
     * @param string $connection Connection name from config.
     * @param int    $rows       Number of affected or returned rows.
     */
    public static function db(string $sql, float $ms, string $connection = 'default', int $rows = 0): void {
        if (!self::$enabled) {
            return;
        }
        self::$events[] = ['type' => 'db', 'sql' => $sql, 'ms' => $ms, 'connection' => $connection, 'rows' => $rows];
    }

    /**
     * Records a cache operation event. #AI:cache
     *
     * Called by cache.php on every get/set/delete/remember. No-op when disabled.
     *
     * @param string   $op     Operation name (get, set, delete, remember).
     * @param string   $key    Cache key operated on.
     * @param bool     $hit    Whether the operation was a cache hit.
     * @param int|null $ttl    TTL in seconds, or null for reads/deletes.
     * @param string   $driver Active cache driver name.
     */
    public static function cache(string $op, string $key, bool $hit = false, ?int $ttl = null, string $driver = 'redis'): void {
        if (!self::$enabled) {
            return;
        }
        self::$events[] = ['type' => 'cache', 'op' => $op, 'key' => $key, 'hit' => $hit, 'ttl' => $ttl, 'driver' => $driver];
    }

    /**
     * Records a template render event. #AI:view
     *
     * Called by view.php after every template render. No-op when disabled.
     *
     * @param string      $template Template path relative to views directory.
     * @param string|null $fragment Fragment name when rendering a partial, null for full page.
     * @param float       $ms       Render time in milliseconds.
     */
    public static function view(string $template, ?string $fragment = null, float $ms = 0.0): void {
        if (!self::$enabled) {
            return;
        }
        self::$events[] = ['type' => 'view', 'template' => $template, 'fragment' => $fragment, 'ms' => $ms];
    }

    /**
     * Records a log entry with source location from debug_backtrace. #AI:log
     *
     * Called by Log::* methods. No-op when disabled.
     *
     * @param string $level   Log level (debug, info, warning, error).
     * @param string $message Log message.
     * @param array  $context Additional context data.
     * @param string $file    Source file from debug_backtrace.
     * @param int    $line    Source line from debug_backtrace.
     */
    public static function log(string $level, string $message, array $context = [], string $file = '', int $line = 0): void {
        if (!self::$enabled) {
            return;
        }
        self::$events[] = ['type' => 'log', 'level' => $level, 'message' => $message, 'context' => $context, 'file' => $file, 'line' => $line];
    }

    /**
     * Returns aggregate summary for toolbar badge display. #AI:summary
     *
     * Groups events by type and computes counts and totals.
     */
    public static function summary(): array {
        $dbEvents    = array_filter(self::$events, fn($e) => $e['type'] === 'db');
        $cacheEvents = array_filter(self::$events, fn($e) => $e['type'] === 'cache');
        $viewEvents  = array_filter(self::$events, fn($e) => $e['type'] === 'view');

        return [
            'db'      => ['count' => count($dbEvents), 'ms' => round(array_sum(array_column($dbEvents, 'ms')), 2)],
            'cache'   => [
                'hits'   => count(array_filter($cacheEvents, fn($e) => $e['hit'])),
                'misses' => count(array_filter($cacheEvents, fn($e) => !$e['hit'])),
            ],
            'views'   => count($viewEvents),
            'view_ms' => round(array_sum(array_column(array_values($viewEvents), 'ms')), 2),
            'logs'    => count(array_filter(self::$events, fn($e) => $e['type'] === 'log')),
        ];
    }

    /**
     * Returns all raw events collected during the current request. #AI:events
     *
     * Used by the toolbar renderer to build detailed panels.
     */
    public static function events(): array {
        return self::$events;
    }

    /**
     * Registers a custom toolbar panel for user-defined debug extensions. #AI:panel
     *
     * Use in controllers, middleware, or extensions to add debug panels to
     * the toolbar. Panels appear as additional tabs after the built-in ones.
     * When $html is provided, it renders directly. When $template is set,
     * the toolbar renders it via dev_view with $data.
     *
     * Example:
     *   Profiler::panel('htmx', 'htmx Debug', [
     *       'icon' => 'arrows exchange',
     *       'data' => ['swaps' => 3, 'boosts' => 1],
     *   ]);
     *
     * @param string      $id       Unique panel identifier (used as tab data-tab).
     * @param string      $label    Tab label shown in the toolbar.
     * @param array       $options  Panel options: icon, html, data, template.
     */
    public static function panel(string $id, string $label, array $options = []): void {
        if (!self::$enabled) {
            return;
        }
        self::$panels[$id] = array_merge([
            'id'       => $id,
            'label'    => $label,
            'icon'     => 'puzzle',
            'html'     => '',
            'data'     => [],
            'template' => '',
        ], $options);
    }

    /**
     * Returns all registered custom panels for toolbar rendering. #AI:panels
     */
    public static function panels(): array {
        return self::$panels;
    }

    /**
     * Clears the event buffer without disabling the profiler. #AI:reset
     *
     * Call in tests between requests to isolate per-request data.
     * Does NOT disable the profiler — only clears collected events.
     */
    public static function reset(): void {
        self::$events = [];
        self::$panels = [];
    }
}

#AI:class
#AI symbol: Skim\Dev\Profiler
#AI source_path: src/Dev/Profiler.php
#AI title: profiler
#AI description: Static event collector that records DB queries, cache ops, view renders, and log entries for debug toolbar display.
#AI role: debug event collector
#AI layer: dev
#AI badges: [dev; debug; profiler; static; toolbar]
#AI intro: `profiler` is a static event collector that records framework operations (DB queries, cache ops, view renders, log entries) during a request. All methods are no-ops when disabled, ensuring zero overhead in production. The toolbar reads collected events for display.
#AI lifecycle: created empty at boot, enabled by App::run() when APP_DEBUG=true, populated during request, read by toolbar, reset between requests
#AI fallback: all recording methods are no-ops when disabled
#AI test_seam: enable()/disable() to toggle, reset() to clear between tests
#AI invariants: [all recording methods are no-ops when disabled; reset() clears events but does not disable; summary() computes aggregates from raw events]
#AI core_behaviors: [Records DB queries with interpolated SQL and timing; Records cache operations with hit/miss status; Records view renders with timing; Records log entries with source location; Provides aggregate summary for toolbar badges]
#AI scope_items: [{name: $events | mutable: true | desc: Array of recorded event arrays, cleared by reset().}; {name: $enabled | mutable: true | desc: Boolean toggle — when false, all recording methods are no-ops.}]
#AI owns: events array, enabled flag
#AI entry_points: [enable; disable; db; cache; view; log; summary; events; reset]
#AI config_reads: []
#AI non_goals: [Does not persist events across requests; Does not filter or sample events; Does not replace structured logging]
#AI side_effects: [mutates static $events array on each recording call]
#AI flow: db/cache/view/log() -> enabled? -> append to $events; summary() -> filter by type -> aggregate
#AI lifecycle_steps: [enable(); -> db()/cache()/view()/log() during request; -> summary()/events() for toolbar; -> reset() between requests]
#AI section_order: [Control; Recording; Reading; Architecture]
#AI architectural_notes: Static facade pattern — all state is process-local. Called by framework internals (db.php, cache.php, view.php, log.php) after every operation.

#AI:enable
#AI group: Control
#AI frequency: low
#AI signature: public static function enable(): void
#AI contract: Enables event collection. Called by App::run() when APP_DEBUG=true.

#AI:disable
#AI group: Control
#AI frequency: low
#AI signature: public static function disable(): void
#AI contract: Disables event collection. All recording methods become no-ops.

#AI:db
#AI group: Recording
#AI frequency: high
#AI signature: public static function db(string $sql, float $ms, string $connection = 'default', int $rows = 0): void
#AI contract: Records a database query event with interpolated SQL, timing, connection name, and row count. No-op when disabled.
#AI param_details: [{name: $sql | type: string | required: true | desc: Interpolated SQL string (not raw template).}; {name: $ms | type: float | required: true | desc: Execution time in milliseconds.}; {name: $connection | type: string | required: false | desc: Connection name from config.}; {name: $rows | type: int | required: false | desc: Number of affected or returned rows.}]

#AI:cache
#AI group: Recording
#AI frequency: high
#AI signature: public static function cache(string $op, string $key, bool $hit = false, ?int $ttl = null, string $driver = 'redis'): void
#AI contract: Records a cache operation event with operation type, key, hit/miss status, optional TTL, and driver name. No-op when disabled.
#AI param_details: [{name: $op | type: string | required: true | desc: Operation name (get, set, delete, remember).}; {name: $key | type: string | required: true | desc: Cache key operated on.}; {name: $hit | type: bool | required: false | desc: Whether the operation was a cache hit.}; {name: $ttl | type: ?int | required: false | desc: TTL in seconds, or null for reads/deletes.}; {name: $driver | type: string | required: false | desc: Active cache driver name.}]

#AI:view
#AI group: Recording
#AI frequency: medium
#AI signature: public static function view(string $template, ?string $fragment = null, float $ms = 0.0): void
#AI contract: Records a template render event with template path, optional fragment name, and render time. No-op when disabled.
#AI param_details: [{name: $template | type: string | required: true | desc: Template path relative to views directory.}; {name: $fragment | type: ?string | required: false | desc: Fragment name for partial renders, null for full page.}; {name: $ms | type: float | required: false | desc: Render time in milliseconds.}]

#AI:log
#AI group: Recording
#AI frequency: medium
#AI signature: public static function log(string $level, string $message, array $context = [], string $file = '', int $line = 0): void
#AI contract: Records a log entry with level, message, context, and source location from debug_backtrace. No-op when disabled.
#AI param_details: [{name: $level | type: string | required: true | desc: Log level (debug, info, warning, error).}; {name: $message | type: string | required: true | desc: Log message.}; {name: $context | type: array | required: false | desc: Additional context data.}; {name: $file | type: string | required: false | desc: Source file from debug_backtrace.}; {name: $line | type: int | required: false | desc: Source line from debug_backtrace.}]

#AI:summary
#AI group: Reading
#AI frequency: medium
#AI signature: public static function summary(): array
#AI contract: Returns an aggregate summary of all collected events grouped by type, with counts and timing totals for toolbar badge display.
#AI return_detail: {type: array | desc: Associative array with db, cache, views, viewMs, and logs keys.}

#AI:events
#AI group: Reading
#AI frequency: medium
#AI signature: public static function events(): array
#AI contract: Returns all raw events collected during the current request. Used by the toolbar renderer to build detailed panels.
#AI return_detail: {type: array | desc: Array of event arrays in insertion order.}

#AI:reset
#AI group: Reading
#AI frequency: low
#AI signature: public static function reset(): void
#AI contract: Clears the event buffer without disabling the profiler. Call in tests between requests to isolate per-request data.
#AI side_effects: [clears static $events array]
