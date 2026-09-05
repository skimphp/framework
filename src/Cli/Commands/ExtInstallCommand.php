<?php declare(strict_types=1);

namespace Skim\Cli\Commands;

use Skim\Cli\Cli;
use Skim\Cli\Command;
use Skim\Db\Db;
use Skim\Ext\ExtMigrator;
use Skim\Ext\ExtRegistry;

/**
 * Installs a SKIM extension: runs composer require, publishes config, runs migrations.
 *
 * Use when adding a new SKIM extension package to the project.
 * Performs a preflight DB check, runs composer require, discovers the extension
 * via ext_registry, publishes config, runs extension migrations atomically
 * (rolls back on failure), and prints .env additions and post-install steps.
 *
 * Example:
 *   php skim ext:install skim/auth
 *   php skim ext:install auth --prefix=myapp_ --no-interaction
 *
 * Testing: Inject mock ext_registry and ext_migrator via constructor.
 *
 * #AI:class
 */
class ExtInstallCommand extends \Skim\Cli\Command {
    /**
     * Accepts optional test doubles for registry and migration executor. #AI:__construct
     *
     * @param \Skim\Ext\ExtRegistry|null $registry Test double for extension registry.
     * @param \Skim\Ext\ExtMigrator|null $migrator Test double for migration executor.
     */
    public function __construct(
        private readonly ?\Skim\Ext\ExtRegistry $registry = null,
        private readonly ?\Skim\Ext\ExtMigrator $migrator = null,
    ) {}

    /**
     * Runs the full extension installation pipeline. #AI:handle
     *
     * Steps: resolve package name → preflight DB check → composer require →
     * discover extension → publish config → run migrations (atomic) → print hints.
     * On migration failure, all migrations applied in this session are rolled back.
     *
     * Example:
     *   php skim ext:install skim/auth --prefix=myapp_
     */
    public function handle(): int {
        $package = $this->resolve_package((string) $this->arg(0, ''));
        if ($package === '') {
            $this->error('Usage: php skim ext:install <name|vendor/name>');
            return 1;
        }

        if (!$this->preflight()) {
            return 1;
        }

        $no_interaction = (bool) $this->flag('no-interaction', false);
        $composer = $this->composer_require($package, $no_interaction);
        if ($composer['code'] !== 0) {
            $this->error('Composer install failed.');
            if ($composer['output'] !== '') {
                $this->line($composer['output']);
            }
            return 1;
        }

        $registry = $this->registry();
        $registry->refresh();
        $extension = $registry->find($package);
        if ($extension === null) {
            $this->error("Package '{$package}' does not declare extra.skim.extension.");
            return 1;
        }

        $config_path = $this->publish_config($extension);
        if ($config_path !== null) {
            $prefix = $this->table_prefix($no_interaction);
            $this->write_table_prefix($config_path, $prefix);
        }

        $migrator = $this->migrator();
        try {
            $applied = $migrator->run($package, $extension['path'] . '/migrations');
            foreach ($applied as $entry) {
                $this->success('Migrated: ' . $entry['filename']);
            }
        } catch (\Throwable $e) {
            foreach ($migrator->rollback_session($migrator->applied_this_session()) as $filename) {
                $this->warn('Rolled back: ' . $filename);
            }
            $this->error('Installation failed - changes rolled back.');
            $this->muted($e->getMessage());
            return 1;
        }

        $this->print_env_additions($extension);
        $this->print_post_install($extension);

        $this->success("{$package} installed successfully.");
        return 0;
    }

    /**
     * Normalizes short names to vendor/package format. #AI:resolve_package
     *
     * @param string $name Raw package name from CLI arg.
     */
    private function resolve_package(string $name): string {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        return str_contains($name, '/') ? $name : "skim/{$name}";
    }

