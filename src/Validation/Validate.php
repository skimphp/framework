<?php declare(strict_types=1);

namespace Skim\Validation;

/**
 * Zero-dependency validation engine with mass-assignment protection. #AI:class
 *
 * Use to validate request data before passing to Model::create(). Fields not
 * declared in make() are silently dropped from validated(), preventing
 * mass-assignment of unexpected POST fields.
 *
 * Example:
 *   $result = Validate::make([
 *       'email' => ['required', 'email'],
 *       'age'   => ['required', 'int', 'min:18'],
 *   ])->check($req->post());
 *
 *   if (!$result->ok) {
 *       return $res->status(422)->json(['errors' => $result->errors()]);
 *   }
 *   User::create($result->validated());
 *
 * Testing: Call make() and check() directly — no container or config needed.
 *
 * #AI:class
 */
class Validate {
    // Instance-level custom rules registry — safe for FrankenPHP worker mode.
    private array $customRules = [];

    private array $rules;

    public function __construct(array $rules) {
        $this->rules = $rules;
    }

    /**
     * Declares the expected field-to-rules map. #AI:make
     *
     * @param array $rules Map of field name to rule array or pipe-delimited string.
     */
    public static function make(array $rules): static {
        return new static($rules);
    }

    /**
     * Registers a custom rule on this validator instance. #AI:extend
     *
     * The callback receives the field value and returns true on pass or
     * false on fail. Use `:field` in the message as a placeholder for
     * the field name. Returns $this for fluent chaining.
     *
     * Example:
     *   Validate::make([...])
     *       ->extend('even', fn($v) => (int)$v % 2 === 0, ':field must be even')
     *       ->check($data);
     *
     * @param string   $name    Rule name used in rule lists.
     * @param callable $fn      Receives value, returns true on pass or false on fail.
     * @param string   $message Default error message (`:field` is replaced).
     */
    public function extend(string $name, callable $fn, string $message = 'Invalid.'): static {
        $this->customRules[$name] = ['fn' => $fn, 'message' => $message];
        return $this;
    }

    /**
     * Runs all rules against $data and returns a result. #AI:check
     *
     * Fields that pass all rules appear in validated(). Fields not declared
     * in make() are silently excluded. Optional fields absent from $data
     * pass validation without error.
     *
     * @param array $data Input data to validate (typically $req->post()).
     */
    public function check(array $data): \Skim\Validation\Result {
        $errors    = [];
        $validated = [];

        foreach ($this->rules as $field => $rules) {
            $value     = $data[$field] ?? null;
            $ruleList = is_string($rules) ? explode('|', $rules) : $rules;
            $fieldOk  = true;

            // Extract rule name strings for cast() — skip objects/callables
            $fieldRulesStrings = array_map(
                fn($r) => is_string($r) ? explode(':', $r, 2)[0] : '',
                $ruleList,
            );

            // Nullable shortcut: if field is nullable and value is null, skip all rules
            $isNullable = in_array('nullable', $fieldRulesStrings, true);
            if ($isNullable && $value === null) {
                $validated[$field] = null;
                continue;
            }

            foreach ($ruleList as $ruleItem) {
                // Rule object (implements Rule interface)
                if ($ruleItem instanceof \Skim\Validation\Rule) {
                    $ok = $ruleItem->validate($value, $field, $data);
                    if (!$ok) {
                        $errors[$field][] = $ruleItem->message($field);
                        $fieldOk = false;
                    }
                    continue;
                }

                // One-off callable (but not a string — strings are rule names)
                if (is_callable($ruleItem) && !is_string($ruleItem)) {
                    $ok = (bool) $ruleItem($value);
                    if (!$ok) {
                        $errors[$field][] = "The {$field} is invalid.";
                        $fieldOk = false;
                    }
                    continue;
                }

                // String rule — existing applyRule() path
                $error = $this->applyRule((string) $ruleItem, $field, $value, $data);
                if ($error !== null) {
                    $errors[$field][] = $error;
                    $fieldOk = false;
                }
            }

            // Include field in validated() if it passed, or if not required and absent
            if ($fieldOk) {
                $validated[$field] = $this->cast($fieldRulesStrings, $value);
            }
        }

        return new \Skim\Validation\Result($errors, $validated);
    }

