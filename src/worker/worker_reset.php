<?php declare(strict_types=1);

namespace skim\worker;

use skim\cache\cache;
use skim\core\pipeline;
use skim\db\db;
use skim\dev\profiler;
use skim\dev\request_trace;

/**
 * Per-request reset orchestrator for FrankenPHP worker mode.
 *
 * Discovers every class implementing resettable once, then on each request:
 * flushes output buffers, rolls back any open database transactions, resets
 * all request-scoped static facades, and clears middleware singleton cache.
 *
 * #AI:class
 */
final class worker_reset {
    /** @var list<class-string<resettable>> */
    private static array $classes = [];
    // Number of declared classes already scanned for resettable discovery.
    // get_declared_classes() returns classes in declaration order, so we only
    // ever inspect the suffix added since the last scan — fixes the bug where a
    // facade autoloaded lazily on a later request was never discovered (and thus
    // never reset) because discovery was cached after the first request.
    private static int $scanned = 0;

    /**
     * Resets all request-scoped state. Safe to call once per request. #AI:apply
     *
     * Flushes output buffers, rolls back any open database transactions, resets
     * all request-scoped static facades, and clears middleware singleton cache.
     *
     * @param int $preserve_ob_level Output buffers at or below this level are left open.
     *                                Pass ob_get_level() in tests to preserve PHPUnit's buffer.
     */
    public static function apply(int $preserve_ob_level = 0): void {
        self::discover();

        while (ob_get_level() > $preserve_ob_level) {
            ob_end_clean();
        }

        db::rollback_all();

        foreach (self::$classes as $class) {
            $class::reset_request();
        }

        profiler::reset();
        request_trace::reset();
        pipeline::reset_instance_cache();
    }

    /**
     * Incrementally discovers classes implementing resettable. #AI:discover
     *
     * Only inspects classes declared since the previous call, so the cost is
     * amortised to near zero after warmup while still picking up facades that
     * are autoloaded lazily on later requests.
     */
    public static function discover(): void {
        $all = get_declared_classes();
        $count = count($all);

        for ($i = self::$scanned; $i < $count; $i++) {
            if (is_subclass_of($all[$i], resettable::class)) {
                self::$classes[] = $all[$i];
            }
        }

        self::$scanned = $count;
    }

    /**
     * Returns the resettable classes discovered so far. Test seam. #AI:discovered
     *
     * @return list<class-string<resettable>>
     */
    public static function discovered(): array {
        return self::$classes;
    }
}

#AI:class
#AI symbol: skim\worker\worker_reset
#AI source_path: src/worker/worker_reset.php
#AI title: worker_reset
#AI description: Per-request reset orchestrator for FrankenPHP worker mode.
#AI role: reset orchestrator
#AI layer: worker
#AI badges: [worker; reset; lifecycle; frankenphp]
#AI intro: `worker_reset` discovers every class implementing `resettable` once, then on each request flushes output buffers, rolls back open DB transactions, resets all request-scoped static facades, and clears middleware singleton cache.
#AI lifecycle: static, discovered once at first request, apply() called once per request
#AI test_seam: discovered() returns the scanned class list for assertions
#AI invariants: [apply() is safe to call once per request; discover() only inspects new classes since last call; rollback_all() is a no-op when no transactions are open]
#AI core_behaviors: [Incremental discovery via get_declared_classes() suffix scanning; OB level restoration preserving test buffers; Transaction rollback on all pooled connections; Delegates per-facade reset to reset_request() on each discovered class]
#AI warnings: [If a facade implements resettable but is never loaded, it will not be discovered and will not be reset]
#AI owns: discovered class list, scan cursor
#AI entry_points: [apply; discover; discovered]
#AI config_reads: []
#AI non_goals: [Does not reset non-resettable classes; Does not close DB connections — only rolls back transactions]
#AI side_effects: [Flushes output buffers; Rolls back database transactions; Resets profiler and request_trace; Clears pipeline instance cache]
#AI flow: worker entrypoint -> end_request() -> worker_reset::apply() -> discover() -> rollback_all() -> reset_request() on each discovered class -> profiler::reset() / request_trace::reset() / pipeline::reset_instance_cache()
#AI lifecycle_steps: [First request: discover() scans all declared classes; -> apply($preserve_ob_level) flushes buffers; -> rollback_all(); -> foreach discovered class: reset_request(); -> profiler/request_trace reset]; [Subsequent requests: discover() scans only new classes; -> same reset sequence]

#AI:apply
#AI group: Lifecycle
#AI frequency: high
#AI signature: public static function apply(int $preserve_ob_level = 0): void
#AI contract: Resets all request-scoped state. Safe to call once per request. Flushes output buffers down to $preserve_ob_level, rolls back any open DB transactions, resets all discovered resettable facades, and clears profiler/request_trace/pipeline caches.
#AI param_details: [{name: $preserve_ob_level | type: int | required: false | desc: Output buffers at or below this level are left open. Pass ob_get_level() in tests to preserve PHPUnit buffers.}]
#AI side_effects: [Flushes output buffers; Rolls back DB transactions; Resets all resettable facades; Clears profiler and request_trace; Clears pipeline instance cache]

#AI:discover
#AI group: Lifecycle
#AI frequency: high
#AI signature: public static function discover(): void
#AI contract: Incrementally discovers classes implementing resettable. Only inspects classes declared since the previous call, so the cost is amortised to near zero after warmup while still picking up facades autoloaded lazily on later requests.
#AI side_effects: [Mutates $classes and $scanned static properties]

#AI:discovered
#AI group: Testing
#AI frequency: low
#AI signature: public static function discovered(): array
#AI contract: Returns the resettable classes discovered so far. Test seam for verifying that expected facades were picked up by discovery.
#AI return_detail: {type: list<class-string<resettable>> | desc: Discovered resettable class names.}
