<?php declare(strict_types=1);

namespace skim\db;

// Tracks and executes migrations.
// State stored in `_migrations` table: filename + batch number.
// Batch groups all migrations run in one `migrate` call for rollback targeting.
//
// Lifecycle:
//   migrate        → run all pending (not in _migrations table)
//   migrate:down   → rollback last batch (all files in max(batch))
//   migrate:fresh  → DROP all tables + re-run everything (dev only, destructive)
//   migrate:status → show applied/pending list
final class migrator {
    private string $table      = '_migrations';
    private string $migrations_dir;
    private string $connection;

    public function __construct(string $migrations_dir, string $connection = 'default') {
        $this->migrations_dir = rtrim($migrations_dir, '/');
        $this->connection     = $connection;
    }

    /**
     * @ai-contract runs all pending migrations in filename-sorted order
     * @ai-contract wraps each migration in a transaction — partial runs leave no residue
     * @ai-contract returns array of filenames that were run
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
     * @ai-contract rolls back all migrations in the last batch
     * @ai-contract $steps overrides batch size — rolls back N individual migrations instead
     * @ai-contract returns array of filenames that were rolled back
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
     * @ai-contract drops _migrations table + calls down() for all applied migrations
     * @ai-contract then re-runs all migrations from scratch
     * @ai-contract DESTRUCTIVE — dev environments only
     */
    public function fresh(): void {
        // Drop all tables by running all downs in reverse order
        $applied = array_reverse($this->applied_filenames());
        foreach ($applied as $filename) {
            $file = $this->migrations_dir . '/' . $filename;
            if (is_file($file)) {
                $migration = require $file;
                $this->execute_sql($migration->down());
            }
        }

        // Drop tracking table
        db::query('DROP TABLE IF EXISTS ' . $this->table, connection: $this->connection);

        // Re-run everything
        $this->run();
    }

    /**
     * @ai-contract returns array of ['filename', 'batch', 'status'] for all known migrations
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
        // Split on semicolons to support multi-statement migrations
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn(string $s) => $s !== '',
        );
        foreach ($statements as $statement) {
            db::query($statement, connection: $this->connection);
        }
    }
}
