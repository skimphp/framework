<?php declare(strict_types=1);

namespace Skim\Ext;

use Skim\Db\Db;
use Skim\Db\Migration;

/**
 * Runs extension migrations against the shared _migrations table.
 *
 * Use during extension installation to apply an extension's migration
 * files. Tracks applied migrations as "vendor/package: filename.php"
 * in the shared _migrations table. Supports rollback of the current
 * install session only.
 *
 * Example:
 *   $migrator = new ExtMigrator('default');
 *   $applied = $migrator->run('acme/auth', __DIR__ . '/migrations');
 *   // On failure:
 *   $migrator->rollbackSession($applied);
 *
 * Testing: Use SQLite :memory: connection for isolated migration tests.
 *
 * #AI:class
 */
final class ExtMigrator {
    private string $table = '_migrations';
    private array $appliedThisSession = [];

    /**
     * Sets the DB connection name used for extension migrations. #AI:__construct
     *
     * @param string $connection Configured DB connection name from config/db.php.
     */
    public function __construct(
        private readonly string $connection = 'default',
    ) {}

    /**
     * Runs pending extension migrations in filename order. #AI:run
     *
     * Scans the migrations path for .php files, compares against the
     * _migrations table, and applies pending ones inside transactions.
     * Each migration is tracked as "ext_name: filename.php".
     *
     * Example:
     *   $applied = $migrator->run('acme/auth', '/path/to/ext/migrations');
     *
     * @param string $extName       Extension name (no colons allowed).
     * @param string $migrationsPath Absolute path to migration .php files.
     * @return array<int,array{ext_name:string,filename:string,tracking_filename:string,file:string}>
     */
    public function run(string $extName, string $migrationsPath): array {
        $extName = $this->normalizeExtName($extName);
        $this->appliedThisSession = [];

        if (!is_dir($migrationsPath)) {
            return [];
        }

        $this->ensureTable();
        $applied = $this->appliedFor($extName);
        $pending = $this->pending($extName, $migrationsPath, $applied);

        if ($pending === []) {
            return [];
        }

        $batch = $this->nextBatch();

        foreach ($pending as $entry) {
            /** @var \Skim\Db\Migration $migration */
            $migration = require $entry['file'];
            $migration->filename = $entry['tracking_filename'];

            \Skim\Db\Db::transaction(function() use ($migration, $entry, $batch): void {
                $this->executeSql($migration->up());
                \Skim\Db\Db::query(
                    'INSERT INTO ' . $this->table . ' %values%',
                    ['values' => ['filename' => $entry['tracking_filename'], 'batch' => $batch]],
                    connection: $this->connection,
                );
            }, $this->connection);

            $this->appliedThisSession[] = $entry;
        }

        return $this->appliedThisSession;
    }

    /**
     * Rolls back migrations applied during the current install session. #AI:rollbackSession
     *
     * WARNING: Only rolls back migrations applied by the most recent run() call.
     * Used for cleanup when a later step in the install process fails.
     *
     * @param array $appliedThisSession Array of applied entries from run().
     * @return array<int,string> Tracking filenames that were rolled back.
     */
    public function rollbackSession(array $appliedThisSession): array {
        $rolledBack = [];

        foreach (array_reverse($appliedThisSession) as $entry) {
            if (!isset($entry['file'], $entry['tracking_filename']) || !is_file($entry['file'])) {
                continue;
            }

            /** @var \Skim\Db\Migration $migration */
            $migration = require $entry['file'];
            $migration->filename = $entry['tracking_filename'];

            \Skim\Db\Db::transaction(function() use ($migration, $entry): void {
                $this->executeSql($migration->down());
                \Skim\Db\Db::query(
                    'DELETE FROM ' . $this->table . ' WHERE filename = :filename',
                    [':filename' => $entry['tracking_filename']],
                    connection: $this->connection,
                );
            }, $this->connection);

            $rolledBack[] = $entry['tracking_filename'];
        }

        return $rolledBack;
    }

    /**
     * Returns migrations applied during the most recent run() call. #AI:appliedThisSession
     */
    public function appliedThisSession(): array {
        return $this->appliedThisSession;
    }

    private function normalizeExtName(string $extName): string {
        $extName = trim($extName);
        if ($extName === '' || str_contains($extName, ':')) {
            throw new \InvalidArgumentException('Extension name must be non-empty and cannot contain ":".');
        }

        return $extName;
    }

    private function pending(string $extName, string $migrationsPath, array $applied): array {
        $files = glob(rtrim($migrationsPath, '/') . '/*.php') ?: [];
        sort($files);

        $pending = [];
        foreach ($files as $file) {
            $filename = basename($file);
            $trackingFilename = "{$extName}: {$filename}";
            if (in_array($trackingFilename, $applied, true)) {
                continue;
            }

            $pending[] = [
                'ext_name'          => $extName,
                'filename'          => $filename,
                'tracking_filename' => $trackingFilename,
                'file'              => $file,
            ];
        }

        return $pending;
    }

