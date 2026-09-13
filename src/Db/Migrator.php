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
 * #AI source_path: src/Db/Migrator.php
 */
final class Migrator {
    private string $table      = '_migrations';
    private string $migrationsDir;
    private string $connection;
    private array $beforeEach = [];
    private array $afterEach  = [];

    public function __construct(string $migrationsDir, string $connection = 'default') {
        $this->migrationsDir = rtrim($migrationsDir, '/');
        $this->connection     = $connection;
    }

    /**
     * Register a hook fired before each migration runs. #AI:before
     */
    public function before(callable $fn): static {
        $this->beforeEach[] = $fn;
        return $this;
    }

    /**
     * Register a hook fired after each migration runs. #AI:after
     */
    public function after(callable $fn): static {
        $this->afterEach[] = $fn;
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
        $this->ensureTable();
        $applied = $this->appliedFilenames();
        $pending = $this->loadPending($applied);

        if (!$pretend) {
            $this->verifyChecksums($force);
            $this->acquireLock();
        }

        if ($pending === []) {
            if (!$pretend) {
                $this->releaseLock();
            }
            return [];
        }

        try {
            $batch = $this->nextBatch();
            $ran   = [];
            $executor = (new MigrationExecutor())->pretend($pretend);

            foreach ($pending as $migration) {
                $conn = $migration->connection ?? $this->connection;
                $pdo  = Db::pdo($conn);

                foreach ($this->beforeEach as $hook) {
                    $hook($migration);
                }

                $started = microtime(true);
                $checksum = $this->checksum($migration->filename);

                $runMigration = function() use ($migration, $batch, $conn, $executor, $checksum): void {
                    $executor->run($migration->up(), $conn);
                    Db::query(
                        "INSERT INTO {$this->table} (filename, batch, checksum, applied_at, execution_ms) VALUES (:f, :b, :c, :a, :e)",
                        [':f' => $migration->filename, ':b' => $batch, ':c' => $checksum, ':a' => date('Y-m-d H:i:s'), ':e' => 0],
                        connection: $conn,
                    );
                };

                if ($migration->transactional && !$pretend) {
                    Db::transaction($runMigration, $conn);
                } else {
                    $runMigration();
                }

                $ms = (int) round((microtime(true) - $started) * 1000);

                if (!$pretend) {
                    Db::query(
                        'UPDATE ' . $this->table . ' SET execution_ms = :ms WHERE filename = :f',
                        [':ms' => $ms, ':f' => $migration->filename],
                        connection: $conn,
                    );
                }

                foreach ($this->afterEach as $hook) {
                    $hook($migration, $ms);
                }

                $ran[] = $pretend
                    ? ['filename' => $migration->filename, 'sql' => $executor->collected()]
                    : $migration->filename;

                $executor = (new MigrationExecutor())->pretend($pretend);
            }

            return $ran;
        } finally {
            if (!$pretend) {
                $this->releaseLock();
            }
        }
    }

