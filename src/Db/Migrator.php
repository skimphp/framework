<?php declare(strict_types=1);

namespace Skim\Db;

/**
 * Tracks and executes SQL-first migrations with batch-based rollback. #AI:class
 *
 * Use via CLI commands (migrate, migrate:down, migrate:fresh, migrate:status).
 * State is stored in the `_migrations` table: filename + batch number.
 * Each `migrate` call groups all pending migrations into one batch for
 * atomic rollback targeting.
 *
 * Example:
 *   $m = new Migrator(basePath('migrations'));
 *   $ran = $m->run();          // ['2024_01_01_create_users.php', ...]
 *   $m->down();                // rollback last batch
 *   $m->status();              // [['filename' => ..., 'batch' => ..., 'status' => 'applied'], ...]
 *
 * Testing: Use test_db() SQLite :memory: — migrator creates its own tracking table.
 *
 * #AI:class
 */
final class Migrator {
    private string $table      = '_migrations';
    private string $migrationsDir;
    private string $connection;

    public function __construct(string $migrationsDir, string $connection = 'default') {
        $this->migrationsDir = rtrim($migrationsDir, '/');
        $this->connection     = $connection;
    }

    /**
     * Runs all pending migrations in filename-sorted order. #AI:run
     *
     * Each migration is wrapped in a transaction — partial runs leave no residue.
     * Returns an array of filenames that were applied.
     *
     * @return array List of migration filenames that were run.
     */
    public function run(): array {
        $this->ensureTable();
        $applied = $this->appliedFilenames();
        $pending = $this->loadPending($applied);

        if ($pending === []) {
            return [];
        }

        $batch = $this->nextBatch();
        $ran   = [];

        foreach ($pending as $migration) {
            \Skim\Db\Db::transaction(function() use ($migration, $batch): void {
                $this->executeSql($migration->up());
                \Skim\Db\Db::query(
                    'INSERT INTO ' . $this->table . ' %values%',
                    ['values' => ['filename' => $migration->filename, 'batch' => $batch]],
                    connection: $this->connection,
                );
            }, $this->connection);

            $ran[] = $migration->filename;
        }

        return $ran;
    }

    /**
     * Rolls back all migrations in the last batch. #AI:down
     *
     * When $steps > 0, rolls back that many individual migrations instead
     * of the entire batch.
     *
     * @param int $steps Override: roll back N individual migrations instead of the batch.
     * @return array List of migration filenames that were rolled back.
     */
    public function down(int $steps = 0): array {
        $this->ensureTable();

        if ($steps > 0) {
            $rows = \Skim\Db\Db::all(
                'SELECT * FROM ' . $this->table . ' ORDER BY id DESC LIMIT ' . $steps,
                connection: $this->connection,
            );
        } else {
            $batch = $this->currentBatch();
            if ($batch === 0) {
                return [];
            }
            $rows = \Skim\Db\Db::all(
                'SELECT * FROM ' . $this->table . ' WHERE batch = :b ORDER BY id DESC',
                [':b' => $batch],
                connection: $this->connection,
            );
        }

        $rolledBack = [];

        foreach ($rows as $row) {
            $file = $this->migrationsDir . '/' . $row['filename'];
            if (!is_file($file)) {
                continue;
            }
            $migration = require $file;
            \Skim\Db\Db::transaction(function() use ($migration, $row): void {
                $this->executeSql($migration->down());
                \Skim\Db\Db::query(
                    'DELETE FROM ' . $this->table . ' WHERE filename = :f',
                    [':f' => $row['filename']],
                    connection: $this->connection,
                );
            }, $this->connection);
            $rolledBack[] = $row['filename'];
        }

        return $rolledBack;
    }

    /**
     * Drops all tables and re-runs all migrations from scratch. #AI:fresh
     *
     * WARNING: DESTRUCTIVE — drops every table by running all down() methods
     * in reverse order, then re-applies everything. Dev environments only.
     *
     * Example:
     *   $migrator->fresh(); // Nuclear option: wipe and rebuild
     */
    public function fresh(): void {
        $applied = array_reverse($this->appliedFilenames());
        foreach ($applied as $filename) {
            $file = $this->migrationsDir . '/' . $filename;
            if (is_file($file)) {
                $migration = require $file;
                $this->executeSql($migration->down());
            }
        }

        \Skim\Db\Db::query('DROP TABLE IF EXISTS ' . $this->table, connection: $this->connection);

        $this->run();
    }

    /**
     * Returns the status of all known migrations. #AI:status
     *
     * @return array Array of ['filename', 'batch', 'status'] records.
     */
    public function status(): array {
        $this->ensureTable();
        $applied = array_column(
            \Skim\Db\Db::all('SELECT * FROM ' . $this->table, connection: $this->connection),
            null,
            'filename',
        );

        $all = $this->loadAll();
        $out = [];

        foreach ($all as $migration) {
            $out[] = [
                'filename' => $migration->filename,
                'batch'    => $applied[$migration->filename]['batch'] ?? null,
                'status'   => isset($applied[$migration->filename]) ? 'applied' : 'pending',
            ];
        }

        return $out;
    }

    // --- internals ---

    private function ensureTable(): void {
        $driver = \Skim\Db\Db::pdo($this->connection)->getAttribute(\PDO::ATTR_DRIVER_NAME);

		$idCol = match ($driver) {
            'pgsql'  => 'id SERIAL PRIMARY KEY',
            'sqlite' => 'id INTEGER PRIMARY KEY AUTOINCREMENT',
            default  => 'id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
        };

        $text = match ($driver) {
            'pgsql', 'sqlite' => 'TEXT',
            default           => 'VARCHAR(500)',
        };

        $unique  = match ($driver) {
            'pgsql', 'sqlite' => "CREATE UNIQUE INDEX IF NOT EXISTS uq_{$this->table}_filename ON {$this->table} (filename)",
            default           => '',
        };

        $suffix  = match ($driver) {
            'mysql'  => ', UNIQUE KEY uq_filename (filename)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            default  => ')',
        };

