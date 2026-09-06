<?php declare(strict_types=1);

namespace Skim\Cli\Commands;

use Skim\Cli\Command;
use Skim\Db\Migrator;

/**
 * CLI dispatcher for database migration operations — delegates all SQL to migrator.
 *
 * Use when running, rolling back, or inspecting database migrations from the terminal.
 * Supports run (pending), down (rollback), fresh (drop+re-run), status, and make sub-commands.
 *
 * Example:
 *   php skim migrate           # run pending
 *   php skim migrate:down      # rollback last batch
 *   php skim migrate:fresh     # drop all + re-run (dev only)
 *   php skim migrate:status    # show pending/applied list
 *   php skim migrate:make create_users_table
 *
 * Testing: Instantiate directly, inject a mock migrator path, call handle().
 *
 * #AI:class
 */
class MigrateCommand extends Command {
    /**
     * Dispatches to the appropriate migration sub-command. #AI:handle
     *
     * Sub-commands: run (default), down, fresh, status, make. All SQL logic
     * is delegated to the migrator class — this command handles only
     * CLI output and exit codes.
     */
    public function handle(): int {
        $sub  = $this->arg(0, 'run');
        $conn = $this->flag('connection', 'default');

        if ($sub === 'make') {
            return $this->make($conn);
        }

        $dir = $conn === 'default'
            ? basePath('migrations')
            : basePath("migrations/{$conn}");

        if (!is_dir($dir)) {
            $this->warn("Migration directory not found: {$dir}");
            return 0;
        }

        $mig = new Migrator($dir, $conn);

        return match ($sub) {
            'down'   => $this->down($mig),
            'fresh'  => $this->fresh($mig),
            'status' => $this->status($mig),
            default  => $this->run($mig),
        };
    }

    /**
     * Runs all pending migrations. #AI:run
     */
    private function run(Migrator $mig): int {
        $force = $this->flag('force');
        $ran = $mig->run(force: (bool) $force);
        if ($ran === []) {
            $this->info('Nothing to migrate.');
            return 0;
        }
        foreach ($ran as $file) {
            $this->success("Migrated: {$file}");
        }
        return 0;
    }

    /**
     * Rolls back the last batch of migrations. #AI:down
     */
    private function down(Migrator $mig): int {
        $steps        = (int) $this->flag('steps', 0);
        $force        = $this->flag('force');
        $skipMissing = $this->flag('skip-missing');
        $rolled = $mig->down($steps, force: (bool) $force, skipMissing: (bool) $skipMissing);
        if ($rolled === []) {
            $this->info('Nothing to roll back.');
            return 0;
        }
        foreach ($rolled as $file) {
            $this->warn("Rolled back: {$file}");
        }
        return 0;
    }

    /**
     * Drops all tables and re-runs all migrations from scratch. #AI:fresh
     */
    private function fresh(Migrator $mig): int {
        if (Env('APP_ENV') === 'production' && !$this->flag('force')) {
            $this->error('Production environment detected. Use --force to proceed.');
            return 1;
        }
        $this->warn('Running migrate:fresh — all data will be DESTROYED.');
        $mig->fresh();
        $this->success('Fresh migration complete.');
        return 0;
    }

    /**
     * Displays a table of migration status (applied vs pending). #AI:status
     */
    private function status(Migrator $mig): int {
        $rows = $mig->status();
        $this->line();
        \Skim\Cli\Cli::table(
            ['filename', 'batch', 'status', 'applied_at', 'execution_ms', 'checksum_ok'],
            array_map(fn($r) => [
                'filename'     => $r['filename'],
                'batch'        => $r['batch'] ?? '-',
                'status'       => $r['status'],
                'applied_at'   => $r['applied_at'] ?? '-',
                'execution_ms' => $r['execution_ms'] ?? '-',
                'checksum_ok'  => $r['checksum_ok'] === null ? '-' : ($r['checksum_ok'] ? 'yes' : 'NO'),
            ], $rows),
        );
        return 0;
    }

