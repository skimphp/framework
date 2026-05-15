<?php declare(strict_types=1);

namespace skim\log;

// Rotating file log handler. Creates a new file every $days days via date suffix.
// Format: [2024-01-15 14:23:01] ERROR: message {"context":"value"}
final class file_handler implements log_handler {
    private static array $level_order = [
        'debug' => 0, 'info' => 1, 'notice' => 2, 'warning' => 3,
        'error' => 4, 'critical' => 5, 'alert' => 6, 'emergency' => 7,
    ];

    private int $min_level;

    public function __construct(
        private readonly string $path,
        private readonly string $level = 'debug',
        private readonly int    $days  = 14,
    ) {
        $this->min_level = self::$level_order[$level] ?? 0;
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    public function write(string $level, string $message, array $context): void {
        if ((self::$level_order[$level] ?? 0) < $this->min_level) {
            return;
        }

        $ctx_str  = $context !== [] ? ' ' . json_encode($context) : '';
        $line     = sprintf(
            "[%s] %s: %s%s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $ctx_str,
        );

        @file_put_contents($this->log_file(), $line, \FILE_APPEND | \LOCK_EX);
        $this->rotate();
    }

    private function log_file(): string {
        $info = pathinfo($this->path);
        $date = date('Y-m-d');
        return $info['dirname'] . '/' . $info['filename'] . '-' . $date . '.' . ($info['extension'] ?? 'log');
    }

    private function rotate(): void {
        $info    = pathinfo($this->path);
        $pattern = $info['dirname'] . '/' . $info['filename'] . '-*.log';
        $files   = glob($pattern) ?: [];

        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));

        foreach (array_slice($files, $this->days) as $old) {
            @unlink($old);
        }
    }
}