    /**
     * Verifies PHP version and database connectivity before installation. #AI:preflight
     */
    private function preflight(): bool {
        if (PHP_VERSION_ID < 80500) {
            $this->error('SKIM extensions require PHP >= 8.5.');
            return false;
        }

        try {
            \Skim\Db\Db::val('SELECT 1');
            $driver = \Skim\Db\Db::pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
            $this->success("Database: {$driver}");
            return true;
        } catch (\Throwable $e) {
            $this->error('Database is not reachable: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Runs composer require as a subprocess with optional streaming output. #AI:composer_require
     *
     * @param string $package        Composer package name.
     * @param bool   $no_interaction Suppress interactive prompts.
     * @return array{code:int,output:string}
     */
    private function composer_require(string $package, bool $no_interaction): array {
        $cmd = ['composer', 'require', $package];
        if ($no_interaction) {
            $cmd[] = '--no-interaction';
        }

        $process = proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, base_path());

        if (!is_resource($process)) {
            return ['code' => 1, 'output' => 'Unable to start composer.'];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = '';
        $stream_output = \Skim\Cli\Cli::is_tty() && !$no_interaction;

        do {
            $chunk = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            foreach ([$chunk, $error] as $part) {
                if ($part === false || $part === '') {
                    continue;
                }
                $output .= $part;
                if ($stream_output) {
                    echo $part;
                }
            }
            $status = proc_get_status($process);
            if ($status['running']) {
                usleep(50000);
            }
        } while ($status['running']);

        $output .= (string) stream_get_contents($pipes[1]);
        $output .= (string) stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $code = (int) ($status['exitcode'] ?? 1);
        $closed_code = proc_close($process);
        if ($code < 0 && $closed_code >= 0) {
            $code = $closed_code;
        }

        return ['code' => $code, 'output' => trim($output)];
    }

    /**
     * Copies extension config file to the project config directory. #AI:publish_config
     *
     * @param array $extension Extension metadata from registry.
     * @return string|null Path to published config, or null if no config to publish.
     */
    private function publish_config(array $extension): ?string {
        $slug = $this->package_slug($extension['name']);
        $source = $extension['path'] . "/config/{$slug}.php";
        if (!is_file($source)) {
            return null;
        }

        $destination = base_path("config/{$slug}.php");
        if (is_file($destination)) {
            $this->info("config/{$slug}.php already exists - skipping");
            return $destination;
        }

        if (!copy($source, $destination)) {
            throw new \RuntimeException("Unable to publish config/{$slug}.php");
        }

        $this->success("Published config/{$slug}.php");
        return $destination;
    }

    /**
     * Resolves table prefix from flag, interaction mode, or interactive prompt. #AI:table_prefix
     *
     * @param bool $no_interaction Skip interactive prompts.
     */
    private function table_prefix(bool $no_interaction): string {
        $flag = $this->flag('prefix', null);
        if (is_string($flag) && $flag !== '') {
            return $flag;
        }

        if ($no_interaction || !\Skim\Cli\Cli::is_tty()) {
            return 'skim_';
        }

        return \Skim\Cli\Cli::ask('Table prefix', 'skim_');
    }

    /**
     * Writes the table_prefix value into the published config file. #AI:write_table_prefix
     *
     * @param string $config_path Path to the published config file.
     * @param string $prefix      Table prefix to write.
     */
    private function write_table_prefix(string $config_path, string $prefix): void {
        $config = require $config_path;
        if (!is_array($config)) {
            return;
        }

        $config['table_prefix'] = $prefix;
        $export = var_export($config, true);
        file_put_contents($config_path, "<?php declare(strict_types=1);\n\nreturn {$export};\n");
    }

    /**
     * Prints missing .env keys that the extension requires. #AI:print_env_additions
     *
     * @param array $extension Extension metadata with 'env' key.
     */
    private function print_env_additions(array $extension): void {
        $missing = array_values(array_filter(
            $extension['env'],
            fn(string $key): bool => !$this->env_has($key),
        ));

        if ($missing === []) {
            return;
        }

        $this->line();
        $this->line('Add to your .env:');
        $this->line();
        foreach ($missing as $key) {
            $this->line("{$key}=");
        }
    }

    /**
     * Prints post-install next steps from extension metadata. #AI:print_post_install
     *
     * @param array $extension Extension metadata with 'post_install' key.
     */
    private function print_post_install(array $extension): void {
        if ($extension['post_install'] === []) {
            return;
        }

        $this->line();
        $this->line('Next steps:');
        foreach ($extension['post_install'] as $step) {
            $this->line('  ' . (string) $step);
        }
    }

    /**
     * Checks if a key already exists in the .env file. #AI:env_has
     *
     * @param string $key Environment variable name to check.
     */
    private function env_has(string $key): bool {
        $path = base_path('.env');
        if (!is_file($path)) {
            return false;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=/', $line) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Converts a package name to a filesystem-safe slug. #AI:package_slug
     *
     * @param string $package Composer package name.
     */
    private function package_slug(string $package): string {
        return str_replace(['/', '-'], '_', $package);
    }

    /**
     * Returns the injected or default extension registry. #AI:registry
     */
    private function registry(): \Skim\Ext\ExtRegistry {
        return $this->registry ?? new \Skim\Ext\ExtRegistry(base_path());
    }

    /**
     * Returns the injected or default extension migrator. #AI:migrator
     */
    private function migrator(): \Skim\Ext\ExtMigrator {
        return $this->migrator ?? new \Skim\Ext\ExtMigrator();
    }
}

#AI:class
#AI symbol: Skim\Cli\Commands\ExtInstallCommand
#AI source_path: src/cli/commands/ext_install_command.php
#AI title: ext_install_command
#AI description: CLI command that installs a SKIM extension package with composer, config publishing, and atomic migrations.
#AI role: CLI extension installer
#AI layer: cli
#AI badges: [cli; command; extension; installer; atomic-migrations]
#AI intro: `ext_install_command` implements the `php skim ext:install` CLI command. It runs the full installation pipeline: preflight DB check, composer require, extension discovery, config publishing, atomic migration execution (with rollback on failure), and post-install hints.
#AI lifecycle: instantiated by kernel, handle() called once per invocation
#AI fallback: migration failure triggers automatic rollback of all migrations applied in this session
#AI test_seam: constructor accepts ext_registry and ext_migrator test doubles
#AI invariants: [short package names are prefixed with skim/; migrations are atomic — all or nothing; config is not overwritten if it already exists]
#AI core_behaviors: [Preflight checks PHP version and DB connectivity; Composer require runs as subprocess with optional TTY streaming; Extension discovery via ext_registry after composer install; Config publishing copies to project config dir; Migrations run atomically with rollback on failure]
#AI warnings: [Migration failure rolls back all migrations applied in this session; composer require modifies vendor/ and composer.json]
#AI owns: nothing — delegates to ext_registry and ext_migrator
#AI entry_points: [handle]
#AI config_reads: []
#AI non_goals: [Does not manage extension updates; Does not uninstall extensions; Does not validate extension compatibility]
#AI side_effects: [Runs composer require subprocess; Copies config files; Runs database migrations; Writes to .env hints on stdout]
#AI flow: ext_install_command::handle() -> resolve_package() -> preflight() -> composer_require() -> registry->refresh() -> registry->find() -> publish_config() -> migrator->run() -> print hints
#AI lifecycle_steps: [handle(); -> resolve_package(arg(0)); -> preflight() checks PHP+DB; -> composer_require(package); -> registry->refresh(); -> registry->find(package); -> publish_config(extension); -> table_prefix(); -> write_table_prefix(); -> migrator->run(); -> on failure: rollback_session(); -> print_env_additions(); -> print_post_install()]
#AI section_order: [Command Execution; Installation Pipeline; Configuration; Architecture]
#AI architectural_notes: Constructor injection of ext_registry and ext_migrator enables full test isolation without filesystem or database side-effects.

#AI:__construct
#AI group: Architecture
#AI frequency: low
#AI signature: public function __construct(?ext_registry $registry = null, ?ext_migrator $migrator = null)
#AI contract: Accepts optional test doubles for extension registry and migration executor. Defaults are created lazily.
#AI param_details: [{name: $registry | type: ?ext_registry | required: false | desc: Test double for extension registry. Null uses default.}; {name: $migrator | type: ?ext_migrator | required: false | desc: Test double for migration executor. Null uses default.}]

#AI:handle
#AI group: Command Execution
#AI frequency: low
#AI signature: public function handle(): int
#AI contract: Runs the full extension installation pipeline: resolve package, preflight, composer require, discover, publish config, run migrations atomically, print hints.
#AI return_detail: {type: int | desc: 0 on success, 1 on any failure.}
#AI warnings: [Migration failure triggers rollback of all migrations applied in this session]

#AI:resolve_package
#AI group: Installation Pipeline
#AI frequency: internal
#AI signature: private function resolve_package(string $name): string
#AI contract: Normalizes short names to vendor/package format. Empty input returns empty string.
#AI param_details: [{name: $name | type: string | required: true | desc: Raw package name from CLI arg.}]
#AI return_detail: {type: string | desc: Normalized package name or empty string.}

#AI:preflight
#AI group: Installation Pipeline
#AI frequency: internal
#AI signature: private function preflight(): bool
#AI contract: Verifies PHP >= 8.5 and database connectivity before proceeding with installation.

#AI:composer_require
#AI group: Installation Pipeline
#AI frequency: internal
#AI signature: private function composer_require(string $package, bool $no_interaction): array
#AI contract: Runs composer require as a subprocess. Streams output to TTY when interactive. Returns exit code and captured output.
#AI param_details: [{name: $package | type: string | required: true | desc: Composer package name.}; {name: $no_interaction | type: bool | required: true | desc: Suppress interactive prompts.}]
#AI return_detail: {type: array{code:int,output:string} | desc: Exit code and captured output.}

#AI:publish_config
#AI group: Configuration
#AI frequency: internal
#AI signature: private function publish_config(array $extension): ?string
#AI contract: Copies extension config file to the project config directory. Skips if config already exists.
#AI param_details: [{name: $extension | type: array | required: true | desc: Extension metadata from registry.}]
#AI return_detail: {type: ?string | desc: Path to published config, or null if no config to publish.}

#AI:table_prefix
#AI group: Configuration
#AI frequency: internal
#AI signature: private function table_prefix(bool $no_interaction): string
#AI contract: Resolves table prefix from --prefix flag, non-interactive default, or interactive prompt.

#AI:write_table_prefix
#AI group: Configuration
#AI frequency: internal
#AI signature: private function write_table_prefix(string $config_path, string $prefix): void
#AI contract: Writes the table_prefix value into the published config file.

#AI:print_env_additions
#AI group: Installation Pipeline
#AI frequency: internal
#AI signature: private function print_env_additions(array $extension): void
#AI contract: Prints missing .env keys that the extension requires.

#AI:print_post_install
#AI group: Installation Pipeline
#AI frequency: internal
#AI signature: private function print_post_install(array $extension): void
#AI contract: Prints post-install next steps from extension metadata.

#AI:env_has
#AI group: Architecture
#AI frequency: internal
#AI signature: private function env_has(string $key): bool
#AI contract: Checks if a key already exists in the .env file.

#AI:package_slug
#AI group: Architecture
#AI frequency: internal
#AI signature: private function package_slug(string $package): string
#AI contract: Converts a package name to a filesystem-safe slug by replacing / and - with _.

#AI:registry
#AI group: Architecture
#AI frequency: internal
#AI signature: private function registry(): ext_registry
#AI contract: Returns the injected or default extension registry.

#AI:migrator
#AI group: Architecture
#AI frequency: internal
#AI signature: private function migrator(): ext_migrator
#AI contract: Returns the injected or default extension migrator.
