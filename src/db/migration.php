<?php declare(strict_types=1);

namespace skim\db;

// Abstract base for all migrations.
// SQL-first: raw SQL gives full control over indexes, charset, collation.
// No fluent schema builder — what you write is exactly what runs on the database.
//
// File naming convention: YYYY_MM_DD_HHMMSS_description.php
// The timestamp prefix guarantees deterministic run order.
abstract class migration {
    /**
     * @ai-contract returns raw SQL string to execute on migrate:run
     * @ai-contract may contain multiple statements separated by semicolons
     */
    abstract public function up(): string;

    /**
     * @ai-contract returns raw SQL to exactly reverse up() — used by migrate:down and migrate:fresh
     * @ai-contract always implement even if you never plan to rollback — fresh dev environments need it
     */
    abstract public function down(): string;

    /**
     * @ai-contract returns the migration filename used as the unique identifier in _migrations table
     * @ai-contract set automatically by migrator::load() from the file basename
     */
    public string $filename = '';
}
