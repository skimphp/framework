<?php declare(strict_types=1);

namespace skim\log;

interface log_handler {
    public function write(string $level, string $message, array $context): void;
}
