<?php declare(strict_types=1);

namespace Skim\Db;

/**
 * Executes migration payloads (SQL or callables) with optional dry-run collection.
 *
 * Extracted from Migrator for isolated testing and pretend mode support.
 *
 * #AI:class
 * #AI source_path: src/Db/MigrationExecutor.php
 */
final class MigrationExecutor {
    private bool $pretend = false;
    private array $collected = [];

    public function pretend(bool $value = true): static {
        $this->pretend = $value;
        return $this;
    }

    public function collected(): array {
        return $this->collected;
    }

    public function run(string|array|callable $payload, string $connection = 'default'): void {
        $steps = match (true) {
            is_string($payload) => [$payload],
            is_array($payload) => $payload,
            default => [$payload],
        };

        foreach ($steps as $step) {
            if (is_string($step)) {
                $this->runSql(trim($step), $connection);
            } elseif (is_callable($step)) {
                $this->runCallable($step, $connection);
            }
        }
    }

    private function runSql(string $sql, string $connection): void {
        if ($sql === '') return;
        if ($this->pretend) {
            $this->collected[] = $sql;
            return;
        }
        Db::query($sql, [], false, $connection);
    }

    private function runCallable(callable $fn, string $connection): void {
        if ($this->pretend) {
            $this->collected[] = 'callable: ' . $this->callableName($fn);
            return;
        }
        $fn(Db::pdo($connection));
    }

    private function callableName(callable $fn): string {
        if ($fn instanceof \Closure) {
            $ref = new \ReflectionFunction($fn);
            return 'Closure@' . basename($ref->getFileName() ?: '') . ':' . $ref->getStartLine();
        }
        if (is_array($fn)) {
            return (is_object($fn[0]) ? get_class($fn[0]) : $fn[0]) . '::' . $fn[1];
        }
        return (string) $fn;
    }
}