    private function appliedFor(string $extName): array {
        $prefix = "{$extName}: ";
        $rows = \Skim\Db\Db::all('SELECT filename FROM ' . $this->table, connection: $this->connection);

        return array_values(array_filter(
            array_column($rows, 'filename'),
            static fn(string $filename): bool => str_starts_with($filename, $prefix),
        ));
    }

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

        $unique = match ($driver) {
            'pgsql', 'sqlite' => "CREATE UNIQUE INDEX IF NOT EXISTS uq_{$this->table}_filename ON {$this->table} (filename)",
            default           => '',
        };

        $suffix = match ($driver) {
            'mysql' => ', UNIQUE KEY uq_filename (filename)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            default => ')',
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

    private function nextBatch(): int {
        return ((int) \Skim\Db\Db::val('SELECT MAX(batch) FROM ' . $this->table, connection: $this->connection)) + 1;
    }

    private function executeSql(string $sql): void {
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            static fn(string $statement): bool => $statement !== '',
        );

        foreach ($statements as $statement) {
            \Skim\Db\Db::query($statement, connection: $this->connection);
        }
    }
}

#AI:class
#AI symbol: Skim\Ext\ExtMigrator
#AI source_path: src/Ext/ExtMigrator.php
#AI title: ExtMigrator
#AI description: Runs extension migrations against the shared _migrations table with session-scoped rollback.
#AI role: extension migration runner
#AI layer: ext
#AI badges: [extension; migration; database; transactional]
#AI intro: `ExtMigrator` applies an extension's migration files inside transactions and tracks them in the shared `_migrations` table. It supports session-scoped rollback for cleanup when installation fails partway through.
#AI lifecycle: instantiated per-extension install; tracks applied migrations per session
#AI test_seam: use SQLite :memory: connection; inspect appliedThisSession()
#AI invariants: [Each migration runs inside a transaction; Tracking filename format is 'ext_name: filename.php'; Extension name cannot contain colons; _migrations table is auto-created]
#AI core_behaviors: [Scans migration directory for .php files; Compares against _migrations table; Applies pending in sorted order; Tracks in batch numbers]
#AI owns: _migrations table rows for extension entries
#AI entry_points: [run; rollbackSession; appliedThisSession]
#AI config_reads: []
#AI non_goals: [Does not handle core framework migrations; Does not support down-migrations by batch; Does not validate SQL syntax across drivers]
#AI side_effects: [Creates _migrations table if missing; Inserts tracking rows; Executes DDL/DML from migration files; Deletes rows on rollback]
#AI flow: run() -> ensureTable() -> appliedFor() -> pending() -> foreach: transaction(up() + INSERT tracking) -> rollbackSession(): reverse foreach: transaction(down() + DELETE tracking)
#AI section_order: [Migration API; Inspection]
#AI warnings: [rollbackSession() only rolls back migrations from the current run() call — not previous sessions]

#AI:__construct
#AI group: Migration API
#AI frequency: low
#AI signature: public function __construct(string $connection = 'default')
#AI contract: Sets the configured DB connection name used for all migration operations.
#AI param_details: [{name: $connection | type: string | required: false | desc: Configured DB connection name from config/db.php. Default 'default'.}]

#AI:run
#AI group: Migration API
#AI frequency: high
#AI signature: public function run(string $extName, string $migrationsPath): array
#AI contract: Scans the migrations path, compares against applied migrations, and applies pending ones inside transactions. Returns the list of applied entries.
#AI param_details: [{name: $extName | type: string | required: true | desc: Extension name. Must be non-empty and cannot contain colons.}; {name: $migrationsPath | type: string | required: true | desc: Absolute path to directory containing migration .php files.}]
#AI return_detail: {type: array | desc: List of applied migration entries with ext_name, filename, tracking_filename, and file keys.}
#AI side_effects: [Creates _migrations table if missing; Executes migration SQL; Inserts tracking rows]

#AI:rollbackSession
#AI group: Migration API
#AI frequency: low
#AI signature: public function rollbackSession(array $appliedThisSession): array
#AI contract: Rolls back migrations applied during the current install session in reverse order. Each rollback runs inside a transaction.
#AI param_details: [{name: $appliedThisSession | type: array | required: true | desc: Array of applied entries returned by run().}]
#AI return_detail: {type: array<int,string> | desc: Tracking filenames that were rolled back.}
#AI warnings: [Only rolls back migrations from the most recent run() call — not previous sessions]
#AI side_effects: [Executes migration down() SQL; Deletes tracking rows from _migrations]

#AI:appliedThisSession
#AI group: Inspection
#AI frequency: low
#AI signature: public function appliedThisSession(): array
#AI contract: Returns the list of migrations applied during the most recent run() call.
#AI return_detail: {type: array | desc: Applied migration entries from the last run().}
