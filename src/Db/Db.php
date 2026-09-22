<?php declare(strict_types=1);

namespace Skim\Db;

use Skim\Dev\Profiler;

/**
 * Static facade for all database operations using query_gen SQL templates. #AI:class
 *
 * Use for raw SQL queries with %placeholder% substitution. Connections are
 * lazy — PDO is created on first query, not on config load. All queries are
 * recorded in the profiler when APP_DEBUG=true.
 *
 * Example:
 *   $users = Db::all('SELECT * FROM users %where% %limit%', [
 *       'where' => ['status = :status'], ':status' => 'active', 'limit' => 20,
 *   ]);
 *   Db::transaction(fn() => Db::query('UPDATE accounts %set% WHERE id = :id', [
 *       'set' => ['balance' => 100], ':id' => 1,
 *   ]));
 *
 * Testing: Use test_db() for SQLite :memory:, Db::reset() to clear connections.
 *
 * #AI:class
 */
class Db {
    /** @var array<string, \PDO> */
    private static array $pool = [];

    // --- connection management ---

    /**
     * Registers and opens a named database connection. #AI:connect
     *
     * @param string $name   Connection name (e.g. 'default', 'alt').
     * @param array  $config Driver config array from config/db.php.
     */
    public static function connect(string $name, array $config): void {
        self::$pool[$name] = self::makePdo($config);
    }

