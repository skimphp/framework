<?php declare(strict_types=1);

namespace Skim\Worker;

use Skim\Core\App;
use Skim\Db\Db;
use Skim\Events\Event;
use Skim\Dev\Profiler;
use Skim\Dev\RequestTrace;

/**
 * Runtime per-request leak detector for worker mode (dev/CI only). #AI:class
 *
 * Snapshots boundary state at begin_request and, after worker_reset has run at
 * end_request, flags hard invariant violations immediately and sustained growth
 * trends over a sliding window. Off unless WORKER_MODE + debug, or explicitly
 * configured. Reports via log/request_trace/profiler — never throws mid-request.
 */
final class LeakDetector {
    private static string $mode = 'off';    // off | warn | strict
    private static int $requestN = 0;
    private static int $warmup = 10;
    private static int $obBaseline = 0;
    private static array $window = [];       // ring buffer of metric samples
    private static array $findings = [];    // strict-mode accumulator (deduped)
    private static array $reported = [];    // dedupe set for warn mode

    public static function configure(string $mode): void {
        self::$mode = $mode;
    }

    public static function isActive(): bool {
        return self::$mode !== 'off';
    }

    public static function begin(): void {
        if (self::$mode === 'off') return;
        self::$obBaseline = ob_get_level();
    }

    /**
     * Called at the END of endRequest(), AFTER WorkerReset::apply().
     */
    public static function check(\Skim\Core\App $app): void {
        if (self::$mode === 'off') return;
        self::$requestN++;

        // --- 1. Hard invariants (immediate, deterministic) ---
        self::checkHardInvariants($app);

        // --- 2. Soft growth trends (after warmup window) ---
        if (self::$requestN > self::$warmup) {
            self::checkGrowthTrends($app);
        }
    }

    private static function checkHardInvariants(\Skim\Core\App $app): void {
        // user scope must be empty after end_request
        if (!$app->userScopeEmpty()) {
            self::report('hard.user_scope', 'User scope not empty after end_request');
        }

        // output buffer level must match baseline
        if (ob_get_level() !== self::$obBaseline) {
            self::report('hard.ob_level', 'Output buffer level mismatch after end_request');
        }

        // no pooled DB connection left in transaction
        if (\Skim\Db\Db::hasOpenTransaction()) {
            self::report('hard.db_transaction', 'Open database transaction after end_request');
        }

        // request-scoped bindings must be dropped from resolved
        foreach ($app->requestScopedServices() as $abstract) {
            if (in_array($abstract, $app->resolvedServices(), true)) {
                self::report('hard.request_scoped_cached', "Request-scoped binding '{$abstract}' still in resolved cache");
            }
        }
    }

    private static function checkGrowthTrends(\Skim\Core\App $app): void {
        $sample = [
            'memory_real'    => memory_get_usage(true),
            'memory_used'    => memory_get_usage(false),
            'resolved_count' => count($app->resolvedServices()),
            'listener_count' => \Skim\Events\Event::totalListenerCount(),
            'db_conn_count'  => \Skim\Db\Db::connectionCount(),
        ];

        self::$window[] = $sample;
        if (count(self::$window) > 50) {
            array_shift(self::$window);
        }

        if (count(self::$window) < 10) {
            return; // need minimum window after warmup
        }

        self::checkMonotonic('growth.memory_real', array_column(self::$window, 'memory_real'));
        self::checkMonotonic('growth.memory_used', array_column(self::$window, 'memory_used'));
        self::checkMonotonic('growth.resolved_count', array_column(self::$window, 'resolved_count'));
        self::checkMonotonic('growth.listener_count', array_column(self::$window, 'listener_count'));
        self::checkMonotonic('growth.db_conn_count', array_column(self::$window, 'db_conn_count'));
    }

    private static function checkMonotonic(string $key, array $series): void {
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
        $dedupeKey = $key . ':' . md5($message);
        if (isset(self::$reported[$dedupeKey])) return;
        self::$reported[$dedupeKey] = true;

        $finding = ['key' => $key, 'message' => $message, 'request_n' => self::$requestN];

        if (self::$mode === 'strict') {
            self::$findings[] = $finding;
        }

        // Always log + trace regardless of mode (warn gets logged, strict gets both)
        \Skim\Log\Log::warning("[leak] {$message}", $finding);
        \Skim\Dev\RequestTrace::event('leak.detected', $finding);
        \Skim\Dev\Profiler::panel('leaks', 'Leaks', ['count' => count(self::$findings) + count(self::$reported)]);
    }

    /** @return list<array> */
    public static function findings(): array {
        return self::$findings;
    }

    public static function reset(): void {
        self::$mode = 'off';
        self::$requestN = 0;
        self::$obBaseline = 0;
        self::$window = [];
        self::$findings = [];
        self::$reported = [];
    }
}
