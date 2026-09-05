<?php declare(strict_types=1);

namespace Skim\Db;

/**
 * Sentinel that forces SET col = NULL in query_gen %set% placeholders. #AI:class
 *
 * Use when a partial update must explicitly erase a column value. Plain PHP
 * null in %set% is silently skipped (partial-update convention); this object
 * distinguishes "don't touch" from "set to NULL".
 *
 * Example:
 *   Db::query('UPDATE users %set% WHERE id = :id', [
 *       'set' => ['avatar' => Db::null(), 'name' => 'Jane'], ':id' => 5,
 *   ]);
 *
 * Testing: No state to reset — pure value object.
 *
 * #AI:class
 */
final class NullMarker {
    private function __construct() {}

    /**
     * Creates the singleton sentinel instance. #AI:make
     *
     * Always use via Db::null() — never instantiate directly.
     */
    public static function make(): self {
        return new self();
    }
}

#AI:class
#AI symbol: Skim\Db\NullMarker
#AI source_path: src/Db/NullMarker.php
#AI title: NullMarker
#AI description: Sentinel object that forces SET col = NULL in query_gen %set% placeholders.
#AI role: SQL NULL sentinel
#AI layer: db
#AI badges: [sentinel; query_gen; null-safe]
#AI intro: `NullMarker` is a value object used exclusively in `%set%` placeholder arrays to force `SET col = NULL`. Plain PHP `null` skips the column entirely (partial-update pattern), while `Db::null()` produces a `NullMarker` that the query builder translates to literal `NULL`.
#AI lifecycle: stateless value object, created per use
#AI fallback: none
#AI test_seam: none needed — pure value object
#AI invariants: [plain null in %set% skips the column; NullMarker in %set% sets column to NULL; constructor is private — always use Db::null() or NullMarker::make()]
#AI core_behaviors: [QueryBuilder::buildSet() checks instanceof NullMarker to emit literal NULL]
#AI warnings: []
#AI notes: Never instantiate directly. Always use `Db::null()` which delegates to `NullMarker::make()`.
#AI scope_items: []
#AI owns: nothing
#AI entry_points: [make]
#AI config_reads: []
#AI non_goals: [Does not handle typed NULLs; Does not participate in INSERT %values%]
#AI side_effects: []
#AI flow: Db::null() -> NullMarker::make() -> QueryBuilder::buildSet() detects instanceof -> emits "col = NULL"
#AI lifecycle_steps: [Db::null(); -> NullMarker::make(); -> passed in %set% array; -> QueryBuilder::buildSet() instanceof check; -> emits col = NULL]
#AI section_order: [Factory; Architecture]
#AI architectural_notes: The sentinel pattern avoids ambiguity between "skip this column" and "set to NULL" in partial updates.

#AI:make
#AI group: Factory
#AI frequency: high
#AI signature: public static function make(): self
#AI contract: Returns the singleton NullMarker instance. Called internally by Db::null().
#AI return_detail: {type: self | desc: The NullMarker sentinel.}
#AI notes: Always prefer `Db::null()` over calling `NullMarker::make()` directly.