    // --- rule evaluation ---

    private function applyRule(string $ruleStr, string $field, mixed $value, array $data): ?string {
        [$rule, $param] = array_pad(explode(':', $ruleStr, 2), 2, null);

        // Skip optional fields when value absent (empty/null) — unless 'required'
        if ($rule !== 'required' && ($value === null || $value === '')) {
            return null;
        }

        return match ($rule) {
            'required' => ($value === null || $value === '') ? "The {$field} field is required." : null,
            'email'    => !\Skim\Helpers\Filter::email($value) ? "The {$field} must be a valid email." : null,
            'url'      => !\Skim\Helpers\Filter::url($value) ? "The {$field} must be a valid URL." : null,
            'int'      => !\Skim\Helpers\Filter::int($value) ? "The {$field} must be an integer." : null,
            'float'    => !\Skim\Helpers\Filter::float($value) ? "The {$field} must be a number." : null,
            // Bool rule: explicit string allowlist check instead of Filter::bool() which always returns bool
            'bool'     => !in_array(
                is_bool($value) ? ($value ? 'true' : 'false') : strtolower(trim((string)$value)),
                ['1', '0', 'true', 'false', 'yes', 'no', 'on', 'off'],
                true,
            ) ? "The {$field} must be a boolean." : null,
            'slug'     => !\Skim\Helpers\Filter::slug($value) ? "The {$field} must be a valid slug." : null,
            // Smart min/max: numeric → compare value, string → compare mb_strlen()
            'min'      => match (true) {
                is_numeric($value) => (float)$value < (float)$param
                    ? "The {$field} must be at least {$param}." : null,
                is_string($value)  => mb_strlen($value) < (int)$param
                    ? "The {$field} must be at least {$param} characters." : null,
                default => null,
            },
            'max'      => match (true) {
                is_numeric($value) => (float)$value > (float)$param
                    ? "The {$field} must not exceed {$param}." : null,
                is_string($value)  => mb_strlen($value) > (int)$param
                    ? "The {$field} must not exceed {$param} characters." : null,
                default => null,
            },
            'in'       => !in_array($value, explode(',', (string)$param), true) ? "The {$field} must be one of: {$param}." : null,
            'regex'    => !preg_match((string)$param, (string)$value) ? "The {$field} format is invalid." : null,
            'same'     => ($value !== ($data[$param] ?? null)) ? "The {$field} must match {$param}." : null,
            'nullable' => null, // always passes — signals intent
            default    => $this->applyCustom($rule, $field, $value),
        };
    }

    private function applyCustom(string $rule, string $field, mixed $value): ?string {
        if (!isset($this->customRules[$rule])) {
            return null;   // unknown rules silently pass — prevents accidental lockouts
        }
        $entry = $this->customRules[$rule];
        if (!(bool)($entry['fn'])($value)) {
            return str_replace(':field', $field, $entry['message']);
        }
        return null;
    }

    /**
     * Casts validated values to their PHP types based on declared rules. #AI:cast
     *
     * @param array $ruleNames Flat list of rule name strings for the field.
     * @param mixed $value      The raw value from input data.
     */
    private function cast(array $ruleNames, mixed $value): mixed {
        return match (true) {
            in_array('int', $ruleNames, true)   => \Skim\Helpers\Filter::int($value) ?: $value,
            in_array('float', $ruleNames, true) => \Skim\Helpers\Filter::float($value) ?: $value,
            in_array('bool', $ruleNames, true)  => \Skim\Helpers\Filter::bool($value),
            in_array('email', $ruleNames, true) => \Skim\Helpers\Filter::email($value) ?: $value,
            default => $value,
        };
    }
}

