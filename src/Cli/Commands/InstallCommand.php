<?php declare(strict_types=1);

namespace Skim\Cli\Commands;

use Skim\Cli\Cli;
use Skim\Cli\Command;

/**
 * Interactive installer that generates .env from user prompts and optionally runs migrations.
 *
 * Use when setting up a new SKIM project or regenerating .env after changes.
 * Prompts for application name, environment, database, cache, and logging config.
 * Supports --force to overwrite existing .env and --no-migrate to skip migrations.
 *
 * Example:
 *   php skim install              # interactive wizard
 *   php skim install --force      # overwrite existing .env without prompting
 *   php skim install --no-migrate # skip migration step
 *
 * Testing: Not designed for automated testing — uses interactive STDIN prompts.
 *
 * #AI:class
 */
class InstallCommand extends \Skim\Cli\Command {
    /**
     * Runs the interactive installation wizard. #AI:handle
     *
     * Prompts for app config, database, cache, and logging settings. Writes .env,
     * reloads env/config, and optionally runs migrations. Existing .env is preserved
     * unless --force is passed or user confirms overwrite.
     *
     * WARNING: Without --force, prompts before overwriting an existing .env file.
     */
    public function handle(): int {
        \Skim\Cli\Cli::bold('SKIM Framework Installer');
        \Skim\Cli\Cli::line();

        $env_path = basePath('.env');
        $force    = (bool) $this->flag('force', false);

        if (file_exists($env_path) && !$force) {
            $overwrite = \Skim\Cli\Cli::confirm('.env already exists. Overwrite?', false);
            if (!$overwrite) {
                \Skim\Cli\Cli::info('Installation cancelled. Existing .env kept.');
                return 0;
            }
        }

        \Skim\Cli\Cli::info('[ Application ]');
        $app_name  = \Skim\Cli\Cli::ask('Application name', 'SKIM App');
        $app_env   = \Skim\Cli\Cli::choice('Environment', ['local', 'staging', 'production'], 'local');
        $app_debug = ($app_env === 'local') ? 'true' : 'false';
        $app_key   = $this->generateKey();

        \Skim\Cli\Cli::line();
        \Skim\Cli\Cli::info('[ Database ]');
        $db_driver = \Skim\Cli\Cli::choice('DB driver', ['mysql', 'pgsql', 'sqlite'], 'mysql');

        if ($db_driver === 'sqlite') {
            $db_host = '';
            $db_port = '';
            $db_name = \Skim\Cli\Cli::ask('SQLite file path', basePath('database/app.sqlite'));
            $db_user = '';
            $db_pass = '';
        } else {
            $default_host = $db_driver === 'mysql' ? 'mysql' : 'pgsql';
            $default_port = $db_driver === 'mysql' ? '3306' : '5432';
            $db_host = \Skim\Cli\Cli::ask('DB host', $default_host);
            $db_port = \Skim\Cli\Cli::ask('DB port', $default_port);
            $db_name = \Skim\Cli\Cli::ask('DB name', 'skim_dev');
            $db_user = \Skim\Cli\Cli::ask('DB user', 'skim');
            $db_pass = \Skim\Cli\Cli::ask('DB password', 'secret');
        }

        \Skim\Cli\Cli::line();
        \Skim\Cli\Cli::info('[ Cache ]');
        $cache_driver = \Skim\Cli\Cli::choice('Cache driver', ['redis', 'file', 'array'], 'redis');

        $redis_host = 'redis';
        $redis_port = '6379';
        $redis_pass = '';
        $redis_db   = '0';

        if ($cache_driver === 'redis') {
            $redis_host = \Skim\Cli\Cli::ask('Redis host', 'redis');
            $redis_port = \Skim\Cli\Cli::ask('Redis port', '6379');
            $redis_pass = \Skim\Cli\Cli::ask('Redis password (leave blank for none)', '');
        }

        \Skim\Cli\Cli::line();
        \Skim\Cli\Cli::info('[ Logging ]');
        $log_channel = \Skim\Cli\Cli::choice('Log channel', ['file', 'null'], 'file');
        $log_level   = \Skim\Cli\Cli::choice('Log level', ['debug', 'info', 'warning', 'error'], 'debug');

        $env = $this->buildEnv([
            'APP_NAME'     => "\"{$app_name}\"",
            'APP_ENV'      => $app_env,
            'APP_DEBUG'    => $app_debug,
            'APP_KEY'      => $app_key,
            'DB_DRIVER'    => $db_driver,
            'DB_HOST'      => $db_host,
            'DB_PORT'      => $db_port,
            'DB_NAME'      => $db_name,
            'DB_USER'      => $db_user,
            'DB_PASS'      => $db_pass,
            'CACHE_DRIVER' => $cache_driver,
            'REDIS_HOST'   => $redis_host,
            'REDIS_PORT'   => $redis_port,
            'REDIS_PASS'   => $redis_pass,
            'REDIS_DB'     => $redis_db,
            'LOG_CHANNEL'  => $log_channel,
            'LOG_LEVEL'    => $log_level,
        ]);

        file_put_contents($env_path, $env);
        \Skim\Cli\Cli::line();
        \Skim\Cli\Cli::success('.env written to ' . $env_path);

        \Skim\Core\Env::reset();
        \Skim\Core\Env::load($env_path);
        \Skim\Core\Config::reset();
        \Skim\Core\Config::load(basePath('config'));

        if (!$this->flag('no-migrate', false)) {
            \Skim\Cli\Cli::line();
            $run_mig = \Skim\Cli\Cli::confirm('Run migrations now?', true);
            if ($run_mig) {
                $migrate = new \Skim\Cli\Commands\MigrateCommand();
                $migrate->setInput([], []);
                return $migrate->handle();
            }
        }

        \Skim\Cli\Cli::line();
        \Skim\Cli\Cli::success('Installation complete. Run: php skim serve');
        return 0;
    }

