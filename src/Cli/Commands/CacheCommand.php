<?php declare(strict_types=1);

namespace Skim\Cli\Commands;

use Skim\Cli\Command;
use Skim\Cache\Cache;

/**
 * CLI command that flushes the cache by prefix or clears the entire backend.
 *
 * Use when operators need to invalidate cached data from the terminal.
 * Delegates to Cache::flush() for prefix-based invalidation and
 * Cache::flushAll() when no prefix is given.
 *
 * Example:
 *   php skim cache:clear           # flushes everything
 *   php skim cache:clear user:     # flushes only user:* keys
 *
 * Testing: Inject a mock cache driver via Cache::setDriver() before dispatching.
 *
 * #AI:class
 */
class CacheCommand extends \Skim\Cli\Command {
    /**
     * Dispatches clear/flush sub-commands. #AI:handle
     *
     * WARNING: Running without a prefix argument calls Cache::flushAll(),
     * which clears the ENTIRE active cache backend.
     *
     * Example:
     *   php skim cache:clear user:     # safe — prefix-scoped
     *   php skim cache:clear           # DANGEROUS — full backend flush
     */
    public function handle(): int {
        $sub    = $this->arg(0, 'clear');
        $prefix = $this->arg(1, '');

        if (in_array($sub, ['clear', 'flush'], true)) {
            $prefix !== '' ? \Skim\Cache\Cache::flush($prefix) : \Skim\Cache\Cache::flushAll();
            $msg = $prefix !== '' ? "Cache prefix '{$prefix}' cleared." : 'Cache cleared.';
            $this->success($msg);
            return 0;
        }

        $this->error("Unknown sub-command: {$sub}");
        return 1;
    }
}

#AI:class
#AI symbol: Skim\Cli\Commands\CacheCommand
#AI source_path: src/Cli/Commands/CacheCommand.php
#AI title: CacheCommand
#AI description: CLI command for cache invalidation by prefix or full backend flush.
#AI role: CLI cache invalidation command
#AI layer: cli
#AI badges: [cli; command; cache; destructive]
#AI intro: `CacheCommand` provides the `php skim cache:clear` and `php skim cache:flush` CLI entry points. It delegates to `Cache::flush($prefix)` for prefix-scoped invalidation or `Cache::flushAll()` when no prefix is supplied.
#AI lifecycle: instantiated by CLI kernel, handle() called once per invocation
#AI fallback: none — unknown sub-commands print an error and return exit code 1
#AI test_seam: Cache::setDriver() to inject ArrayDriver, Cache::reset() in tearDown
#AI invariants: [clear and flush sub-commands are treated identically; empty prefix triggers full backend flush]
#AI core_behaviors: [Delegates prefix flush to Cache::flush(); Delegates full flush to Cache::flushAll(); Prints success or error message to stdout]
#AI warnings: [Running `php skim cache:clear` without a prefix calls Cache::flushAll() which clears the entire cache backend]
#AI owns: nothing — delegates all cache operations to cache facade
#AI entry_points: [handle]
#AI config_reads: []
#AI non_goals: [Does not support tag-based invalidation; Does not list cached keys]
#AI side_effects: [Cache::flush() or Cache::flushAll() mutates the active cache backend]
#AI flow: CacheCommand::handle() -> arg(0) sub-command -> Cache::flush(prefix) or Cache::flushAll() -> print result
#AI lifecycle_steps: [kernel dispatches CacheCommand; -> handle(); -> read sub-command from arg(0); -> read prefix from arg(1); -> Cache::flush(prefix) or Cache::flushAll(); -> print success/error]
#AI section_order: [Command Execution]
#AI architectural_notes: Thin CLI wrapper over the cache facade. All cache logic lives in cache.php.

#AI:handle
#AI group: Command Execution
#AI frequency: low
#AI signature: public function handle(): int
#AI contract: Dispatches clear/flush sub-commands. When a prefix is provided, calls Cache::flush($prefix). When no prefix is given, calls Cache::flushAll() which clears the entire backend.
#AI warnings: [Without a prefix argument, the entire cache backend is cleared — prefer prefix-scoped invalidation in production]
#AI return_detail: {type: int | desc: 0 on success, 1 on unknown sub-command.}
