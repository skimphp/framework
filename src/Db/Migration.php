<?php declare(strict_types=1);

namespace Skim\Db;

/**
 * Abstract base for SQL-first database migrations. #AI:class
 *
 * Use when creating or altering tables — raw SQL gives full control over
 * indexes, charset, and collation. No fluent schema builder abstraction.
 * Files are named YYYY_MM_DD_HHMMSS_description.php for deterministic ordering.
 *
 * Example:
 *   return new class extends Migration {
 *       public function up(): string {
 *           return "CREATE TABLE posts (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255))";
 *       }
 *       public function down(): string { return "DROP TABLE IF EXISTS posts"; }
 *   };
 *
 * Testing: Run against test_db() SQLite :memory: connection via migrator.
 *
 * #AI:class
 */
abstract class Migration {
    /**
     * Migration filename used as unique identifier in _migrations table. #AI:filename
     *
     * Set automatically by migrator from the file basename — never assign manually.
     */
    public string $filename = '';

    /** Run inside a transaction. #AI:transactional */
    public bool $transactional = true;

    /** Whether this migration supports rollback. #AI:reversible */
    public bool $reversible = true;

    /** Connection name override. Null falls back to the migrator's connection. #AI:connection */
    public ?string $connection = null;

    /**
     * Returns the SQL, array of SQL, or callable to apply on migrate:run. #AI:up
     *
     * String = single statement. Array = one element per statement.
     * Callable receives PDO and runs custom logic.
     */
    abstract public function up(): string|array|callable;

    /**
     * Returns the SQL, array of SQL, or callable to reverse up(). #AI:down
     *
     * Override when needed. Base default throws if $reversible is false.
     */
    public function down(): string|array|callable {
        if (!$this->reversible) {
            throw new \LogicException("Migration [{$this->filename}] is irreversible.");
        }
        return '';
    }
}

#AI:class
#AI symbol: Skim\Db\Migration
#AI source_path: src/Db/Migration.php
#AI title: migration
#AI description: Abstract base class for SQL-first database migrations with up/down contract.
#AI role: migration base class
#AI layer: db
#AI badges: [abstract; migration; sql-first]
#AI intro: `migration` is the abstract base for all database migrations. Each migration file returns an anonymous class extending this base, implementing `up()` and `down()` with raw SQL strings. The migrator tracks applied migrations by filename in the `_migrations` table.
#AI lifecycle: instantiated by Migrator::loadAll() via require, filename assigned from basename
#AI fallback: none
#AI test_seam: run via migrator against test_db() SQLite :memory: connection
#AI invariants: [up() and down() must return valid SQL strings; filename is set by migrator, never manually; down() must exactly reverse up()]
#AI core_behaviors: [SQL-first approach — no schema builder abstraction; multi-statement SQL supported via semicolon splitting]
#AI warnings: []
#AI notes: File naming convention is YYYY_MM_DD_HHMMSS_description.php for deterministic sort order.
#AI scope_items: []
#AI owns: nothing
#AI entry_points: [up; down]
#AI config_reads: []
#AI non_goals: [Does not execute SQL — migrator handles execution; Does not track state — _migrations table handles that]
#AI side_effects: []
#AI flow: migrator requires file -> migration instance -> up()/down() returns SQL -> migrator executes
#AI lifecycle_steps: [Migrator::loadAll() requires migration file; -> migration instance created; -> filename assigned from basename; -> migrator calls up() or down(); -> SQL string returned; -> Migrator executes via MigrationExecutor]
#AI section_order: [Migration Contract; Properties]
#AI architectural_notes: SQL-first design avoids the impedance mismatch of fluent schema builders. What you write is exactly what runs on the database.

#AI:up
#AI group: Migration Contract
#AI frequency: high
#AI signature: abstract public function up(): string|array|callable
#AI contract: Returns raw SQL, an array of SQL statements, or a callable receiving PDO to execute when migrating forward.
#AI return_detail: {type: string|array|callable | desc: SQL string, one element per statement, or custom callable.}

#AI:down
#AI group: Migration Contract
#AI frequency: high
#AI signature: public function down(): string|array|callable
#AI contract: Returns raw SQL, an array, or a callable to reverse up(). Used by migrate:down and migrate:fresh. Optional — base default throws when $reversible is false.
#AI return_detail: {type: string|array|callable | desc: SQL string, array, or callable to reverse the migration.}

#AI:filename
#AI group: Properties
#AI frequency: internal
#AI signature: public string $filename = ''
#AI contract: The migration filename used as unique identifier in the _migrations tracking table. Set by migrator from file basename.