#AI:class
#AI symbol: Skim\Validation\Validate
#AI source_path: src/Validation/Validate.php
#AI title: validate
#AI description: Zero-dependency validation engine with built-in rules, custom rule registration, and mass-assignment protection.
#AI role: validation engine
#AI layer: validation
#AI badges: [validation; engine; zero-deps; mass-assignment-safe]
#AI intro: `validate` is SKIM's built-in validation engine. It declares expected data shapes via `make()`, runs rules via `check()`, and returns a `result` with errors and validated data. Undeclared fields are silently dropped from `validated()`.
#AI lifecycle: instantiated per-validation via make(), check() runs synchronously
#AI fallback: n/a — own implementation
#AI test_seam: call make() and check() directly, register custom rules via extend()
#AI invariants: [Fields not in make() are excluded from validated(); Optional fields absent from data pass without error; Unknown custom rules silently pass; Custom rules are instance-scoped via extend() — safe for FrankenPHP worker mode]
#AI core_behaviors: [Built-in rules: required, email, url, int, float, bool, slug, min, max, in, regex, same, nullable; Custom rules via extend() with fluent return; Rule objects implementing rule interface; Inline callables in rule arrays; Pipe-delimited or array rule syntax; Typed validated() values via cast()]
#AI warnings: [Unknown rule names silently pass — typos in rule names go undetected]
#AI notes: Rules accept both array syntax `['required', 'email']` and pipe syntax `'required|email'`. The `:field` placeholder in custom rule messages is replaced with the actual field name. Custom rules are instance-scoped — register via extend() on the validator instance.
#AI owns: customRules instance property
#AI entry_points: [make; check; extend]
#AI config_reads: []
#AI non_goals: [Does not sanitize input; Does not handle file upload validation; Does not provide localized error messages]
#AI side_effects: []
#AI flow: Validate::make($rules) -> extend() (optional) -> check($data) -> applyRule() per field per rule -> new Result($errors, $validated)
#AI lifecycle_steps: [Validate::make([...]); -> extend() for custom rules; -> check($req->post()); -> foreach field -> foreach rule -> applyRule(); -> new Result(errors, validated); -> controller branches on ok]
#AI section_order: [Validation API; Custom Rules; Architecture]
#AI architectural_notes: Own implementation with zero external dependencies. Uses Skim\Helpers\Filter for type checking. Custom rules are instance-scoped via extend() — safe for long-lived FrankenPHP processes.

#AI:make
#AI group: Validation API
#AI frequency: high
#AI signature: public static function make(array $rules): static
#AI contract: Factory that declares the field-to-rules map and returns a validator instance.
#AI param_details: [{name: $rules | type: array | required: true | desc: Map of field name to rule array or pipe-delimited string.}]
#AI return_detail: {type: static | desc: Validator instance ready for extend() and check().}

#AI:extend
#AI group: Custom Rules
#AI frequency: medium
#AI signature: public function extend(string $name, callable $fn, string $message = 'Invalid.'): static
#AI contract: Registers a custom rule on this validator instance. Returns $this for fluent chaining. The callback receives the field value and returns true on pass or false on fail.
#AI param_details: [{name: $name | type: string | required: true | desc: Rule name used in rule lists.}; {name: $fn | type: callable | required: true | desc: Receives value, returns true on pass or false on fail.}; {name: $message | type: string | required: false | desc: Default error message. Use :field as placeholder for field name.}]
#AI side_effects: []
#AI warnings: []

#AI:check
#AI group: Validation API
#AI frequency: high
#AI signature: public function check(array $data): result
#AI contract: Runs all declared rules against $data. Returns a result where ok is false if any field failed any rule. Only declared fields appear in validated().
#AI param_details: [{name: $data | type: array | required: true | desc: Input data to validate, typically $req->post().}]
#AI return_detail: {type: result | desc: Immutable result with errors and validated().}

#AI:cast
#AI group: Internal
#AI frequency: high
#AI signature: private function cast(array $ruleNames, mixed $value): mixed
#AI contract: Casts validated values to their PHP types based on declared rules. Returns typed value for int, float, bool, email rules; raw value otherwise.
#AI param_details: [{name: $ruleNames | type: array | required: true | desc: Flat list of rule name strings for the field.}; {name: $value | type: mixed | required: true | desc: The raw value from input data.}]
#AI return_detail: {type: mixed | desc: Typed value based on rule declarations.}
