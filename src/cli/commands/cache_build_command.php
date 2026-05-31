<?php declare(strict_types=1);

namespace skim\cli\commands;

use skim\cli\command;
use skim\core\app;
use skim\core\config;
use skim\core\env;

/**
 * CLI command that pre-compiles env and config into pure PHP array caches.
 *
 * Use in deployment pipelines or after config changes to generate
 * storage/cache/env.php and storage/cache/config.php — plain `return [...]`
 * files that OPcache can serve from shared memory with zero parse overhead.
 *
 * Example:
 *   php skim cache:build
 *
 * Testing: Run against a temp directory; verify generated files are valid PHP arrays.
 *
 * #AI:class
 */
class cache_build_command extends command {
    /**
     * Builds compiled cache files for env, config, and extensions. #AI:handle
     *
     * Generates three files in storage/cache/:
     * - env.php: compiled .env values as a pure PHP array
     * - config.php: compiled config/*.php values as a pure PHP array
     * - extensions.php: extension manifest from sys.extensions
     *
     * WHY: Pure arrays allow OPcache shared-memory hit with zero parse overhead.
     */
    public function handle(): int {
        $cache_dir = base_path('storage/cache');
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0755, true);
        }

        env::reset();
        env::load(base_path('.env'));
        file_put_contents(
            $cache_dir . '/env.php',
            '<?php return ' . var_export(env::all(), true) . ';'
        );

        config::reset();
        config::load(base_path('config'));
        file_put_contents(
            $cache_dir . '/config.php',
            '<?php return ' . var_export(config::all(), true) . ';'
        );

        $app  = app::instance();
        $exts = $app->get('sys.extensions', []);
        file_put_contents(
            $cache_dir . '/extensions.php',
            '<?php return ' . var_export($exts, true) . ';'
        );

        $this->success('Cache built successfully.');
        return 0;
    }
}

#AI:class
#AI symbol: skim\cli\commands\cache_build_command
#AI source_path: src/cli/commands/cache_build_command.php
#AI title: cache_build_command
#AI description: CLI command that pre-compiles env, config, and extensions into pure PHP array cache files for OPcache.
#AI role: CLI cache build command
#AI layer: cli
#AI badges: [cli; command; cache; build; opcache]
#AI intro: `cache_build_command` provides the `php skim cache:build` CLI entry point. It generates compiled PHP array files in `storage/cache/` for env, config, and extensions, enabling OPcache shared-memory hits with zero parse overhead on subsequent requests.
#AI lifecycle: instantiated by CLI kernel, handle() called once per invocation
#AI fallback: creates storage/cache/ directory if missing
#AI test_seam: run against temp directory, verify generated files are valid PHP arrays
#AI invariants: [Generates env.php, config.php, and extensions.php in storage/cache/; Resets env and config before loading to ensure fresh state; Creates cache directory recursively if missing]
#AI core_behaviors: [Resets and reloads env from .env; Resets and reloads config from config/; Reads sys.extensions from app container; Writes var_export() output as PHP return statements]
#AI warnings: [Calls app::instance() which triggers full boot — ensure .env and config/ are present]
#AI owns: nothing — reads from env, config, and app facades
#AI entry_points: [handle]
#AI config_reads: []
#AI non_goals: [Does not clear existing cache files before building; Does not validate generated files]
#AI side_effects: [Writes env.php, config.php, extensions.php to storage/cache/; Creates storage/cache/ directory]
#AI flow: cache_build_command::handle() -> mkdir storage/cache -> env::reset + load + write -> config::reset + load + write -> app extensions write -> success
#AI lifecycle_steps: [kernel dispatches cache_build_command; -> handle(); -> mkdir storage/cache; -> env::reset(); -> env::load(.env); -> write env.php; -> config::reset(); -> config::load(config/); -> write config.php; -> app::instance(); -> write extensions.php; -> success]
#AI section_order: [Command Execution]
#AI architectural_notes: Thin CLI wrapper that delegates to env, config, and app facades. The generated files are consumed by env::load_compiled_cache() and config::load_compiled_cache().

#AI:handle
#AI group: Command Execution
#AI frequency: low
#AI signature: public function handle(): int
#AI contract: Builds compiled cache files for env, config, and extensions in storage/cache/. Resets env and config before loading to ensure fresh state. Creates the cache directory if missing.
#AI return_detail: {type: int | desc: 0 on success.}