    private static function makePdo(array $cfg): \PDO {
        if ($cfg['driver'] === 'sqlite' && ($cfg['database'] ?? '') !== ':memory:') {
            $dir = dirname($cfg['database']);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

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

    /**
     * Clears all pooled connections (testing only). #AI:reset
     *
     * Call in tearDown() after test_db() or when DB config changes at runtime.
     */
    public static function reset(): void {
        self::$pool = [];
    }

    /**
     * Returns the number of active pooled connections. #AI:connection_count
     */
    public static function connectionCount(): int {
        return count(self::$pool);
    }

    /**
     * Returns true if any pooled connection has an open transaction. #AI:has_open_transaction
     */
    public static function hasOpenTransaction(): bool {
        foreach (self::$pool as $conn) {
            if ($conn->inTransaction()) {
                return true;
            }
        }
        return false;
    }
    /**
     * Rolls back any open transactions on all pooled connections. #AI:rollback_all
     *
     * Safety net for worker mode: if a request exits with an uncommitted
     * transaction, the next request must not inherit it. Called by
     * WorkerReset::apply() between requests.
     */
    public static function rollbackAll(): void {
        foreach (self::$pool as $conn) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
        }
    }

    /**
     * Returns the PDO instance for a named connection. #AI:pdo
     *
     * Auto-connects from config/db.php on first access — no manual boot wiring needed.
     *
     * In worker mode, pooled connections are checked for liveness before reuse.
     * If the server closed the connection (MySQL codes 2006/2013/HY000), the
     * pool entry is dropped and a fresh connection is opened once.
     *
     * @param string $connection Named connection from config/db.php.
     */
    public static function pdo(string $connection = 'default'): \PDO {
        if (isset(self::$pool[$connection])) {
            $conn = self::$pool[$connection];
            try {
                $conn->query('SELECT 1');
                return $conn;
            } catch (\PDOException $e) {
                if (in_array($e->getCode(), ['2006', '2013', 'HY000'], true)) {
                    unset(self::$pool[$connection]);
                }
            }
        }

        if (!isset(self::$pool[$connection])) {
            $cfg = config("db.{$connection}")
                ?? throw new \RuntimeException("No DB connection '{$connection}' in config/db.php");
            self::connect($connection, $cfg);
        }

        return self::$pool[$connection];
    }

    /**
     * Executes a query_gen SQL template and returns rows or affected count. #AI:query
     *   $rows = Db::query('SELECT * FROM users %where% %limit%', [
     *       'where' => ['status = :s'], ':s' => 'active', 'limit' => 10,
     *   ]);
     *
     * @param string $sql        SQL template with %placeholders%.
     * @param array  $params     Placeholder values and :named params.
     * @param bool   $debug      When true, returns interpolated SQL without executing.
     * @param string $connection Named DB connection to use.
     * @return array<int,array>|int|string Rows for SELECT, rowCount for DML, SQL string for debug.
     */
    public static function query(
        string $sql,
        array  $params = [],
        bool   $debug = false,
        string $connection = 'default',
    ): mixed {
        [$builtSql, $pdoParams] = \Skim\Db\QueryBuilder::build($sql, $params);

        if ($debug) {
            return \Skim\Db\QueryBuilder::interpolate($builtSql, $pdoParams);
        }

        $t    = microtime(true);
        $stmt = self::pdo($connection)->prepare($builtSql);
        $stmt->execute($pdoParams);
        $rows = $stmt->fetchAll();

        \Skim\Dev\Profiler::db(\Skim\Db\QueryBuilder::interpolate($builtSql, $pdoParams), (microtime(true) - $t) * 1000, $connection, count($rows));

        return $rows !== [] ? $rows : $stmt->rowCount();
    }

    /**
     * Returns a single scalar value from the first column of the first row. #AI:val
     *
     * Ideal for COUNT, MAX, SUM queries. Returns null when no row matches.
     *
     * @param string $sql        SQL template with %placeholders%.
     * @param array  $params     Placeholder values and :named params.
     * @param string $connection Named DB connection to use.
     */
    public static function val(
        string $sql,
        array  $params = [],
        string $connection = 'default',
    ): mixed {
        [$builtSql, $pdoParams] = \Skim\Db\QueryBuilder::build($sql, $params);
        $t    = microtime(true);
        $stmt = self::pdo($connection)->prepare($builtSql);
        $stmt->execute($pdoParams);
        $row  = $stmt->fetch(\PDO::FETCH_NUM);
        \Skim\Dev\Profiler::db(\Skim\Db\QueryBuilder::interpolate($builtSql, $pdoParams), (microtime(true) - $t) * 1000, $connection, $row ? 1 : 0);
        return $row ? $row[0] : null;
    }

    /**
     * Returns a single row as an associative array, or null if not found. #AI:row
     *
     * Never throws on missing rows — always check for null.
     *
     * @param string $sql        SQL template with %placeholders%.
     * @param array  $params     Placeholder values and :named params.
     * @param string $connection Named DB connection to use.
     */
    public static function row(
        string $sql,
        array  $params = [],
        string $connection = 'default',
    ): ?array {
        [$builtSql, $pdoParams] = \Skim\Db\QueryBuilder::build($sql, $params);
        $t    = microtime(true);
        $stmt = self::pdo($connection)->prepare($builtSql);
        $stmt->execute($pdoParams);
        $row  = $stmt->fetch() ?: null;
        \Skim\Dev\Profiler::db(\Skim\Db\QueryBuilder::interpolate($builtSql, $pdoParams), (microtime(true) - $t) * 1000, $connection, $row ? 1 : 0);
        return $row;
    }

    /**
     * Returns all matching rows as an array — empty array if none matched. #AI:all
     *
     * Never returns null. Always use limit on large tables to avoid memory exhaustion.
     *
     * @param string $sql        SQL template with %placeholders%.
     * @param array  $params     Placeholder values and :named params.
     * @param string $connection Named DB connection to use.
     */
    public static function all(
        string $sql,
        array  $params = [],
        string $connection = 'default',
    ): array {
        [$builtSql, $pdoParams] = \Skim\Db\QueryBuilder::build($sql, $params);
        $t    = microtime(true);
        $stmt = self::pdo($connection)->prepare($builtSql);
        $stmt->execute($pdoParams);
        $rows = $stmt->fetchAll();
        \Skim\Dev\Profiler::db(\Skim\Db\QueryBuilder::interpolate($builtSql, $pdoParams), (microtime(true) - $t) * 1000, $connection, count($rows));
        return $rows;
    }

    /**
     * Wraps a callable in BEGIN/COMMIT with auto-ROLLBACK on any exception. #AI:transaction
     *
     * WARNING: Always use for multi-table writes — partial writes corrupt data.
     * The original exception is rethrown after rollback, never swallowed.
     *
     * Example:
     *   Db::transaction(function() {
     *       Db::query('UPDATE accounts %set% WHERE id = :id', ['set' => ['balance' => 100], ':id' => 1]);
     *       Db::query('UPDATE accounts %set% WHERE id = :id', ['set' => ['balance' => 200], ':id' => 2]);
     *   });
     *
     * @param callable $fn         Code to execute inside the transaction.
     * @param string   $connection Named DB connection to use.
     */
    public static function transaction(callable $fn, string $connection = 'default'): mixed {
        $pdo = self::pdo($connection);
        $pdo->beginTransaction();
        try {
            $result = $fn();
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            return $result;

		} catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Returns a null_marker sentinel for forcing SET col = NULL in %set%. #AI:null
     *
     * Plain null in %set% skips the column; Db::null() sets it to NULL.
     *
     * Example:
     *   Db::query('UPDATE users %set% WHERE id = :id', [
     *       'set' => ['avatar' => Db::null()], ':id' => 5,
     *   ]);
     */
    public static function null(): \Skim\Db\NullMarker {
        return \Skim\Db\NullMarker::make();
    }
}

#AI:class
#AI symbol: Skim\Db\Db
#AI source_path: src/Db/Db.php
#AI title: db
#AI description: Static facade for database operations using query_gen SQL templates with lazy connections and profiler integration.
#AI role: static database facade
#AI layer: db
#AI badges: [facade; db; query_gen; lazy-connect; profiler]
#AI intro: `db` is the static entry point for all raw SQL operations. It uses query_gen `%placeholder%` templates processed by `QueryBuilder` (internal). Connections are lazy — PDO is created on first query, not on config load. All queries are recorded in the profiler when APP_DEBUG is enabled.
#AI lifecycle: static facade, connections resolved lazily on first query per named connection
#AI fallback: none — missing connection config throws RuntimeException
#AI test_seam: test_db() for SQLite :memory:, reset() to clear connection pool
#AI invariants: [connections are lazy — PDO created on first query; query() returns rows for SELECT, rowCount for DML; val() returns null on no match; row() returns null on no match; all() returns empty array on no match; transaction() auto-rolls back on any Throwable]
#AI core_behaviors: [query_gen %placeholders% are substituted by QueryBuilder; unused placeholders stripped silently; debug:true returns interpolated SQL without executing; profiler records every query with timing]
#AI warnings: [Always use transaction() for multi-table writes; Always use limit on large tables with all()]
#AI notes: The connection pool is process-local. Call reset() in test tearDown() to clear connections.
#AI scope_items: []
#AI owns: PDO connection pool
#AI entry_points: [query; val; row; all; transaction; null; pdo; connect; reset]
#AI config_reads: [db.default; db.*.driver; db.*.host; db.*.port; db.*.database; db.*.charset; db.*.user; db.*.password]
#AI non_goals: [Does not provide an ORM — use model for active record; Does not handle migrations — use migrator; QueryBuilder is internal, not public API]
#AI side_effects: [Profiler::db records every query; connect() opens PDO connections; reset() closes all pooled connections]
#AI flow: Db::method() -> QueryBuilder::build() -> PDO prepare/execute -> Profiler::db()
#AI lifecycle_steps: [Db::query/val/row/all(); -> QueryBuilder::build(sql, params); -> pdo(connection) auto-connects if needed; -> PDO prepare + execute; -> Profiler::db() records timing; -> return rows/scalar/count]
#AI section_order: [Connection Management; Query Execution; Transactions; Utilities; Testing Hooks]
#AI architectural_notes: The facade keeps raw SQL as the primary interface. QueryBuilder handles %placeholder% substitution internally — it is not part of the public API.

#AI:connect
#AI group: Connection Management
#AI frequency: low
#AI signature: public static function connect(string $name, array $config): void
#AI contract: Registers and immediately opens a named PDO connection. The config array must contain driver, host/database, and optional port/charset/credentials.
#AI param_details: [{name: $name | type: string | required: true | desc: Connection name used in all query methods.}; {name: $config | type: array | required: true | desc: Driver config array from config/db.php.}]
#AI side_effects: Opens a PDO connection and stores it in the pool.

#AI:reset
#AI group: Testing Hooks
#AI frequency: low
#AI signature: public static function reset(): void
#AI contract: Clears all pooled PDO connections. Forces re-connection from config on the next query.
#AI side_effects: Closes all pooled connections.

#AI:pdo
#AI group: Connection Management
#AI frequency: internal
#AI signature: public static function pdo(string $connection = 'default'): \PDO
#AI contract: Returns the PDO instance for the named connection. Auto-connects from config/db.php on first access.
#AI param_details: [{name: $connection | type: string | required: false | desc: Named connection. Default 'default'.}]
#AI return_detail: {type: \PDO | desc: The PDO instance for the named connection.}
#AI throws_details: [{type: \RuntimeException | desc: When the named connection is not defined in config/db.php.}]

#AI:query
#AI group: Query Execution
#AI frequency: high
#AI signature: public static function query(string $sql, array $params = [], bool $debug = false, string $connection = 'default'): mixed
#AI contract: Executes a query_gen SQL template. Returns array of rows for SELECT, int rowCount for INSERT/UPDATE/DELETE. When $debug is true, returns the interpolated SQL string without executing.
#AI param_details: [{name: $sql | type: string | required: true | desc: SQL template with %placeholders%.}; {name: $params | type: array | required: false | desc: Placeholder values and :named params.}; {name: $debug | type: bool | required: false | desc: When true, returns interpolated SQL without executing.}; {name: $connection | type: string | required: false | desc: Named DB connection.}]
#AI return_detail: {type: array|int|string | desc: Rows for SELECT, rowCount for DML, SQL string when debug is true.}
#AI side_effects: Executes SQL, records in profiler.

#AI:val
#AI group: Query Execution
#AI frequency: high
#AI signature: public static function val(string $sql, array $params = [], string $connection = 'default'): mixed
#AI contract: Returns a single scalar value from the first column of the first row. Returns null when no row matches.
#AI param_details: [{name: $sql | type: string | required: true | desc: SQL template with %placeholders%.}; {name: $params | type: array | required: false | desc: Placeholder values and :named params.}; {name: $connection | type: string | required: false | desc: Named DB connection.}]
#AI return_detail: {type: mixed | desc: Scalar value or null.}
#AI side_effects: Executes SQL, records in profiler.

#AI:row
#AI group: Query Execution
#AI frequency: high
#AI signature: public static function row(string $sql, array $params = [], string $connection = 'default'): ?array
#AI contract: Returns a single row as an associative array. Returns null when no row matches — never throws.
#AI param_details: [{name: $sql | type: string | required: true | desc: SQL template with %placeholders%.}; {name: $params | type: array | required: false | desc: Placeholder values and :named params.}; {name: $connection | type: string | required: false | desc: Named DB connection.}]
#AI return_detail: {type: ?array | desc: Associative array or null.}
#AI side_effects: Executes SQL, records in profiler.

#AI:all
#AI group: Query Execution
#AI frequency: high
#AI signature: public static function all(string $sql, array $params = [], string $connection = 'default'): array
#AI contract: Returns all matching rows as an array. Returns empty array when no rows match — never null.
#AI param_details: [{name: $sql | type: string | required: true | desc: SQL template with %placeholders%.}; {name: $params | type: array | required: false | desc: Placeholder values and :named params.}; {name: $connection | type: string | required: false | desc: Named DB connection.}]
#AI return_detail: {type: array | desc: Array of associative arrays.}
#AI side_effects: Executes SQL, records in profiler.

#AI:transaction
#AI group: Transactions
#AI frequency: high
#AI signature: public static function transaction(callable $fn, string $connection = 'default'): mixed
#AI contract: Wraps $fn in BEGIN/COMMIT. Auto-ROLLBACK on any Throwable. The original exception is rethrown after rollback — never swallowed.
#AI param_details: [{name: $fn | type: callable | required: true | desc: Code to execute inside the transaction.}; {name: $connection | type: string | required: false | desc: Named DB connection.}]
#AI return_detail: {type: mixed | desc: Return value of $fn.}
#AI warnings: [Always use for multi-table writes — partial writes corrupt data]
#AI side_effects: Manages transaction state on the PDO connection.

#AI:null
#AI group: Utilities
#AI frequency: medium
#AI signature: public static function null(): NullMarker
#AI contract: Returns a NullMarker sentinel for use in %set% to force SET col = NULL. Plain null skips the column.
#AI return_detail: {type: NullMarker | desc: Sentinel that QueryBuilder translates to literal NULL.}
