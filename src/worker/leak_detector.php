<?php declare(strict_types=1);

namespace skim\worker;

use skim\core\app;
use skim\db\db;
use skim\events\event;
use skim\dev\profiler;
use skim\dev\request_trace;

/**
 * Runtime per-request leak detector for worker mode (dev/CI only). #AI:class
 *
 * Snapshots boundary state at begin_request and, after worker_reset has run at
 * end_request, flags hard invariant violations immediately and sustained growth
 * trends over a sliding window. Off unless WORKER_MODE + debug, or explicitly
 * configured. Reports via log/request_trace/profiler — never throws mid-request.
 */
final class leak_detector {
    private static string $mode = 'off';    // off | warn | strict
    private static int $request_n = 0;
    private static int $warmup = 10;
    private static int $ob_baseline = 0;
    private static array $window = [];       // ring buffer of metric samples
    private static array $findings = [];    // strict-mode accumulator (deduped)
    private static array $reported = [];    // dedupe set for warn mode

    public static function configure(string $mode): void {
        self::$mode = $mode;
    }

    public static function is_active(): bool {
        return self::$mode !== 'off';
    }

    public static function begin(): void {
        if (self::$mode === 'off') return;
        self::$ob_baseline = ob_get_level();
    }

    /**
     * Called at the END of end_request(), AFTER worker_reset::apply().
     */
    public static function check(app $app): void {
        if (self::$mode === 'off') return;
        self::$request_n++;

        // --- 1. Hard invariants (immediate, deterministic) ---
        self::check_hard_invariants($app);

        // --- 2. Soft growth trends (after warmup window) ---
        if (self::$request_n > self::$warmup) {
            self::check_growth_trends($app);
        }
    }

    private static function check_hard_invariants(app $app): void {
        // user scope must be empty after end_request
        if (!$app->user_scope_empty()) {
            self::report('hard.user_scope', 'User scope not empty after end_request');
        }

        // output buffer level must match baseline
        if (ob_get_level() !== self::$ob_baseline) {
            self::report('hard.ob_level', 'Output buffer level mismatch after end_request');
        }

        // no pooled DB connection left in transaction
        if (db::has_open_transaction()) {
            self::report('hard.db_transaction', 'Open database transaction after end_request');
        }

        // request-scoped bindings must be dropped from resolved
        foreach ($app->request_scoped_services() as $abstract) {
            if (in_array($abstract, $app->resolved_services(), true)) {
                self::report('hard.request_scoped_cached', "Request-scoped binding '{$abstract}' still in resolved cache");
            }
        }
    }

    private static function check_growth_trends(app $app): void {
        $sample = [
            'memory_real'    => memory_get_usage(true),
            'memory_used'    => memory_get_usage(false),
            'resolved_count' => count($app->resolved_services()),
            'listener_count' => event::total_listener_count(),
            'db_conn_count'  => db::connection_count(),
        ];

        self::$window[] = $sample;
        if (count(self::$window) > 50) {
            array_shift(self::$window);
        }

        if (count(self::$window) < 10) {
            return; // need minimum window after warmup
        }

        self::check_monotonic('growth.memory_real', array_column(self::$window, 'memory_real'));
        self::check_monotonic('growth.memory_used', array_column(self::$window, 'memory_used'));
        self::check_monotonic('growth.resolved_count', array_column(self::$window, 'resolved_count'));
        self::check_monotonic('growth.listener_count', array_column(self::$window, 'listener_count'));
        self::check_monotonic('growth.db_conn_count', array_column(self::$window, 'db_conn_count'));
    }

    private static function check_monotonic(string $key, array $series): void {
        if (count($series) < 5) return;

        // Check if the series is monotonically increasing with no plateau
        $growing = true;
        for ($i = 1; $i < count($series); $i++) {
            if ($series[$i] < $series[$i - 1]) {
                $growing = false;
                break;
            }
            // plateau counts as not growing
            if ($series[$i] === $series[$i - 1]) {
                $growing = false;
                break;
            }
        }

        if ($growing) {
            $delta = end($series) - reset($series);
            self::report($key, "Sustained monotonic growth detected (delta: {$delta})");
        }
    }

    private static function report(string $key, string $message): void {
        $dedupe_key = $key . ':' . md5($message);
        if (isset(self::$reported[$dedupe_key])) return;
        self::$reported[$dedupe_key] = true;

        $finding = ['key' => $key, 'message' => $message, 'request_n' => self::$request_n];

        if (self::$mode === 'strict') {
            self::$findings[] = $finding;
        }

        // Always log + trace regardless of mode (warn gets logged, strict gets both)
        \skim\log\log::warning("[leak] {$message}", $finding);
        request_trace::event('leak.detected', $finding);
        profiler::panel('leaks', 'Leaks', ['count' => count(self::$findings) + count(self::$reported)]);
    }

    /** @return list<array> */
    public static function findings(): array {
        return self::$findings;
    }

    public static function reset(): void {
        self::$mode = 'off';
        self::$request_n = 0;
        self::$ob_baseline = 0;
        self::$window = [];
        self::$findings = [];
        self::$reported = [];
    }
}
