<?php declare(strict_types=1);

namespace skim\validation;

/**
 * Zero-dependency validation engine with mass-assignment protection. #AI:class
 *
 * Use to validate request data before passing to model::create(). Fields not
 * declared in make() are silently dropped from validated(), preventing
 * mass-assignment of unexpected POST fields.
 *
 * Example:
 *   $result = validate::make([
 *       'email' => ['required', 'email'],
 *       'age'   => ['required', 'int', 'min:18'],
 *   ])->check($req->post());
 *
 *   if (!$result->ok()) {
 *       return $res->status(422)->json(['errors' => $result->errors()]);
 *   }
 *   user::create($result->validated());
 *
 * Testing: Call make() and check() directly — no container or config needed.
 *
 * #AI:class
 */
class validate {
    private static array $custom_rules = [];

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
     * Runs all rules against $data and returns a result. #AI:check
     *
     * Fields that pass all rules appear in validated(). Fields not declared
     * in make() are silently excluded. Optional fields absent from $data
     * pass validation without error.
     *
     * @param array $data Input data to validate (typically $req->post()).
     */
    public function check(array $data): result {
        $errors    = [];
        $validated = [];

        foreach ($this->rules as $field => $rules) {
            $value     = $data[$field] ?? null;
            $rule_list = is_string($rules) ? explode('|', $rules) : $rules;
            $field_ok  = true;

            foreach ($rule_list as $rule) {
                $error = $this->apply_rule($rule, $field, $value, $data);
                if ($error !== null) {
                    $errors[$field][] = $error;
                    $field_ok         = false;
                }
            }

            // Include field in validated() if it passed, or if not required and absent
            if ($field_ok) {
                $validated[$field] = $value;
            }
        }

        return new result($errors, $validated);
    }

    /**
     * Registers a custom rule globally for all validate::make() calls. #AI:rule
     *
     * The callback receives the field value and returns null on pass or
     * a string error message on fail. Use `:field` in the message as a
     * placeholder for the field name.
     *
     * Example:
     *   validate::rule('even', fn($v) => $v % 2 === 0, ':field must be even');
     *
     * @param string   $name     Rule name used in rule lists.
     * @param callable $callback Receives value, returns null or error string.
     * @param string   $message  Default error message (`:field` is replaced).
     */
    public static function rule(string $name, callable $callback, string $message = 'Invalid value'): void {
        self::$custom_rules[$name] = ['fn' => $callback, 'message' => $message];
    }

    // --- rule evaluation ---

    private function apply_rule(string $rule_str, string $field, mixed $value, array $data): ?string {
        [$rule, $param] = array_pad(explode(':', $rule_str, 2), 2, null);

        // Skip optional fields when value absent (empty/null) — unless 'required'
        if ($rule !== 'required' && ($value === null || $value === '')) {
            return null;
        }

        return match ($rule) {
            'required'  => ($value === null || $value === '') ? "The {$field} field is required." : null,
            'email'     => !\skim\helpers\filter::email($value) ? "The {$field} must be a valid email." : null,
            'url'       => !\skim\helpers\filter::url($value) ? "The {$field} must be a valid URL." : null,
            'int'       => !\skim\helpers\filter::int($value) ? "The {$field} must be an integer." : null,
            'float'     => !\skim\helpers\filter::float($value) ? "The {$field} must be a number." : null,
            'bool'      => \skim\helpers\filter::bool($value) === false ? "The {$field} must be a boolean." : null,
            'slug'      => !\skim\helpers\filter::slug($value) ? "The {$field} must be a valid slug." : null,
            'min'       => (is_numeric($value) && (float)$value < (float)$param) ? "The {$field} must be at least {$param}." : null,
            'max'       => (is_numeric($value) && (float)$value > (float)$param) ? "The {$field} must not exceed {$param}." : null,
            'min_len'   => (mb_strlen((string)$value) < (int)$param) ? "The {$field} must be at least {$param} characters." : null,
            'max_len'   => (mb_strlen((string)$value) > (int)$param) ? "The {$field} must not exceed {$param} characters." : null,
            'in'        => !in_array($value, explode(',', (string)$param), true) ? "The {$field} must be one of: {$param}." : null,
            'regex'     => !preg_match((string)$param, (string)$value) ? "The {$field} format is invalid." : null,
            'same'      => ($value !== ($data[$param] ?? null)) ? "The {$field} must match {$param}." : null,
            default     => $this->apply_custom($rule, $field, $value),
        };
    }