        \Skim\Db\Db::query(
            "CREATE TABLE IF NOT EXISTS {$this->table} (
                {$idCol},
                filename {$text} NOT NULL,
                batch    INT NOT NULL
                {$suffix}",
            connection: $this->connection,
        );
        if ($unique !== '') {
            \Skim\Db\Db::query($unique, connection: $this->connection);
        }
    }

    private function appliedFilenames(): array {
        return array_column(
            \Skim\Db\Db::all('SELECT filename FROM ' . $this->table, connection: $this->connection),
            'filename',
        );
    }

    private function loadAll(): array {
        $files = glob($this->migrationsDir . '/*.php') ?: [];
        sort($files);
        $migrations = [];
        foreach ($files as $file) {
            $m           = require $file;
            $m->filename = basename($file);
            $migrations[] = $m;
        }
        return $migrations;
    }

    private function loadPending(array $applied): array {
        return array_filter(
            $this->loadAll(),
            fn(\Skim\Db\Migration $m) => !in_array($m->filename, $applied, true),
        );
    }

    private function nextBatch(): int {
        return $this->currentBatch() + 1;
    }

    private function currentBatch(): int {
        return (int) \Skim\Db\Db::val('SELECT MAX(batch) FROM ' . $this->table, connection: $this->connection);
    }

    private function executeSql(string $sql): void {
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn(string $s) => $s !== '',
        );
        foreach ($statements as $statement) {
            \Skim\Db\Db::query($statement, connection: $this->connection);
        }
    }
}

#AI:class
#AI symbol: Skim\Db\Migrator
#AI source_path: src/Db/Migrator.php
#AI title: migrator
#AI description: Tracks and executes SQL-first migrations with batch-based rollback and fresh rebuild.
#AI role: migration runner
#AI layer: db
#AI badges: [migration; batch; rollback; sql-first]
#AI intro: `migrator` reads migration files from a directory, tracks applied migrations in the `_migrations` table, and executes `up()`/`down()` methods. Migrations run in filename-sorted order, grouped into batches for atomic rollback.
#AI lifecycle: instantiated per CLI command invocation, creates _migrations table on first use
#AI fallback: none
#AI test_seam: use test_db() SQLite :memory: — migrator creates its own tracking table
#AI invariants: [migrations run in filename-sorted order; each run() call creates one batch; each migration runs inside a transaction; fresh() is destructive — drops all tables]
#AI core_behaviors: [ensureTable() creates _migrations with driver-specific DDL; executeSql() splits on semicolons for multi-statement support; down() rolls back last batch by default, or N steps if specified]
#AI warnings: [fresh() drops ALL tables and re-runs everything — dev environments only]
#AI notes: The _migrations table is auto-created on first run/down/status call.
#AI scope_items: []
#AI owns: _migrations tracking table
#AI entry_points: [run; down; fresh; status]
#AI config_reads: []
#AI non_goals: [Does not generate migration files; Does not validate SQL syntax; Does not support per-connection migration tracking]
#AI side_effects: [Creates _migrations table; Executes DDL and DML; Modifies database schema]
#AI flow: CLI command -> new Migrator(dir) -> run()/down()/fresh()/status() -> Db::transaction -> Migration::up()/down()
#AI lifecycle_steps: [new Migrator(dir, connection); -> run()/down()/fresh()/status(); -> ensureTable(); -> loadAll() reads files; -> compare with applied; -> execute pending in transactions; -> record in _migrations]
#AI section_order: [Migration Commands; Architecture]
#AI architectural_notes: Batch-based rollback means all migrations run in a single `migrate` call are rolled back together by `migrate:down`. This prevents partial-schema states.

#AI:run
#AI group: Migration Commands
#AI frequency: high
#AI signature: public function run(): array
#AI contract: Runs all pending migrations in filename-sorted order. Each migration is wrapped in a transaction. Returns filenames that were applied.
#AI return_detail: {type: array | desc: List of migration filenames that were run.}
#AI side_effects: Creates _migrations table if missing, executes DDL, inserts tracking records.

#AI:down
#AI group: Migration Commands
#AI frequency: medium
#AI signature: public function down(int $steps = 0): array
#AI contract: Rolls back all migrations in the last batch. When $steps > 0, rolls back that many individual migrations instead.
#AI param_details: [{name: $steps | type: int | required: false | desc: Override batch rollback — roll back N individual migrations. Default 0 (full batch).}]
#AI return_detail: {type: array | desc: List of migration filenames that were rolled back.}
#AI side_effects: Executes down() SQL, deletes tracking records.

#AI:fresh
#AI group: Migration Commands
#AI frequency: low
#AI signature: public function fresh(): void
#AI contract: Drops all tables by running all down() methods in reverse, drops _migrations, then re-runs everything.
#AI warnings: [DESTRUCTIVE — drops every table in the database. Dev environments only.]
#AI side_effects: Drops all tables, recreates schema from scratch.

#AI:status
#AI group: Migration Commands
#AI frequency: medium
#AI signature: public function status(): array
#AI contract: Returns the status of all migration files: filename, batch number, and applied/pending status.
#AI return_detail: {type: array | desc: Array of ['filename', 'batch', 'status'] records.}
