<?php declare(strict_types=1);

namespace skim\validation;

/**
 * Interface for custom validation rule objects. #AI:class
 *
 * Implement this interface to create reusable rule objects that can be
 * placed directly in rule arrays. Useful for complex rules that need
 * constructor parameters (e.g., database uniqueness checks).
 *
 * Example:
 *   class unique_rule implements rule {
 *       public function __construct(
 *           private string $table,
 *           private string $column,
 *       ) {}
 *       public function validate(mixed $value, string $field, array $data): bool {
 *           return !db::table($this->table)->where($this->column, $value)->exists();
 *       }
 *       public function message(string $field): string {
 *           return "The {$field} is already taken.";
 *       }
 *   }
 *
 * Testing: Implement and pass directly in rule arrays to check().
 *
 * #AI:class
 */
interface rule {
    public function validate(mixed $value, string $field, array $data): bool;
    public function message(string $field): string;
}

#AI:class
#AI symbol: skim\validation\rule
#AI source_path: src/validation/rule.php
#AI title: rule
#AI description: Interface for custom validation rule objects that can be placed directly in rule arrays.
#AI role: validation rule contract
#AI layer: validation
#AI badges: [validation; rule; interface; contract]
#AI intro: `rule` is the interface that custom validation rule objects must implement. It allows rule objects to be placed directly in rule arrays passed to `validate::make()`, enabling complex rules with constructor parameters.
#AI lifecycle: instantiated by user code, passed in rule arrays, evaluated during check()
#AI fallback: n/a — interface only
#AI test_seam: implement and pass in rule arrays
#AI invariants: [validate() receives the field value, field name, and full data array; message() receives the field name for interpolation]
#AI core_behaviors: [validate() returns true on pass, false on fail; message() returns the error message string]
#AI notes: Rule objects are evaluated before string rules in the check() loop, allowing them to short-circuit validation.
#AI owns: nothing — interface only
#AI entry_points: [validate; message]
#AI config_reads: []
#AI non_goals: [Does not register rules globally; Does not support parameters via colon syntax]
#AI side_effects: []
#AI flow: user creates rule object -> passes in rule array -> check() calls validate() -> on failure calls message()
#AI lifecycle_steps: [user instantiates rule object with params; -> passed to make() rules; -> check() calls validate(value, field, data); -> if false, calls message(field)]
#AI section_order: [Rule Interface; Architecture]
#AI architectural_notes: Kept minimal — two methods only. The validate() method gets full context (value, field, data) for complex checks.
