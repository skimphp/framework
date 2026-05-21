<?php declare(strict_types=1);

namespace skim\cli\commands;

use skim\cli\cli;
use skim\cli\command;

// Interactive installer — generates .env from user prompts and optionally runs migrations.
// Usage:
//   php skim install              → interactive wizard
//   php skim install --force      → overwrite existing .env without prompting
//   php skim install --no-migrate → skip migration step
class install_command extends command {
    public function handle(): int {
        cli::bold('SKIM Framework Installer');
        cli::line();

        $env_path = base_path('.env');
        $force    = (bool) $this->flag('force', false);

        if (file_exists($env_path) && !$force) {
            $overwrite = cli::confirm('.env already exists. Overwrite?', false);
            if (!$overwrite) {
                cli::info('Installation cancelled. Existing .env kept.');
                return 0;
            }
        }

        // --- Application ---
        cli::info('[ Application ]');
        $app_name  = cli::ask('Application name', 'SKIM App');
        $app_env   = cli::choice('Environment', ['local', 'staging', 'production'], 'local');
        $app_debug = ($app_env === 'local') ? 'true' : 'false';
        $app_key   = $this->generate_key();

        // --- Database ---
        cli::line();
        cli::info('[ Database ]');
        $db_driver = cli::choice('DB driver', ['mysql', 'pgsql', 'sqlite'], 'mysql');

        if ($db_driver === 'sqlite') {
            $db_host = '';
            $db_port = '';
            $db_name = cli::ask('SQLite file path', base_path('database/app.sqlite'));
            $db_user = '';
            $db_pass = '';
        } else {
            $default_host = $db_driver === 'mysql' ? 'mysql' : 'pgsql';
            $default_port = $db_driver === 'mysql' ? '3306' : '5432';
            $db_host = cli::ask('DB host', $default_host);
            $db_port = cli::ask('DB port', $default_port);
            $db_name = cli::ask('DB name', 'skim_dev');
            $db_user = cli::ask('DB user', 'skim');
            $db_pass = cli::ask('DB password', 'secret');
        }

        // --- Cache / Redis ---
        cli::line();
        cli::info('[ Cache ]');
        $cache_driver = cli::choice('Cache driver', ['redis', 'file', 'array'], 'redis');

        $redis_host = 'redis';
        $redis_port = '6379';
        $redis_pass = '';
        $redis_db   = '0';

        if ($cache_driver === 'redis') {
            $redis_host = cli::ask('Redis host', 'redis');
            $redis_port = cli::ask('Redis port', '6379');
            $redis_pass = cli::ask('Redis password (leave blank for none)', '');
        }

        // --- Logging ---
        cli::line();
        cli::info('[ Logging ]');
        $log_channel = cli::choice('Log channel', ['file', 'null'], 'file');
        $log_level   = cli::choice('Log level', ['debug', 'info', 'warning', 'error'], 'debug');

        // --- Write .env ---
        $env = $this->build_env([
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
        cli::line();
        cli::success('.env written to ' . $env_path);

        // Reload env and config so migrations run with the newly written configuration
        \skim\core\env::reset();
        \skim\core\env::load($env_path);
        \skim\core\config::reset();
        \skim\core\config::load(base_path('config'));

        // --- Migrations ---
        if (!$this->flag('no-migrate', false)) {
            cli::line();
            $run_mig = cli::confirm('Run migrations now?', true);
            if ($run_mig) {
                $migrate = new migrate_command();
                $migrate->set_input([], []);
                return $migrate->handle();
            }
        }

        cli::line();
        cli::success('Installation complete. Run: php skim serve');
        return 0;
    }

    private function generate_key(): string {
        $bytes = random_bytes(32);
        return 'base64:' . base64_encode($bytes);
    }

    private function build_env(array $vars): string {
        $lines = [];
        foreach ($vars as $key => $value) {
            $lines[] = "{$key}={$value}";
        }
        return implode("\n", $lines) . "\n";
    }
}