    private function apply_custom(string $rule, string $field, mixed $value): ?string {
        if (!isset(self::$custom_rules[$rule])) {
            return null;   // unknown rules silently pass — prevents accidental lockouts
        }
        $entry = self::$custom_rules[$rule];
        if (!(bool)($entry['fn'])($value)) {
            return str_replace(':field', $field, $entry['message']);
        }
        return null;
    }
}

#AI:class
#AI symbol: skim\validation\validate
#AI source_path: src/validation/validate.php
#AI title: validate
#AI description: Zero-dependency validation engine with built-in rules, custom rule registration, and mass-assignment protection.
#AI role: validation engine
#AI layer: validation
#AI badges: [validation; engine; zero-deps; mass-assignment-safe]
#AI intro: `validate` is SKIM's built-in validation engine. It declares expected data shapes via `make()`, runs rules via `check()`, and returns a `result` with errors and validated data. Undeclared fields are silently dropped from `validated()`.
#AI lifecycle: instantiated per-validation via make(), check() runs synchronously
#AI fallback: n/a — own implementation
#AI test_seam: call make() and check() directly, register custom rules via rule()
#AI invariants: [Fields not in make() are excluded from validated(); Optional fields absent from data pass without error; Unknown custom rules silently pass; Custom rules are registered globally and persist across instances]
#AI core_behaviors: [Built-in rules: required, email, url, int, float, bool, slug, min, max, min_len, max_len, in, regex, same; Custom rules via rule() with callback; Pipe-delimited or array rule syntax]
#AI warnings: [Custom rules registered via rule() are global and persist for the process lifetime; Unknown rule names silently pass — typos in rule names go undetected]
#AI notes: Rules accept both array syntax `['required', 'email']` and pipe syntax `'required|email'`. The `:field` placeholder in custom rule messages is replaced with the actual field name.
#AI owns: custom_rules static registry
#AI entry_points: [make; check; rule]
#AI config_reads: []
#AI non_goals: [Does not sanitize input; Does not handle file upload validation; Does not provide localized error messages]
#AI side_effects: [rule() mutates the static custom_rules registry]
#AI flow: validate::make($rules) -> check($data) -> apply_rule() per field per rule -> new result($errors, $validated)
#AI lifecycle_steps: [validate::make([...]); -> check($req->post()); -> foreach field -> foreach rule -> apply_rule(); -> new result(errors, validated); -> controller branches on ok()]
#AI section_order: [Validation API; Custom Rules; Architecture]
#AI architectural_notes: Own implementation with zero external dependencies. Uses skim\helpers\filter for type checking. Custom rules are global — register once during boot.

#AI:make
#AI group: Validation API
#AI frequency: high
#AI signature: public static function make(array $rules): static
#AI contract: Factory that declares the field-to-rules map and returns a validator instance.
#AI param_details: [{name: $rules | type: array | required: true | desc: Map of field name to rule array or pipe-delimited string.}]
#AI return_detail: {type: static | desc: Validator instance ready for check().}

#AI:check
#AI group: Validation API
#AI frequency: high
#AI signature: public function check(array $data): result
#AI contract: Runs all declared rules against $data. Returns a result where ok() is false if any field failed any rule. Only declared fields appear in validated().
#AI param_details: [{name: $data | type: array | required: true | desc: Input data to validate, typically $req->post().}]
#AI return_detail: {type: result | desc: Immutable result with errors() and validated().}

#AI:rule
#AI group: Custom Rules
#AI frequency: low
#AI signature: public static function rule(string $name, callable $callback, string $message = 'Invalid value'): void
#AI contract: Registers a custom rule globally. The callback receives the field value and returns null on pass or a string error message on fail.
#AI param_details: [{name: $name | type: string | required: true | desc: Rule name used in rule lists.}; {name: $callback | type: callable | required: true | desc: Receives value, returns null on pass or error string on fail.}; {name: $message | type: string | required: false | desc: Default error message. Use :field as placeholder for field name.}]
#AI side_effects: [Mutates the static custom_rules registry]
#AI warnings: [Custom rules are global and persist for the process lifetime]