    /**
     * Rolls back all migrations in the last batch. #AI:down
     *
     * @param int  $steps        Override: roll back N individual migrations instead of the batch.
     * @param bool $force         Bypass checksum drift guard.
     * @param bool $skipMissing  Skip missing migration files instead of throwing.
     * @return array List of migration filenames that were rolled back.
     */
    public function down(int $steps = 0, bool $force = false, bool $skipMissing = false): array {
        $this->ensureTable();

        if ($steps > 0) {
            $rows = Db::all(
                'SELECT * FROM ' . $this->table . ' ORDER BY id DESC LIMIT ' . $steps,
                connection: $this->connection,
            );
        } else {
            $batch = $this->currentBatch();
            if ($batch === 0) {
                return [];
            }
            $rows = Db::all(
                'SELECT * FROM ' . $this->table . ' WHERE batch = :b ORDER BY id DESC',
                [':b' => $batch],
                connection: $this->connection,
            );
        }

        if (!$rows) {
            return [];
        }

        $this->verifyChecksums($force);
        $this->acquireLock();

        try {
            $rolledBack = [];
            $executor = new MigrationExecutor();

            foreach ($rows as $row) {
                $file = $this->migrationsDir . '/' . $row['filename'];
                if (!is_file($file)) {
                    if (!$skipMissing) {
                        throw new \RuntimeException("Migration file {$row['filename']} not found, cannot rollback");
                    }
                    continue;
                }
                $migration = require $file;
                $migration->filename = $row['filename'];
                $conn = $migration->connection ?? $this->connection;

                foreach ($this->beforeEach as $hook) {
                    $hook($migration);
                }

                $started = microtime(true);

                $runMigration = function() use ($migration, $row, $conn, $executor): void {
                    $executor->run($migration->down(), $conn);
                    Db::query(
                        'DELETE FROM ' . $this->table . ' WHERE filename = :f',
                        [':f' => $row['filename']],
                        connection: $conn,
                    );
                };

                if ($migration->transactional) {
                    Db::transaction($runMigration, $conn);
                } else {
                    $runMigration();
                }

                $ms = (int) round((microtime(true) - $started) * 1000);

                foreach ($this->afterEach as $hook) {
                    $hook($migration, $ms);
                }

                $rolledBack[] = $row['filename'];
            }

            return $rolledBack;
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Drops all tables and re-runs all migrations from scratch. #AI:fresh
     *
     * WARNING: DESTRUCTIVE — drops every table by running all down() methods
     * in reverse order, then re-applies everything. Dev environments only.
     */
    public function fresh(): void {
        $applied = array_reverse($this->appliedFilenames());
        foreach ($applied as $filename) {
            $file = $this->migrationsDir . '/' . $filename;
            if (!is_file($file)) {
                continue;
            }
            $migration = require $file;
            $migration->filename = $filename;
            $conn = $migration->connection ?? $this->connection;
            (new MigrationExecutor())->run($migration->down(), $conn);
        }

        Db::query('DROP TABLE IF EXISTS ' . $this->table, connection: $this->connection);
        $this->run();
    }

    /**
     * Returns the status of all known migrations. #AI:status
     *
     * @return array Array of ['filename', 'batch', 'status', 'applied_at', 'execution_ms', 'checksum_ok'] records.
     */
    public function status(): array {
        $this->ensureTable();
        $applied = array_column(
            Db::all('SELECT * FROM ' . $this->table, connection: $this->connection),
            null,
            'filename',
        );

        $all = $this->loadAll();
        $files = array_column($all, 'filename');
        $allFilenames = array_unique(array_merge(array_keys($applied), $files));
        sort($allFilenames);

        $out = [];
        foreach ($allFilenames as $filename) {
            $row = $applied[$filename] ?? null;
            $checksumOk = null;
            if ($row !== null) {
                if (!is_file($this->migrationsDir . '/' . $filename)) {
                    $checksumOk = false;
                } elseif ($row['checksum'] === '') {
                    $checksumOk = null;
                } else {
                    $checksumOk = $this->checksum($filename) === $row['checksum'];
                }
            }

            $out[] = [
                'filename'     => $filename,
                'batch'        => $row['batch'] ?? null,
                'status'       => $row !== null ? 'applied' : 'pending',
                'applied_at'   => $row['applied_at'] ?? null,
                'execution_ms' => $row['execution_ms'] ?? null,
                'checksum_ok'  => $checksumOk,
            ];
        }

        return $out;
    }

    // --- internals ---

    private function ensureTable(): void {
        $driver = Db::pdo($this->connection)->getAttribute(\PDO::ATTR_DRIVER_NAME);

        $idCol = match ($driver) {
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

        Db::query(
            "CREATE TABLE IF NOT EXISTS {$this->table} (
                {$idCol},
                filename {$text} NOT NULL,
                batch    INT NOT NULL,
                checksum CHAR(64) NOT NULL DEFAULT '',
                {$datetime},
                execution_ms INT UNSIGNED NOT NULL DEFAULT 0
                {$suffix}",
            connection: $this->connection,
        );
        if ($unique !== '') {
            Db::query($unique, connection: $this->connection);
        }

        $this->addColumnIfMissing('checksum',     "CHAR(64) NOT NULL DEFAULT ''");
        $this->addColumnIfMissing('applied_at',   str_replace('applied_at ', '', $datetime));
        $this->addColumnIfMissing('execution_ms', "INT UNSIGNED NOT NULL DEFAULT 0");
    }

    private function addColumnIfMissing(string $col, string $def): void {
        try {
            Db::query(
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

    private function appliedFilenames(): array {
        return array_column(
            Db::all('SELECT filename FROM ' . $this->table, connection: $this->connection),
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
            fn(Migration $m) => !in_array($m->filename, $applied, true),
        );
    }

    private function nextBatch(): int {
        return $this->currentBatch() + 1;
    }

    private function currentBatch(): int {
        return (int) Db::val('SELECT MAX(batch) FROM ' . $this->table, connection: $this->connection);
    }

    private function checksum(string $filename): string {
        return hash('sha256', file_get_contents($this->migrationsDir . '/' . $filename));
    }

    private function verifyChecksums(bool $force): void {
        if ($force) return;

        $rows = Db::all('SELECT filename, checksum FROM ' . $this->table, connection: $this->connection);
        foreach ($rows as $row) {
            $file = $this->migrationsDir . '/' . $row['filename'];
            if ($row['checksum'] === '' || !is_file($file)) {
                continue;
            }
            if ($this->checksum($row['filename']) !== $row['checksum']) {
                throw new \RuntimeException("Migration [{$row['filename']}] changed after it was applied");
            }
        }
    }

    private function acquireLock(): void {
        $driver = Db::pdo($this->connection)->getAttribute(\PDO::ATTR_DRIVER_NAME);
        match ($driver) {
            'mysql' => Db::pdo($this->connection)->exec("SELECT GET_LOCK('skim_migrations', -1)"),
            'pgsql' => Db::pdo($this->connection)->exec("SELECT pg_advisory_lock(hashtext('skim_migrations'))"),
            default => null, // sqlite: single-writer, skip
        };
    }

    private function releaseLock(): void {
        $driver = Db::pdo($this->connection)->getAttribute(\PDO::ATTR_DRIVER_NAME);
        match ($driver) {
            'mysql' => Db::pdo($this->connection)->exec("SELECT RELEASE_LOCK('skim_migrations')"),
            'pgsql' => Db::pdo($this->connection)->exec("SELECT pg_advisory_unlock(hashtext('skim_migrations'))"),
            default => null,
        };
    }
}
