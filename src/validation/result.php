<?php declare(strict_types=1);

namespace skim\validation;

/**
 * Immutable validation result returned by validate::check(). #AI:class
 *
 * Use in controllers to branch on ok(), display errors(), or pass validated()
 * data to model::create(). All three methods are the only calls needed.
 *
 * Example:
 *   $result = validate::make([...])->check($req->post());
 *   if (!$result->ok()) {
 *       return $res->status(422)->json(['errors' => $result->errors()]);
 *   }
 *   $user = user::create($result->validated());
 *
 * Testing: Construct directly with known errors/validated arrays.
 *
 * #AI:class
 */
final class result {
    public function __construct(
        private readonly array $errors,      // ['field' => ['message', ...]]
        private readonly array $validated,   // only declared fields, values cast
    ) {}

    /**
     * Returns true when no validation errors were found. #AI:ok
     */
    public function ok(): bool {
        return $this->errors === [];
    }

    /**
     * Returns field-to-messages map matching 422 API response shape. #AI:errors
     *
     * @return array<string, string[]> Map of field name to error message list.
     */
    public function errors(): array {
        return $this->errors;
    }

    /**
     * Returns only declared fields — safe for model::create(). #AI:validated
     *
     * Undeclared POST fields are silently dropped, preventing mass-assignment
     * of unexpected fields.
     */
    public function validated(): array {
        return $this->validated;
    }
}

#AI:class
#AI symbol: skim\validation\result
#AI source_path: src/validation/result.php
#AI title: result
#AI description: Immutable validation result with ok(), errors(), and validated() accessors.
#AI role: validation result value object
#AI layer: validation
#AI badges: [validation; result; immutable; value-object]
#AI intro: `result` is the immutable value object returned by `validate::check()`. It carries both the error map and the validated data subset, providing the three methods controllers need: `ok()`, `errors()`, and `validated()`.
#AI lifecycle: created by validate::check(), consumed by controller in the same request
#AI fallback: n/a — pure value object
#AI test_seam: construct directly with known arrays
#AI invariants: [ok() returns true only when errors array is empty; validated() contains only fields declared in the rules; errors() shape matches 422 JSON response format]
#AI core_behaviors: [Immutable — all properties are readonly; errors() returns field-to-messages map; validated() returns only rule-declared fields]
#AI notes: validated() silently drops undeclared fields, providing mass-assignment safety without explicit guarded lists.
#AI owns: errors array, validated array
#AI entry_points: [ok; errors; validated]
#AI config_reads: []
#AI non_goals: [Does not run validation; Does not format error messages for display]
#AI side_effects: []
#AI flow: validate::check() -> new result($errors, $validated) -> controller reads ok/errors/validated
#AI lifecycle_steps: [validate::check() runs rules; -> new result(errors, validated); -> controller calls ok(); -> branches on result]
#AI section_order: [Result API; Architecture]
#AI architectural_notes: Kept as a pure value object — no behavior beyond accessors. The validate class is responsible for populating both arrays correctly.

#AI:ok
#AI group: Result API
#AI frequency: high
#AI signature: public function ok(): bool
#AI contract: Returns true when no validation errors were found.
#AI return_detail: {type: bool | desc: True if all fields passed validation.}

#AI:errors
#AI group: Result API
#AI frequency: high
#AI signature: public function errors(): array
#AI contract: Returns the field-to-messages error map. Shape matches the standard 422 JSON response format.
#AI return_detail: {type: array | desc: Associative array of field name to list of error message strings.}

#AI:validated
#AI group: Result API
#AI frequency: high
#AI signature: public function validated(): array
#AI contract: Returns only the fields declared in validation rules. Undeclared fields are silently dropped.
#AI return_detail: {type: array | desc: Associative array of validated field names to their values.}
