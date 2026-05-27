<?php declare(strict_types=1);

namespace skim\cli\commands;

use skim\cli\cli;
use skim\cli\command;
use skim\db\db;
use skim\ext\ext_migrator;
use skim\ext\ext_registry;

/**
 * Installs a SKIM extension package and runs extension setup steps.
 */
class ext_install_command extends command {
    /**
     * @ai-contract accepts optional test doubles for local registry and migration execution
     */
    public function __construct(
        private readonly ?ext_registry $registry = null,
        private readonly ?ext_migrator $migrator = null,
    ) {}

    /**
     * @ai-contract installs a skim extension package, publishes config, runs migrations, and prints setup hints
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

    private function resolve_package(string $name): string {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        return str_contains($name, '/') ? $name : "skim/{$name}";
    }

    private function preflight(): bool {
        if (PHP_VERSION_ID < 80500) {
            $this->error('SKIM extensions require PHP >= 8.5.');
            return false;
        }

        try {
            db::val('SELECT 1');
            $driver = db::pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
            $this->success("Database: {$driver}");
            return true;
        } catch (\Throwable $e) {
            $this->error('Database is not reachable: ' . $e->getMessage());
            return false;
        }
    }

    /**
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
        $stream_output = cli::is_tty() && !$no_interaction;

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

    private function table_prefix(bool $no_interaction): string {
        $flag = $this->flag('prefix', null);
        if (is_string($flag) && $flag !== '') {
            return $flag;
        }

        if ($no_interaction || !cli::is_tty()) {
            return 'skim_';
        }

        return cli::ask('Table prefix', 'skim_');
    }

    private function write_table_prefix(string $config_path, string $prefix): void {
        $config = require $config_path;
        if (!is_array($config)) {
            return;
        }

        $config['table_prefix'] = $prefix;
        $export = var_export($config, true);
        file_put_contents($config_path, "<?php declare(strict_types=1);\n\nreturn {$export};\n");
    }

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

    private function package_slug(string $package): string {
        return str_replace(['/', '-'], '_', $package);
    }

    private function registry(): ext_registry {
        return $this->registry ?? new ext_registry(base_path());
    }

    private function migrator(): ext_migrator {
        return $this->migrator ?? new ext_migrator();
    }
}
