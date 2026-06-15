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
    /** @var list<class-string<resettable>>|null */
    private static ?array $classes = null;

    /**
     * Resets all request-scoped state. Safe to call once per request.
     *
     * @return void
     */
    public static function apply(): void {
        if (self::$classes === null) {
            self::$classes = [];
            foreach (get_declared_classes() as $class) {
                if (is_subclass_of($class, resettable::class)) {
                    self::$classes[] = $class;
                }
            }
        }

        while (ob_get_level() > 0) {
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
}
