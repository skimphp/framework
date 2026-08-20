<?php declare(strict_types=1);

use skim\db\migration;

/**
 * Migration: {{name}}
 *
 * String  = single statement.
 * Array   = one element per statement.
 * Callable = custom logic receiving \PDO.
 *
 * On MySQL, DDL is auto-committed regardless of $transactional.
 */
return new class extends migration {
    public function up(): string|array|callable {
        return '';
    }
};
