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
    private array $before_each = [];
    private array $after_each  = [];

    public function __construct(string $migrations_dir, string $connection = 'default') {
        $this->migrations_dir = rtrim($migrations_dir, '/');
        $this->connection     = $connection;
    }

    /**
     * Register a hook fired before each migration runs. #AI:before
     */
    public function before(callable $fn): static {
        $this->before_each[] = $fn;
        return $this;
    }

    /**
     * Register a hook fired after each migration runs. #AI:after
     */
    public function after(callable $fn): static {
        $this->after_each[] = $fn;
        return $this;
    }

    /**
     * Runs all pending migrations in filename-sorted order. #AI:run
     *
     * @param bool $pretend   Dry-run: collect SQL instead of executing.
     * @param bool $force     Bypass checksum drift guard.
     * @return array Normal mode: list of filenames. Pretend mode: list of ['filename', 'sql'].
     */
    public function run(bool $pretend = false, bool $force = false): array {
        $this->ensure_table();
        $applied = $this->applied_filenames();
        $pending = $this->load_pending($applied);

        if (!$pretend) {
            $this->verify_checksums($force);
            $this->acquire_lock();
        }

        if ($pending === []) {
            if (!$pretend) {
                $this->release_lock();
            }
            return [];
        }

        try {
            $batch = $this->next_batch();
            $ran   = [];
            $executor = (new migration_executor())->pretend($pretend);

            foreach ($pending as $migration) {
                $conn = $migration->connection ?? $this->connection;
                $pdo  = db::pdo($conn);

                foreach ($this->before_each as $hook) {
                    $hook($migration);
                }

                $started = microtime(true);
                $checksum = $this->checksum($migration->filename);

                $run_migration = function() use ($migration, $batch, $conn, $executor, $checksum): void {
                    $executor->run($migration->up(), $conn);
                    db::query(
                        "INSERT INTO {$this->table} (filename, batch, checksum, applied_at, execution_ms) VALUES (:f, :b, :c, :a, :e)",
                        [':f' => $migration->filename, ':b' => $batch, ':c' => $checksum, ':a' => date('Y-m-d H:i:s'), ':e' => 0],
                        connection: $conn,
                    );
                };

                if ($migration->transactional && !$pretend) {
                    db::transaction($run_migration, $conn);
                } else {
                    $run_migration();
                }

                $ms = (int) round((microtime(true) - $started) * 1000);

                if (!$pretend) {
                    db::query(
                        'UPDATE ' . $this->table . ' SET execution_ms = :ms WHERE filename = :f',
                        [':ms' => $ms, ':f' => $migration->filename],
                        connection: $conn,
                    );
                }

                foreach ($this->after_each as $hook) {
                    $hook($migration, $ms);
                }

                $ran[] = $pretend
                    ? ['filename' => $migration->filename, 'sql' => $executor->collected()]
                    : $migration->filename;

                $executor = (new migration_executor())->pretend($pretend);
            }

            return $ran;
        } finally {
            if (!$pretend) {
                $this->release_lock();
            }
        }
    }

    /**
     * Rolls back all migrations in the last batch. #AI:down
     *
     * @param int  $steps        Override: roll back N individual migrations instead of the batch.
     * @param bool $force         Bypass checksum drift guard.
     * @param bool $skip_missing  Skip missing migration files instead of throwing.
     * @return array List of migration filenames that were rolled back.
     */
    public function down(int $steps = 0, bool $force = false, bool $skip_missing = false): array {
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

        if (!$rows) {
            return [];
        }

        $this->verify_checksums($force);
        $this->acquire_lock();

        try {
            $rolled_back = [];
            $executor = new migration_executor();

            foreach ($rows as $row) {
                $file = $this->migrations_dir . '/' . $row['filename'];
                if (!is_file($file)) {
                    if (!$skip_missing) {
                        throw new \RuntimeException("Migration file {$row['filename']} not found, cannot rollback");
                    }
                    continue;
                }
                $migration = require $file;
                $migration->filename = $row['filename'];
                $conn = $migration->connection ?? $this->connection;

                foreach ($this->before_each as $hook) {
                    $hook($migration);
                }

                $started = microtime(true);

                $run_migration = function() use ($migration, $row, $conn, $executor): void {
                    $executor->run($migration->down(), $conn);
                    db::query(
                        'DELETE FROM ' . $this->table . ' WHERE filename = :f',
                        [':f' => $row['filename']],
                        connection: $conn,
                    );
                };

                if ($migration->transactional) {
                    db::transaction($run_migration, $conn);
                } else {
                    $run_migration();
                }

                $ms = (int) round((microtime(true) - $started) * 1000);

                foreach ($this->after_each as $hook) {
                    $hook($migration, $ms);
                }

                $rolled_back[] = $row['filename'];
            }

            return $rolled_back;
        } finally {
            $this->release_lock();
        }
    }

    /**
     * Drops all tables and re-runs all migrations from scratch. #AI:fresh
     *
     * WARNING: DESTRUCTIVE — drops every table by running all down() methods
     * in reverse order, then re-applies everything. Dev environments only.
     */
    public function fresh(): void {
        $applied = array_reverse($this->applied_filenames());
        foreach ($applied as $filename) {
            $file = $this->migrations_dir . '/' . $filename;
            if (!is_file($file)) {
                continue;
            }
            $migration = require $file;
            $migration->filename = $filename;
            $conn = $migration->connection ?? $this->connection;
            (new migration_executor())->run($migration->down(), $conn);
        }

        db::query('DROP TABLE IF EXISTS ' . $this->table, connection: $this->connection);
        $this->run();
    }

    /**
     * Returns the status of all known migrations. #AI:status
     *
     * @return array Array of ['filename', 'batch', 'status', 'applied_at', 'execution_ms', 'checksum_ok'] records.
     */
    public function status(): array {
        $this->ensure_table();
        $applied = array_column(
            db::all('SELECT * FROM ' . $this->table, connection: $this->connection),
            null,
            'filename',
        );

        $all = $this->load_all();
        $files = array_column($all, 'filename');
        $all_filenames = array_unique(array_merge(array_keys($applied), $files));
        sort($all_filenames);

        $out = [];
        foreach ($all_filenames as $filename) {
            $row = $applied[$filename] ?? null;
            $checksum_ok = null;
            if ($row !== null) {
                if (!is_file($this->migrations_dir . '/' . $filename)) {
                    $checksum_ok = false;
                } elseif ($row['checksum'] === '') {
                    $checksum_ok = null;
                } else {
                    $checksum_ok = $this->checksum($filename) === $row['checksum'];
                }
            }

            $out[] = [
                'filename'     => $filename,
                'batch'        => $row['batch'] ?? null,
                'status'       => $row !== null ? 'applied' : 'pending',
                'applied_at'   => $row['applied_at'] ?? null,
                'execution_ms' => $row['execution_ms'] ?? null,
                'checksum_ok'  => $checksum_ok,
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

        $datetime = match ($driver) {
            'sqlite' => "applied_at TEXT NOT NULL DEFAULT (datetime('now'))",
            'pgsql'  => "applied_at TIMESTAMPTZ NOT NULL DEFAULT NOW()",
            default  => "applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)",
        };

        $unique = match ($driver) {
            'pgsql', 'sqlite' => "CREATE UNIQUE INDEX IF NOT EXISTS uq_{$this->table}_filename ON {$this->table} (filename)",
            default           => '',
        };

        $suffix = match ($driver) {
            'mysql'  => ', UNIQUE KEY uq_filename (filename)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            default  => ')',
        };

        db::query(
            "CREATE TABLE IF NOT EXISTS {$this->table} (
                {$id_col},
                filename {$text} NOT NULL,
                batch    INT NOT NULL,
                checksum CHAR(64) NOT NULL DEFAULT '',
                {$datetime},
                execution_ms INT UNSIGNED NOT NULL DEFAULT 0
                {$suffix}",
            connection: $this->connection,
        );
        if ($unique !== '') {
            db::query($unique, connection: $this->connection);
        }

        $this->add_column_if_missing('checksum',     "CHAR(64) NOT NULL DEFAULT ''");
        $this->add_column_if_missing('applied_at',   str_replace('applied_at ', '', $datetime));
        $this->add_column_if_missing('execution_ms', "INT UNSIGNED NOT NULL DEFAULT 0");
    }

    private function add_column_if_missing(string $col, string $def): void {
        try {
            db::query(
                "ALTER TABLE {$this->table} ADD COLUMN {$col} {$def}",
                connection: $this->connection,
            );
        } catch (\PDOException $e) {
            $msg = strtolower($e->getMessage());
            if (str_contains($msg, 'duplicate column') || str_contains($msg, 'already exists') || str_contains($msg, 'exists')) {
                return;
            }
            throw $e;
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

    private function checksum(string $filename): string {
        return hash('sha256', file_get_contents($this->migrations_dir . '/' . $filename));
    }

    private function verify_checksums(bool $force): void {
        if ($force) return;

        $rows = db::all('SELECT filename, checksum FROM ' . $this->table, connection: $this->connection);
        foreach ($rows as $row) {
            $file = $this->migrations_dir . '/' . $row['filename'];
            if ($row['checksum'] === '' || !is_file($file)) {
                continue;
            }
            if ($this->checksum($row['filename']) !== $row['checksum']) {
                throw new \RuntimeException("Migration [{$row['filename']}] changed after it was applied");
            }
        }
    }

    private function acquire_lock(): void {
        $driver = db::pdo($this->connection)->getAttribute(\PDO::ATTR_DRIVER_NAME);
        match ($driver) {
            'mysql' => db::pdo($this->connection)->exec("SELECT GET_LOCK('skim_migrations', -1)"),
            'pgsql' => db::pdo($this->connection)->exec("SELECT pg_advisory_lock(hashtext('skim_migrations'))"),
            default => null, // sqlite: single-writer, skip
        };
    }

    private function release_lock(): void {
        $driver = db::pdo($this->connection)->getAttribute(\PDO::ATTR_DRIVER_NAME);
        match ($driver) {
            'mysql' => db::pdo($this->connection)->exec("SELECT RELEASE_LOCK('skim_migrations')"),
            'pgsql' => db::pdo($this->connection)->exec("SELECT pg_advisory_unlock(hashtext('skim_migrations'))"),
            default => null,
        };
    }
}
