<?php declare(strict_types=1);

namespace skim\db;

/**
 * Tracks and executes SQL-first migrations with batch-based rollback. #AI:class
 *
 * Use via CLI commands (migrate, migrate:down, migrate:fresh, migrate:status).
 * State is stored in the `_migrations` table: filename + batch number.
 * Each `migrate` call groups all pending migrations into one batch for
 * atomic rollback targeting.
 *
 * Example:
 *   $m = new migrator(base_path('migrations'));
 *   $ran = $m->run();          // ['2024_01_01_create_users.php', ...]
 *   $m->down();                // rollback last batch
 *   $m->status();              // [['filename' => ..., 'batch' => ..., 'status' => 'applied'], ...]
 *
 * Testing: Use test_db() SQLite :memory: — migrator creates its own tracking table.
 *
 * #AI:class
 */
final class migrator {
    private string $table      = '_migrations';
    private string $migrations_dir;
    private string $connection;

    public function __construct(string $migrations_dir, string $connection = 'default') {
        $this->migrations_dir = rtrim($migrations_dir, '/');
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
        $this->ensure_table();
        $applied = $this->applied_filenames();
        $pending = $this->load_pending($applied);

        if ($pending === []) {
            return [];
        }

        $batch = $this->next_batch();
        $ran   = [];

        foreach ($pending as $migration) {
            db::transaction(function() use ($migration, $batch): void {
                $this->execute_sql($migration->up());
                db::query(
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
        $this->ensure_table();

        if ($steps > 0) {
            $rows = db::all(
                'SELECT * FROM ' . $this->table . ' ORDER BY id DESC LIMIT ' . $steps,
                connection: $this->connection,
            );
        } else {
            $batch = $this->current_batch();
            if ($batch === 0) {
                return [];
            }
            $rows = db::all(
                'SELECT * FROM ' . $this->table . ' WHERE batch = :b ORDER BY id DESC',
                [':b' => $batch],
                connection: $this->connection,
            );
        }

        $rolled_back = [];

        foreach ($rows as $row) {
            $file = $this->migrations_dir . '/' . $row['filename'];
            if (!is_file($file)) {
                continue;
            }
            $migration = require $file;
            db::transaction(function() use ($migration, $row): void {
                $this->execute_sql($migration->down());
                db::query(
                    'DELETE FROM ' . $this->table . ' WHERE filename = :f',
                    [':f' => $row['filename']],
                    connection: $this->connection,
                );
            }, $this->connection);
            $rolled_back[] = $row['filename'];
        }

        return $rolled_back;
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
        $applied = array_reverse($this->applied_filenames());
        foreach ($applied as $filename) {
            $file = $this->migrations_dir . '/' . $filename;
            if (is_file($file)) {
                $migration = require $file;
                $this->execute_sql($migration->down());
            }
        }

        db::query('DROP TABLE IF EXISTS ' . $this->table, connection: $this->connection);

        $this->run();
    }

    /**
     * Returns the status of all known migrations. #AI:status
     *
     * @return array Array of ['filename', 'batch', 'status'] records.
     */
    public function status(): array {
        $this->ensure_table();
        $applied = array_column(
            db::all('SELECT * FROM ' . $this->table, connection: $this->connection),
            null,
            'filename',
        );

        $all = $this->load_all();
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

    private function ensure_table(): void {
        $driver = db::pdo($this->connection)->getAttribute(\PDO::ATTR_DRIVER_NAME);

		$id_col = match ($driver) {
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

        db::query(
            "CREATE TABLE IF NOT EXISTS {$this->table} (
                {$id_col},
                filename {$text} NOT NULL,
                batch    INT NOT NULL
                {$suffix}",
            connection: $this->connection,
        );
        if ($unique !== '') {
            db::query($unique, connection: $this->connection);
        }
    }

    private function applied_filenames(): array {
        return array_column(
            db::all('SELECT filename FROM ' . $this->table, connection: $this->connection),
            'filename',
        );
    }

    private function load_all(): array {
        $files = glob($this->migrations_dir . '/*.php') ?: [];
        sort($files);
        $migrations = [];
        foreach ($files as $file) {
            $m           = require $file;
            $m->filename = basename($file);
            $migrations[] = $m;
        }
        return $migrations;
    }

    private function load_pending(array $applied): array {
        return array_filter(
            $this->load_all(),
            fn(migration $m) => !in_array($m->filename, $applied, true),
        );
    }

    private function next_batch(): int {
        return $this->current_batch() + 1;
    }

    private function current_batch(): int {
        return (int) db::val('SELECT MAX(batch) FROM ' . $this->table, connection: $this->connection);
    }

    private function execute_sql(string $sql): void {
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn(string $s) => $s !== '',
        );
        foreach ($statements as $statement) {
            db::query($statement, connection: $this->connection);
        }
    }
}

#AI:class
#AI symbol: skim\db\migrator
#AI source_path: src/db/migrator.php
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
#AI core_behaviors: [ensure_table() creates _migrations with driver-specific DDL; execute_sql() splits on semicolons for multi-statement support; down() rolls back last batch by default, or N steps if specified]
#AI warnings: [fresh() drops ALL tables and re-runs everything — dev environments only]
#AI notes: The _migrations table is auto-created on first run/down/status call.
#AI scope_items: []
#AI owns: _migrations tracking table
#AI entry_points: [run; down; fresh; status]
#AI config_reads: []
#AI non_goals: [Does not generate migration files; Does not validate SQL syntax; Does not support per-connection migration tracking]
#AI side_effects: [Creates _migrations table; Executes DDL and DML; Modifies database schema]
#AI flow: CLI command -> new migrator(dir) -> run()/down()/fresh()/status() -> db::transaction -> migration::up()/down()
#AI lifecycle_steps: [new migrator(dir, connection); -> run()/down()/fresh()/status(); -> ensure_table(); -> load_all() reads files; -> compare with applied; -> execute pending in transactions; -> record in _migrations]
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
