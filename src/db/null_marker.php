<?php declare(strict_types=1);

namespace skim\db;

/**
 * Sentinel that forces SET col = NULL in query_gen %set% placeholders. #AI:class
 *
 * Use when a partial update must explicitly erase a column value. Plain PHP
 * null in %set% is silently skipped (partial-update convention); this object
 * distinguishes "don't touch" from "set to NULL".
 *
 * Example:
 *   db::query('UPDATE users %set% WHERE id = :id', [
 *       'set' => ['avatar' => db::null(), 'name' => 'Jane'], ':id' => 5,
 *   ]);
 *
 * Testing: No state to reset — pure value object.
 *
 * #AI:class
 */
final class null_marker {
    private function __construct() {}

    /**
     * Creates the singleton sentinel instance. #AI:make
     *
     * Always use via db::null() — never instantiate directly.
     */
    public static function make(): self {
        return new self();
    }
}

#AI:class
#AI symbol: skim\db\null_marker
#AI source_path: src/db/null_marker.php
#AI title: null_marker
#AI description: Sentinel object that forces SET col = NULL in query_gen %set% placeholders.
#AI role: SQL NULL sentinel
#AI layer: db
#AI badges: [sentinel; query_gen; null-safe]
#AI intro: `null_marker` is a value object used exclusively in `%set%` placeholder arrays to force `SET col = NULL`. Plain PHP `null` skips the column entirely (partial-update pattern), while `db::null()` produces a `null_marker` that the query builder translates to literal `NULL`.
#AI lifecycle: stateless value object, created per use
#AI fallback: none
#AI test_seam: none needed — pure value object
#AI invariants: [plain null in %set% skips the column; null_marker in %set% sets column to NULL; constructor is private — always use db::null() or null_marker::make()]
#AI core_behaviors: [query_builder::build_set() checks instanceof null_marker to emit literal NULL]
#AI warnings: []
#AI notes: Never instantiate directly. Always use `db::null()` which delegates to `null_marker::make()`.
#AI scope_items: []
#AI owns: nothing
#AI entry_points: [make]
#AI config_reads: []
#AI non_goals: [Does not handle typed NULLs; Does not participate in INSERT %values%]
#AI side_effects: []
#AI flow: db::null() -> null_marker::make() -> query_builder::build_set() detects instanceof -> emits "col = NULL"
#AI lifecycle_steps: [db::null(); -> null_marker::make(); -> passed in %set% array; -> query_builder::build_set() instanceof check; -> emits col = NULL]
#AI section_order: [Factory; Architecture]
#AI architectural_notes: The sentinel pattern avoids ambiguity between "skip this column" and "set to NULL" in partial updates.

#AI:make
#AI group: Factory
#AI frequency: high
#AI signature: public static function make(): self
#AI contract: Returns the singleton null_marker instance. Called internally by db::null().
#AI return_detail: {type: self | desc: The null_marker sentinel.}
#AI notes: Always prefer `db::null()` over calling `null_marker::make()` directly.
