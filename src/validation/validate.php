<?php declare(strict_types=1);

namespace skim\validation;

// Validation engine. Own implementation, zero external dependencies.
// validate::make() declares the expected data shape.
// check() runs rules, returns result with errors() and validated().
//
// Fields not in the make() map are silently dropped from validated() —
// prevents mass-assignment of unexpected POST fields into model::create().
class validate {
    private static array $custom_rules = [];

    private array $rules;

    public function __construct(array $rules) {
        $this->rules = $rules;
    }

    /**
     * @ai-contract factory — declares field→rules map, returns validator instance
     */
    public static function make(array $rules): static {
        return new static($rules);
    }

    /**
     * @ai-contract runs all rules against $data, returns result
     * @ai-contract result::ok() is false if any field failed any rule
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
     * @ai-contract registers a custom rule globally — available in all validate::make() calls
     * @ai-contract $callback returns null on pass, string error message on fail
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
