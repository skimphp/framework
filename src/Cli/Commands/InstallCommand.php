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

        $envPath = basePath('.env');
        $force    = (bool) $this->flag('force', false);

        if (file_exists($envPath) && !$force) {
            $overwrite = \Skim\Cli\Cli::confirm('.env already exists. Overwrite?', false);
            if (!$overwrite) {
                \Skim\Cli\Cli::info('Installation cancelled. Existing .env kept.');
                return 0;
            }
        }

        \Skim\Cli\Cli::info('[ Application ]');
        $appName  = \Skim\Cli\Cli::ask('Application name', 'SKIM App');
        $appEnv   = \Skim\Cli\Cli::choice('Environment', ['local', 'staging', 'production'], 'local');
        $appDebug = ($appEnv === 'local') ? 'true' : 'false';
        $appKey   = $this->generateKey();

        \Skim\Cli\Cli::line();
        \Skim\Cli\Cli::info('[ Database ]');
        $dbDriver = \Skim\Cli\Cli::choice('DB driver', ['mysql', 'pgsql', 'sqlite'], 'mysql');

        if ($dbDriver === 'sqlite') {
            $dbHost = '';
            $dbPort = '';
            $dbName = \Skim\Cli\Cli::ask('SQLite file path', basePath('database/app.sqlite'));
            $dbUser = '';
            $dbPass = '';
        } else {
            $defaultHost = $dbDriver === 'mysql' ? 'mysql' : 'pgsql';
            $defaultPort = $dbDriver === 'mysql' ? '3306' : '5432';
            $dbHost = \Skim\Cli\Cli::ask('DB host', $defaultHost);
            $dbPort = \Skim\Cli\Cli::ask('DB port', $defaultPort);
            $dbName = \Skim\Cli\Cli::ask('DB name', 'skim_dev');
            $dbUser = \Skim\Cli\Cli::ask('DB user', 'skim');
            $dbPass = \Skim\Cli\Cli::ask('DB password', 'secret');
        }

        \Skim\Cli\Cli::line();
        \Skim\Cli\Cli::info('[ Cache ]');
        $cacheDriver = \Skim\Cli\Cli::choice('Cache driver', ['redis', 'file', 'array'], 'redis');

        $redisHost = 'redis';
        $redisPort = '6379';
        $redisPass = '';
        $redisDb   = '0';

        if ($cacheDriver === 'redis') {
            $redisHost = \Skim\Cli\Cli::ask('Redis host', 'redis');
            $redisPort = \Skim\Cli\Cli::ask('Redis port', '6379');
            $redisPass = \Skim\Cli\Cli::ask('Redis password (leave blank for none)', '');
        }

        \Skim\Cli\Cli::line();
        \Skim\Cli\Cli::info('[ Logging ]');
        $logChannel = \Skim\Cli\Cli::choice('Log channel', ['file', 'null'], 'file');
        $logLevel   = \Skim\Cli\Cli::choice('Log level', ['debug', 'info', 'warning', 'error'], 'debug');

        $env = $this->buildEnv([
            'APP_NAME'     => "\"{$appName}\"",
            'APP_ENV'      => $appEnv,
            'APP_DEBUG'    => $appDebug,
            'APP_KEY'      => $appKey,
            'DB_DRIVER'    => $dbDriver,
            'DB_HOST'      => $dbHost,
            'DB_PORT'      => $dbPort,
            'DB_NAME'      => $dbName,
            'DB_USER'      => $dbUser,
            'DB_PASS'      => $dbPass,
            'CACHE_DRIVER' => $cacheDriver,
            'REDIS_HOST'   => $redisHost,
            'REDIS_PORT'   => $redisPort,
            'REDIS_PASS'   => $redisPass,
            'REDIS_DB'     => $redisDb,
            'LOG_CHANNEL'  => $logChannel,
            'LOG_LEVEL'    => $logLevel,
        ]);

        file_put_contents($envPath, $env);
        \Skim\Cli\Cli::line();
        \Skim\Cli\Cli::success('.env written to ' . $envPath);

        \Skim\Core\Env::reset();
        \Skim\Core\Env::load($envPath);
        \Skim\Core\Config::reset();
        \Skim\Core\Config::load(basePath('config'));

        if (!$this->flag('no-migrate', false)) {
            \Skim\Cli\Cli::line();
            $runMig = \Skim\Cli\Cli::confirm('Run migrations now?', true);
            if ($runMig) {
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
#AI source_path: src/Cli/Commands/InstallCommand.php
#AI title: InstallCommand
#AI description: Interactive CLI installer that generates .env from prompts and optionally runs migrations.
#AI role: CLI interactive installer
#AI layer: cli
#AI badges: [cli; command; installer; interactive; setup]
#AI intro: `InstallCommand` implements the `php skim install` CLI command. It provides an interactive wizard that prompts for application, database, cache, and logging configuration, writes the results to `.env`, reloads env/config, and optionally runs migrations.
#AI lifecycle: instantiated by kernel, handle() called once per invocation
#AI fallback: existing .env is preserved unless --force or user confirms overwrite
#AI test_seam: not designed for automated testing due to interactive STDIN prompts
#AI invariants: [APP_KEY is always freshly generated; debug defaults to true for local env; migrations run with newly written config after env reload]
#AI core_behaviors: [Interactive prompts via Cli::ask/confirm/choice; Generates cryptographic APP_KEY; Writes .env file; Reloads env and config after writing; Optionally delegates to MigrateCommand]
#AI warnings: [Overwrites .env when --force is passed or user confirms; DB password is shown in plain text during prompt]
#AI owns: nothing — writes .env file and delegates migrations
#AI entry_points: [handle]
#AI config_reads: []
#AI non_goals: [Does not install composer dependencies; Does not create database; Does not configure Docker]
#AI side_effects: [writes .env file; reloads env and config; optionally runs migrations via MigrateCommand]
#AI flow: InstallCommand::handle() -> prompt app/db/cache/log config -> buildEnv() -> file_put_contents(.env) -> Env::reset/load -> Config::reset/load -> optionally MigrateCommand::handle()
#AI lifecycle_steps: [handle(); -> check existing .env; -> prompt application config; -> prompt database config; -> prompt cache config; -> prompt logging config; -> buildEnv(); -> file_put_contents(.env); -> Env::reset() + Env::load(); -> Config::reset() + Config::load(); -> optionally MigrateCommand::handle()]
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
