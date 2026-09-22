<?php declare(strict_types=1);

namespace Skim\Db\Exceptions;

/**
 * Wraps PDOException with the failed SQL for debugging. #AI:class
 *
 * Use when catching database errors — always catch db_exception, never
 * generic \Exception. The original PDOException is available via getPrevious().
 *
 * Example:
 *   try {
 *       Db::query('SELECT * FROM missing_table');
 *   } catch (DbException $e) {
 *       Log::error($e->getMessage());
 *   }
 *
 * Testing: Trigger by executing invalid SQL against a test_db() connection.
 *
 * #AI:class
 */
class DbException extends \RuntimeException {
    /**
     * Wraps a PDO error with the originating SQL string. #AI:__construct
     *
     * @param string     $sql      The SQL that caused the failure.
     * @param \Throwable $previous The underlying PDOException.
     */
    public function __construct(string $sql, \Throwable $previous) {
        parent::__construct("DB error executing: {$sql}", 0, $previous);
    }
}

#AI:class
#AI symbol: Skim\Db\Exceptions\DbException
#AI source_path: src/Db/Exceptions/DbException.php
#AI title: DbException
#AI description: RuntimeException wrapping PDO failures with the failed SQL string for debugging.
#AI role: database error wrapper
#AI layer: db
#AI badges: [exception; db; error-context]
#AI intro: `DbException` extends `\RuntimeException` and prepends the failed SQL to the error message. All PDO calls in `Db::` are wrapped so that failures carry the originating query for log and debug output.
#AI lifecycle: thrown on PDO failure, caught by application code
#AI fallback: none
#AI test_seam: trigger with invalid SQL against test_db() SQLite connection
#AI invariants: [always wraps a previous \Throwable; message includes the failed SQL string]
#AI core_behaviors: [Provides SQL context in exception message for debugging]
#AI warnings: []
#AI notes: Catch `DbException` specifically — never catch generic `\Exception`.
#AI scope_items: []
#AI owns: nothing
#AI entry_points: [__construct]
#AI config_reads: []
#AI non_goals: [Does not retry queries; Does not mask the underlying PDO error]
#AI side_effects: []
#AI flow: PDO throws -> Db:: catches -> new DbException($sql, $pdo_exception) -> rethrown
#AI lifecycle_steps: [PDO operation fails; -> Db:: catches PDOException; -> new DbException(sql, previous); -> thrown to caller]
#AI section_order: [Constructor]
#AI architectural_notes: Thin wrapper — all intelligence lives in Db::query() and friends.

#AI:__construct
#AI group: Constructor
#AI frequency: internal
#AI signature: public function __construct(string $sql, \Throwable $previous)
#AI contract: Builds the exception message by prepending the failed SQL to the PDO error.
#AI param_details: [{name: $sql | type: string | required: true | desc: The SQL statement that failed.}; {name: $previous | type: \Throwable | required: true | desc: The underlying PDOException.}]
