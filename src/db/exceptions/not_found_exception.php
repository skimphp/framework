<?php declare(strict_types=1);

namespace skim\db\exceptions;

// Thrown by model::find_or_fail() when no record matches the given primary key.
// Controllers catch this to return a typed 404 — never catch generic \Exception.
class not_found_exception extends \RuntimeException {
    public function __construct(string $model, int|string $id) {
        parent::__construct("{$model} with id '{$id}' not found.");
    }
}