    /**
     * Creates a new Migration file from stub. #AI:make
     */
    private function make(string $conn): int {
        $name = $this->arg(1, '');
        if ($name === '') {
            $this->error('Migration name required. Example: php skim migrate:make create_users_table');
            return 1;
        }

        $dir = $conn === 'default'
            ? basePath('migrations')
            : basePath("migrations/{$conn}");

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = date('Y_m_d_His') . '_' . $name . '.php';
        $stub = file_get_contents(__DIR__ . '/../Scaffolds/Migration.php');
        $stub = str_replace('{{name}}', $name, $stub);
        file_put_contents($dir . '/' . $filename, $stub);

        $this->success("Created: {$filename}");
        return 0;
    }
}

#AI:class
#AI symbol: Skim\Cli\Commands\MigrateCommand
#AI source_path: src/Cli/Commands/MigrateCommand.php
#AI title: migrate_command
#AI description: CLI dispatcher for database migration operations — run, rollback, fresh, status, and make.
#AI role: CLI migration dispatcher
#AI layer: cli
#AI badges: [cli; command; database; migration; destructive]
#AI intro: `MigrateCommand` implements the `php skim migrate` family of CLI commands. It dispatches to the `migrator` class for all SQL operations and handles only CLI output and exit codes.
#AI lifecycle: instantiated by kernel, handle() called once per invocation
#AI fallback: prints informational message when nothing to migrate or roll back
#AI test_seam: instantiate directly, call setInput() with test args, then handle()
#AI invariants: [all SQL logic is in migrator — command handles only output; fresh destroys all data; down rolls back by batch]
#AI core_behaviors: [Dispatches sub-commands via match expression; Delegates to migrator for all DB operations; Prints success/warn/info for each migration file]
#AI warnings: [migrate:fresh drops ALL tables and destroys all data — use only in development]
#AI owns: nothing — delegates all DB operations to migrator
#AI entry_points: [handle]
#AI config_reads: [APP_ENV]
#AI non_goals: [Does not validate migration syntax]
#AI side_effects: [Migrator::run() applies pending migrations; Migrator::down() rolls back batches; Migrator::fresh() drops all tables]
#AI flow: MigrateCommand::handle() -> arg(0) sub-command -> new Migrator(migrations/) -> match sub-command -> migrator method -> print results
#AI lifecycle_steps: [handle(); -> arg(0) sub-command; -> new Migrator(basePath('migrations')); -> match: run/down/fresh/status/make; -> migrator method; -> print results]
#AI section_order: [Command Execution; Sub-commands]
#AI architectural_notes: Thin CLI wrapper — all migration logic lives in Skim\Db\Migrator. This command handles only argument dispatch and terminal output.

#AI:handle
#AI group: Command Execution
#AI frequency: low
#AI signature: public function handle(): int
#AI contract: Dispatches to the appropriate migration sub-command (run, down, fresh, status, make). Defaults to run when no sub-command is given.
#AI return_detail: {type: int | desc: 0 on success.}

#AI:run
#AI group: Sub-commands
#AI frequency: low
#AI signature: private function run(Migrator $mig): int
#AI contract: Runs all pending migrations via the migrator and prints each applied file.

#AI:down
#AI group: Sub-commands
#AI frequency: low
#AI signature: private function down(Migrator $mig): int
#AI contract: Rolls back the last batch of migrations. Use --steps=N flag to roll back multiple batches.

#AI:fresh
#AI group: Sub-commands
#AI frequency: low
#AI signature: private function fresh(Migrator $mig): int
#AI contract: Drops all tables and re-runs all migrations from scratch.

#AI:status
#AI group: Sub-commands
#AI frequency: low
#AI signature: private function status(Migrator $mig): int
#AI contract: Displays a table showing each migration's filename, batch number, and applied/pending status.

#AI:make
#AI group: Sub-commands
#AI frequency: low
#AI signature: private function make(string $conn): int
#AI contract: Creates a new Migration file from the stub template.
