<?php declare(strict_types=1);

namespace skim\ext;

use skim\db\db;
use skim\db\migration;

/**
 * Runs extension migrations against the shared _migrations table.
 */
final class ext_migrator {
    private string $table = '_migrations';
    private array $applied_this_session = [];

    /**
     * @ai-contract connection names the configured DB connection used for extension migrations
     */
    public function __construct(
        private readonly string $connection = 'default',
    ) {}

    /**
     * @ai-contract runs pending extension migrations in filename order
     * @ai-contract stores filenames as "vendor/package: migration.php" in the shared _migrations table
     *
     * @return array<int,array{ext_name:string,filename:string,tracking_filename:string,file:string}>
     */
    public function run(string $ext_name, string $migrations_path): array {
        $ext_name = $this->normalize_ext_name($ext_name);
        $this->applied_this_session = [];

        if (!is_dir($migrations_path)) {
            return [];
        }

        $this->ensure_table();
        $applied = $this->applied_for($ext_name);
        $pending = $this->pending($ext_name, $migrations_path, $applied);

        if ($pending === []) {
            return [];
        }

        $batch = $this->next_batch();

        foreach ($pending as $entry) {
            /** @var migration $migration */
            $migration = require $entry['file'];
            $migration->filename = $entry['tracking_filename'];

            db::transaction(function() use ($migration, $entry, $batch): void {
                $this->execute_sql($migration->up());
                db::query(
                    'INSERT INTO ' . $this->table . ' %values%',
                    ['values' => ['filename' => $entry['tracking_filename'], 'batch' => $batch]],
                    connection: $this->connection,
                );
            }, $this->connection);

            $this->applied_this_session[] = $entry;
        }

        return $this->applied_this_session;
    }

    /**
     * @ai-contract rolls back only migrations applied during the current install session
     *
     * @return array<int,string>
     */
    public function rollback_session(array $applied_this_session): array {
        $rolled_back = [];

        foreach (array_reverse($applied_this_session) as $entry) {
            if (!isset($entry['file'], $entry['tracking_filename']) || !is_file($entry['file'])) {
                continue;
            }

            /** @var migration $migration */
            $migration = require $entry['file'];
            $migration->filename = $entry['tracking_filename'];

            db::transaction(function() use ($migration, $entry): void {
                $this->execute_sql($migration->down());
                db::query(
                    'DELETE FROM ' . $this->table . ' WHERE filename = :filename',
                    [':filename' => $entry['tracking_filename']],
                    connection: $this->connection,
                );
            }, $this->connection);

            $rolled_back[] = $entry['tracking_filename'];
        }

        return $rolled_back;
    }

    /**
     * @ai-contract returns migrations applied during the most recent run() call
     */
    public function applied_this_session(): array {
        return $this->applied_this_session;
    }

    private function normalize_ext_name(string $ext_name): string {
        $ext_name = trim($ext_name);
        if ($ext_name === '' || str_contains($ext_name, ':')) {
            throw new \InvalidArgumentException('Extension name must be non-empty and cannot contain ":".');
        }

        return $ext_name;
    }

    private function pending(string $ext_name, string $migrations_path, array $applied): array {
        $files = glob(rtrim($migrations_path, '/') . '/*.php') ?: [];
        sort($files);

        $pending = [];
        foreach ($files as $file) {
            $filename = basename($file);
            $tracking_filename = "{$ext_name}: {$filename}";
            if (in_array($tracking_filename, $applied, true)) {
                continue;
            }

            $pending[] = [
                'ext_name'          => $ext_name,
                'filename'          => $filename,
                'tracking_filename' => $tracking_filename,
                'file'              => $file,
            ];
        }

        return $pending;
    }

    private function applied_for(string $ext_name): array {
        $prefix = "{$ext_name}: ";
        $rows = db::all('SELECT filename FROM ' . $this->table, connection: $this->connection);

        return array_values(array_filter(
            array_column($rows, 'filename'),
            static fn(string $filename): bool => str_starts_with($filename, $prefix),
        ));
    }

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

        $unique = match ($driver) {
            'pgsql', 'sqlite' => "CREATE UNIQUE INDEX IF NOT EXISTS uq_{$this->table}_filename ON {$this->table} (filename)",
            default           => '',
        };

        $suffix = match ($driver) {
            'mysql' => ', UNIQUE KEY uq_filename (filename)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            default => ')',
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

    private function next_batch(): int {
        return ((int) db::val('SELECT MAX(batch) FROM ' . $this->table, connection: $this->connection)) + 1;
    }

    private function execute_sql(string $sql): void {
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            static fn(string $statement): bool => $statement !== '',
        );

        foreach ($statements as $statement) {
            db::query($statement, connection: $this->connection);
        }
    }
}
