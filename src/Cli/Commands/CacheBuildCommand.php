<?php declare(strict_types=1);

namespace Skim\Cli\Commands;

use Skim\Cli\Command;
use Skim\Core\Config;
use Skim\Core\Env;
use Skim\Ext\ExtRegistry;

/**
 * CLI command that pre-compiles env and config into pure PHP array caches.
 *
 * Use in deployment pipelines or after config changes to generate
     * storage/config_cache/env.php and storage/config_cache/config.php — plain `return [...]`
 * files that OPcache can serve from shared memory with zero parse overhead.
 *
 * Example:
 *   php skim cache:build
 *
 * Testing: Run against a temp directory; verify generated files are valid PHP arrays.
 *
 * #AI:class
 */
class CacheBuildCommand extends \Skim\Cli\Command {
    /**
     * Builds compiled cache files for env, config, and extensions. #AI:handle
     *
     * Generates three files in storage/config_cache/:
     * - env.php: compiled .env values as a pure PHP array
     * - config.php: compiled config/*.php values as a pure PHP array
     * - extensions.php: extension manifest from sys.extensions
     *
     * WHY: Pure arrays allow OPcache shared-memory hit with zero parse overhead.
     */
    public function handle(): int {
        $cacheDir = storagePath('config_cache');
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        \Skim\Core\Env::reset();
        \Skim\Core\Env::load(basePath('.env'));
        file_put_contents(
            $cacheDir . '/env.php',
            '<?php return ' . var_export(\Skim\Core\Env::all(), true) . ';'
        );

        \Skim\Core\Config::reset();
        \Skim\Core\Config::load(basePath('config'));
        file_put_contents(
            $cacheDir . '/config.php',
            '<?php return ' . var_export(\Skim\Core\Config::all(), true) . ';'
        );

        $exts = \Skim\Ext\ExtRegistry::all();
        file_put_contents(
            $cacheDir . '/extensions.php',
            '<?php return ' . var_export($exts, true) . ';'
        );

        $this->success('Cache built successfully.');
        return 0;
    }
}

#AI:class
#AI symbol: Skim\Cli\Commands\CacheBuildCommand
#AI source_path: src/Cli/Commands/CacheBuildCommand.php
#AI title: CacheBuildCommand
#AI description: CLI command that pre-compiles env, config, and extensions into pure PHP array cache files for OPcache.
#AI role: CLI cache build command
#AI layer: cli
#AI badges: [cli; command; cache; build; opcache]
#AI intro: `CacheBuildCommand` provides the `php skim cache:build` CLI entry point. It generates compiled PHP array files in `storage/config_cache/` for env, config, and extensions, enabling OPcache shared-memory hits with zero parse overhead on subsequent requests.
#AI lifecycle: instantiated by CLI kernel, handle() called once per invocation
#AI fallback: creates storage/config_cache/ directory if missing
#AI test_seam: run against temp directory, verify generated files are valid PHP arrays
#AI invariants: [Generates env.php, config.php, and extensions.php in storage/config_cache/; Resets env and config before loading to ensure fresh state; Creates cache directory recursively if missing]
#AI core_behaviors: [Resets and reloads env from .env; Resets and reloads config from config/; Reads sys.extensions from app container; Writes var_export() output as PHP return statements]
#AI warnings: [Calls App::instance() which triggers full boot — ensure .env and config/ are present]
#AI owns: nothing — reads from env, config, and app facades
#AI entry_points: [handle]
#AI config_reads: []
#AI non_goals: [Does not clear existing cache files before building; Does not validate generated files]
#AI side_effects: [Writes env.php, config.php, extensions.php to storage/config_cache/; Creates storage/config_cache/ directory]
#AI flow: CacheBuildCommand::handle() -> mkdir storage/config_cache -> Env::reset + load + write -> Config::reset + load + write -> app extensions write -> success
#AI lifecycle_steps: [kernel dispatches CacheBuildCommand; -> handle(); -> mkdir storage/config_cache; -> Env::reset(); -> Env::load(.env); -> write env.php; -> Config::reset(); -> Config::load(config/); -> write config.php; -> App::instance(); -> write extensions.php; -> success]
#AI section_order: [Command Execution]
#AI architectural_notes: Thin CLI wrapper that delegates to env, config, and app facades. The generated files are consumed by Env::loadCompiledCache() and Config::loadCompiledCache().

#AI:handle
#AI group: Command Execution
#AI frequency: low
#AI signature: public function handle(): int
#AI contract: Builds compiled cache files for env, config, and extensions in storage/config_cache/. Resets env and config before loading to ensure fresh state. Creates the cache directory if missing.
#AI return_detail: {type: int | desc: 0 on success.}
