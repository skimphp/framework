<?php declare(strict_types=1);

namespace skim\db;

// Sentinel object for explicitly setting a column to SQL NULL via %set%.
// Plain PHP null in %set% is silently skipped (partial update pattern).
// This distinguishes "don't touch this field" from "set this field to NULL".
//
// Usage:  'avatar' => db::null()   → SET avatar = NULL
// vs:     'avatar' => null         → column skipped entirely
final class null_marker {
    private function __construct() {}

    public static function make(): self {
        return new self();
    }
}
