<?php declare(strict_types=1);

namespace skim\cli\commands;

use skim\cli\command;
use skim\db\migrator;

/**
 * CLI dispatcher for database migration operations — delegates all SQL to migrator.
 *
 * Use when running, rolling back, or inspecting database migrations from the terminal.
 * Supports run (pending), down (rollback), fresh (drop+re-run), and status sub-commands.
 *
 * Example:
 *   php skim migrate           # run pending
 *   php skim migrate:down      # rollback last batch
 *   php skim migrate:fresh     # drop all + re-run (dev only)
 *   php skim migrate:status    # show pending/applied list
 *
 * Testing: Instantiate directly, inject a mock migrator path, call handle().
 *
 * #AI:class
 */
class migrate_command extends command {
    /**
     * Dispatches to the appropriate migration sub-command. #AI:handle
     *
     * Sub-commands: run (default), down, fresh, status. All SQL logic
     * is delegated to the migrator class — this command handles only
     * CLI output and exit codes.
     */
    public function handle(): int {
        $sub  = $this->arg(0, 'run');
        $mig  = new migrator(base_path('migrations'));

        return match ($sub) {
            'down'   => $this->down($mig),
            'fresh'  => $this->fresh($mig),
            'status' => $this->status($mig),
            default  => $this->run($mig),
        };
    }

    /**
     * Runs all pending migrations. #AI:run
     *
     * @param migrator $mig Migrator instance pointed at the migrations directory.
     */
    private function run(migrator $mig): int {
        $ran = $mig->run();
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
     *
     * Use --steps=N to roll back more than one batch.
     *
     * @param migrator $mig Migrator instance.
     */
    private function down(migrator $mig): int {
        $steps = (int) $this->flag('steps', 0);
        $rolled = $mig->down($steps);
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
     *
     * WARNING: Destroys ALL data in the database. Use only in development.
     *
     * @param migrator $mig Migrator instance.
     */
    private function fresh(migrator $mig): int {
        $this->warn('Running migrate:fresh — all data will be DESTROYED.');
        $mig->fresh();
        $this->success('Fresh migration complete.');
        return 0;
    }

    /**
     * Displays a table of migration status (applied vs pending). #AI:status
     *
     * @param migrator $mig Migrator instance.
     */
    private function status(migrator $mig): int {
        $rows = $mig->status();
        $this->line();
        \skim\cli\cli::table(
            ['filename', 'batch', 'status'],
            array_map(fn($r) => [
                'filename' => $r['filename'],
                'batch'    => $r['batch'] ?? '-',
                'status'   => $r['status'],
            ], $rows),
        );
        return 0;
    }
}

#AI:class
#AI symbol: skim\cli\commands\migrate_command
#AI source_path: src/cli/commands/migrate_command.php
#AI title: migrate_command
#AI description: CLI dispatcher for database migration operations — run, rollback, fresh, and status.
#AI role: CLI migration dispatcher
#AI layer: cli
#AI badges: [cli; command; database; migration; destructive]
#AI intro: `migrate_command` implements the `php skim migrate` family of CLI commands. It dispatches to the `migrator` class for all SQL operations and handles only CLI output and exit codes. Supports run (pending), down (rollback), fresh (drop+re-run), and status sub-commands.
#AI lifecycle: instantiated by kernel, handle() called once per invocation
#AI fallback: prints informational message when nothing to migrate or roll back
#AI test_seam: instantiate directly, call set_input() with test args, then handle()
#AI invariants: [all SQL logic is in migrator — command handles only output; fresh destroys all data; down rolls back by batch]
#AI core_behaviors: [Dispatches sub-commands via match expression; Delegates to migrator for all DB operations; Prints success/warn/info for each migration file]
#AI warnings: [migrate:fresh drops ALL tables and destroys all data — use only in development]
#AI owns: nothing — delegates all DB operations to migrator
#AI entry_points: [handle]
#AI config_reads: []
#AI non_goals: [Does not contain SQL logic; Does not create migration files; Does not validate migration syntax]
#AI side_effects: [migrator::run() applies pending migrations; migrator::down() rolls back batches; migrator::fresh() drops all tables]
#AI flow: migrate_command::handle() -> arg(0) sub-command -> new migrator(migrations/) -> match sub-command -> migrator method -> print results
#AI lifecycle_steps: [handle(); -> arg(0) sub-command; -> new migrator(base_path('migrations')); -> match: run/down/fresh/status; -> migrator method; -> print results]
#AI section_order: [Command Execution; Sub-commands]
#AI architectural_notes: Thin CLI wrapper — all migration logic lives in skim\db\migrator. This command handles only argument dispatch and terminal output.

#AI:handle
#AI group: Command Execution
#AI frequency: low
#AI signature: public function handle(): int
#AI contract: Dispatches to the appropriate migration sub-command (run, down, fresh, status). Defaults to run when no sub-command is given.
#AI return_detail: {type: int | desc: 0 on success.}

#AI:run
#AI group: Sub-commands
#AI frequency: low
#AI signature: private function run(migrator $mig): int
#AI contract: Runs all pending migrations via the migrator and prints each applied file.
#AI param_details: [{name: $mig | type: migrator | required: true | desc: Migrator instance pointed at the migrations directory.}]

#AI:down
#AI group: Sub-commands
#AI frequency: low
#AI signature: private function down(migrator $mig): int
#AI contract: Rolls back the last batch of migrations. Use --steps=N flag to roll back multiple batches.
#AI param_details: [{name: $mig | type: migrator | required: true | desc: Migrator instance.}]

#AI:fresh
#AI group: Sub-commands
#AI frequency: low
#AI signature: private function fresh(migrator $mig): int
#AI contract: Drops all tables and re-runs all migrations from scratch.
#AI param_details: [{name: $mig | type: migrator | required: true | desc: Migrator instance.}]
#AI warnings: [Destroys ALL data in the database — use only in development environments]

#AI:status
#AI group: Sub-commands
#AI frequency: low
#AI signature: private function status(migrator $mig): int
#AI contract: Displays a table showing each migration's filename, batch number, and applied/pending status.
#AI param_details: [{name: $mig | type: migrator | required: true | desc: Migrator instance.}]
