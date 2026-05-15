<?php declare(strict_types=1);

namespace skim\db;

// Immutable result object returned by model::paginate().
// Clone-with syntax (PHP 8.5) can create modified copies if needed.
final class pagination {
    public function __construct(
        public readonly array $items,
        public readonly int   $total,
        public readonly int   $per_page,
        public readonly int   $current,
    ) {}

    public int $pages {
        get => (int) ceil($this->total / max(1, $this->per_page));
    }

    public bool $has_next {
        get => $this->current < $this->pages;
    }

    public bool $has_prev {
        get => $this->current > 1;
    }
}
