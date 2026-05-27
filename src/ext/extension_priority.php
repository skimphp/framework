<?php declare(strict_types=1);

namespace skim\ext;

/**
 * Named extension priority bands.
 */
final class extension_priority {
    public const CORE = 10;
    public const OFFICIAL = 50;
    public const PAID = 80;
    public const USER = 100;
}
