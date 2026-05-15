<?php declare(strict_types=1);

namespace skim\db\exceptions;

// Wraps PDOException with context about which query failed.
// db.php wraps all PDO calls in try/catch and rethrows as db_exception.
class db_exception extends \RuntimeException {
    public function __construct(string $sql, \Throwable $previous) {
        parent::__construct("DB error executing: {$sql}", 0, $previous);
    }
}
