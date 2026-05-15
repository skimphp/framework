<?php declare(strict_types=1);

namespace skim\validation;

// Immutable validation result returned by validate::check().
// ok() / errors() / validated() are the only three calls controllers need.
final class result {
    public function __construct(
        private readonly array $errors,      // ['field' => ['message', ...]]
        private readonly array $validated,   // only declared fields, values cast
    ) {}

    /**
     * @ai-contract returns true when no validation errors were found
     */
    public function ok(): bool {
        return $this->errors === [];
    }

    /**
     * @ai-contract returns field → [messages] map — matches 422 API response shape
     */
    public function errors(): array {
        return $this->errors;
    }

    /**
     * @ai-contract returns only declared fields — safe to pass to model::create()
     * @ai-contract undeclared POST fields are silently dropped (mass-assignment safe)
     */
    public function validated(): array {
        return $this->validated;
    }
}
