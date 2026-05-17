<?php declare(strict_types=1);

namespace skim\db;

use skim\dev\profiler;

// Public facade for all database operations.
// query_gen SQL building logic lives in query_builder.php (internal, not public API).
// Connection pool is lazy — PDO created on first query, not on config load.
class db {
    /** @var array<string, \PDO> */
    private static array $pool = [];

    // --- connection management ---

    // Connections are lazy — registered here, PDO created on first query.
    // Reason: don't open DB connection if the request only hits cache.
    public static function connect(string $name, array $config): void {
        self::$pool[$name] = self::make_pdo($config);
    }

    private static function make_pdo(array $cfg): \PDO {
        $dsn = match ($cfg['driver']) {
            'mysql' => sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $cfg['host'],
                $cfg['port'] ?? 3306,
                $cfg['database'],
                $cfg['charset'] ?? 'utf8mb4',
            ),
            'pgsql' => sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                $cfg['host'],
                $cfg['port'] ?? 5432,
                $cfg['database'],
            ),
            'sqlite' => 'sqlite:' . $cfg['database'],
            default => throw new \InvalidArgumentException("Unknown DB driver: {$cfg['driver']}"),
        };

        return new \PDO($dsn, $cfg['user'] ?? null, $cfg['password'] ?? null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    public static function reset(): void {
        self::$pool = [];
    }

    public static function pdo(string $connection = 'default'): \PDO {
        if (!isset(self::$pool[$connection])) {
            // Auto-connect from config on first use — avoids manual boot wiring.
            $cfg = config("db.{$connection}")
                ?? throw new \RuntimeException("No DB connection '{$connection}' in config/db.php");
            self::connect($connection, $cfg);
        }
        return self::$pool[$connection];
    }

    // --- query execution ---

    /**
     * @ai-contract input   $sql template with %placeholders%, $params array (see query_builder.php)
     * @ai-contract returns array of rows if SELECT, int rowCount if INSERT/UPDATE/DELETE
     * @ai-contract returns string if debug:true — interpolated SQL, query NOT executed
     * @ai-contract side-effect records query in profiler when APP_DEBUG=true
     * @ai-contract null-safe %where% silently removed if conditions resolve to empty
     * @ai-contract null-safe null values in %set% are silently skipped
     *
     * @return array<int,array>|int|string  rows / affected count / SQL string (debug)
     */
    public static function query(
        string $sql,
        array  $params = [],
        bool   $debug = false,
        string $connection = 'default',
    ): mixed {
        [$built_sql, $pdo_params] = query_builder::build($sql, $params);

        if ($debug) {
            return query_builder::interpolate($built_sql, $pdo_params);
        }

        $t    = microtime(true);
        $stmt = self::pdo($connection)->prepare($built_sql);
        $stmt->execute($pdo_params);
        $rows = $stmt->fetchAll();

        profiler::db(query_builder::interpolate($built_sql, $pdo_params), (microtime(true) - $t) * 1000, $connection, count($rows));

        return $rows !== [] ? $rows : $stmt->rowCount();
    }

    /**
     * @ai-contract returns single scalar value (COUNT, MAX, single column), null if no row
     * @ai-contract side-effect records query in profiler when APP_DEBUG=true
     */
    public static function val(
        string $sql,
        array  $params = [],
        string $connection = 'default',
    ): mixed {
        [$built_sql, $pdo_params] = query_builder::build($sql, $params);
        $t    = microtime(true);
        $stmt = self::pdo($connection)->prepare($built_sql);
        $stmt->execute($pdo_params);
        $row  = $stmt->fetch(\PDO::FETCH_NUM);
        profiler::db(query_builder::interpolate($built_sql, $pdo_params), (microtime(true) - $t) * 1000, $connection, $row ? 1 : 0);
        return $row ? $row[0] : null;
    }

    /**
     * @ai-contract returns single row as associative array, null if not found — never throws
     * @ai-contract side-effect records query in profiler when APP_DEBUG=true
     */
    public static function row(
        string $sql,
        array  $params = [],
        string $connection = 'default',
    ): ?array {
        [$built_sql, $pdo_params] = query_builder::build($sql, $params);
        $t    = microtime(true);
        $stmt = self::pdo($connection)->prepare($built_sql);
        $stmt->execute($pdo_params);
        $row  = $stmt->fetch() ?: null;
        profiler::db(query_builder::interpolate($built_sql, $pdo_params), (microtime(true) - $t) * 1000, $connection, $row ? 1 : 0);
        return $row;
    }

    /**
     * @ai-contract returns all rows as array, empty array if none matched — never null
     * @ai-contract side-effect records query in profiler when APP_DEBUG=true
     */
    public static function all(
        string $sql,
        array  $params = [],
        string $connection = 'default',
    ): array {
        [$built_sql, $pdo_params] = query_builder::build($sql, $params);
        $t    = microtime(true);
        $stmt = self::pdo($connection)->prepare($built_sql);
        $stmt->execute($pdo_params);
        $rows = $stmt->fetchAll();
        profiler::db(query_builder::interpolate($built_sql, $pdo_params), (microtime(true) - $t) * 1000, $connection, count($rows));
        return $rows;
    }

    /**
     * @ai-contract wraps callable in BEGIN/COMMIT, auto-ROLLBACK on any exception
     * @ai-contract throws original exception after rollback — never swallows
     * @ai-contract always use for multi-table writes — partial writes corrupt data
     */
    public static function transaction(callable $fn, string $connection = 'default'): mixed {
        $pdo = self::pdo($connection);
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @ai-contract returns null_marker sentinel — use in %set% to explicitly SET col = NULL
     * @ai-contract 'col' => null skips the column; 'col' => db::null() sets it to NULL
     */
    public static function null(): null_marker {
        return null_marker::make();
    }
}
