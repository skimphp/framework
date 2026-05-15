<?php declare(strict_types=1);

namespace skim\log;

// Discards all log entries — used in tests and when channel = 'null'.
final class null_handler implements log_handler {
    public function write(string $level, string $message, array $context): void {}
}