    /**
     * Generates a base64-encoded 32-byte APP_KEY. #AI:generateKey
     */
    private function generateKey(): string {
        $bytes = random_bytes(32);
        return 'base64:' . base64_encode($bytes);
    }

    /**
     * Formats key-value pairs as .env file content. #AI:buildEnv
     *
     * @param array $vars Associative array of ENV_KEY => value pairs.
     */
    private function buildEnv(array $vars): string {
        $lines = [];
        foreach ($vars as $key => $value) {
            $lines[] = "{$key}={$value}";
        }
        return implode("\n", $lines) . "\n";
    }
}

#AI:class
#AI symbol: Skim\Cli\Commands\InstallCommand
#AI source_path: src/cli/commands/install_command.php
#AI title: install_command
#AI description: Interactive CLI installer that generates .env from prompts and optionally runs migrations.
#AI role: CLI interactive installer
#AI layer: cli
#AI badges: [cli; command; installer; interactive; setup]
#AI intro: `install_command` implements the `php skim install` CLI command. It provides an interactive wizard that prompts for application, database, cache, and logging configuration, writes the results to `.env`, reloads env/config, and optionally runs migrations.
#AI lifecycle: instantiated by kernel, handle() called once per invocation
#AI fallback: existing .env is preserved unless --force or user confirms overwrite
#AI test_seam: not designed for automated testing due to interactive STDIN prompts
#AI invariants: [APP_KEY is always freshly generated; debug defaults to true for local env; migrations run with newly written config after env reload]
#AI core_behaviors: [Interactive prompts via cli::ask/confirm/choice; Generates cryptographic APP_KEY; Writes .env file; Reloads env and config after writing; Optionally delegates to migrate_command]
#AI warnings: [Overwrites .env when --force is passed or user confirms; DB password is shown in plain text during prompt]
#AI owns: nothing — writes .env file and delegates migrations
#AI entry_points: [handle]
#AI config_reads: []
#AI non_goals: [Does not install composer dependencies; Does not create database; Does not configure Docker]
#AI side_effects: [writes .env file; reloads env and config; optionally runs migrations via migrate_command]
#AI flow: install_command::handle() -> prompt app/db/cache/log config -> build_env() -> file_put_contents(.env) -> env::reset/load -> config::reset/load -> optionally migrate_command::handle()
#AI lifecycle_steps: [handle(); -> check existing .env; -> prompt application config; -> prompt database config; -> prompt cache config; -> prompt logging config; -> build_env(); -> file_put_contents(.env); -> env::reset() + env::load(); -> config::reset() + config::load(); -> optionally migrate_command::handle()]
#AI section_order: [Command Execution; Key Generation; Environment Building]
#AI architectural_notes: After writing .env, the command reloads env and config so that subsequent migration runs use the newly written configuration values.

#AI:handle
#AI group: Command Execution
#AI frequency: low
#AI signature: public function handle(): int
#AI contract: Runs the interactive installation wizard. Prompts for config, writes .env, reloads env/config, and optionally runs migrations.
#AI return_detail: {type: int | desc: 0 on success or cancellation, 1 on migration failure.}
#AI warnings: [Overwrites .env when --force is passed or user confirms overwrite]

#AI:generateKey
#AI group: Key Generation
#AI frequency: internal
#AI signature: private function generateKey(): string
#AI contract: Generates a base64-encoded 32-byte cryptographic key for APP_KEY.
#AI return_detail: {type: string | desc: Key in format 'base64:<encoded>'.}

#AI:buildEnv
#AI group: Environment Building
#AI frequency: internal
#AI signature: private function buildEnv(array $vars): string
#AI contract: Formats key-value pairs as .env file content with one KEY=VALUE per line.
#AI param_details: [{name: $vars | type: array | required: true | desc: Associative array of ENV_KEY => value pairs.}]
#AI return_detail: {type: string | desc: Formatted .env file content.}
