<?php declare(strict_types=1);

namespace Skim\Log;

/**
 * Rotating file log handler with daily file suffix and automatic cleanup.
 *
 * Use as the default log backend when no external service (Sentry, Slack)
 * is configured. Creates date-suffixed log files and prunes files older
 * than $days on every write.
 *
 * Example:
 *   // config/app.php: 'log' => ['channel' => 'file', 'path' => storagePath('logs/app.log')]
 *   // Produces: storage/logs/app-2024-01-15.log
 *
 * Testing: Inject null_handler via Log::setHandler() to skip file I/O.
 *
 * #AI:class
 */
final class FileHandler implements \Skim\Log\LogHandler {
    private static array $levelOrder = [
        'debug' => 0, 'info' => 1, 'notice' => 2, 'warning' => 3,
        'error' => 4, 'critical' => 5, 'alert' => 6, 'emergency' => 7,
    ];

    private int $minLevel;

    public function __construct(
        private readonly string $path,
        private readonly string $level = 'debug',
        private readonly int    $days  = 14,
    ) {
        $this->minLevel = self::$levelOrder[$level] ?? 0;
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Appends a log line if the level meets the minimum threshold. #AI:write
     *
     * Format: [2024-01-15 14:23:01] ERROR: message {"context":"value"}
     * Triggers rotation after each write to prune files older than $days.
     *
     * @param string $level   RFC 5424 level string.
     * @param string $message Log message.
     * @param array  $context Arbitrary metadata, JSON-encoded in output.
     */
    public function write(string $level, string $message, array $context): void {
        if ((self::$levelOrder[$level] ?? 0) < $this->minLevel) {
            return;
        }

        $ctxStr  = $context !== [] ? ' ' . json_encode($context) : '';
        $line     = sprintf(
            "[%s] %s: %s%s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $ctxStr,
        );

        @file_put_contents($this->logFile(), $line, \FILE_APPEND | \LOCK_EX);
        $this->rotate();
    }

    private function logFile(): string {
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

#AI:class
#AI symbol: Skim\Log\FileHandler
#AI source_path: src/Log/FileHandler.php
#AI title: FileHandler
#AI description: Rotating file log handler with daily suffix and automatic old-file cleanup.
#AI role: file log handler
#AI layer: log
#AI badges: [handler; log; file; rotating]
#AI intro: `FileHandler` writes log entries to date-suffixed files and automatically prunes files older than the configured retention period. It is the default log backend when no external service is configured.
#AI lifecycle: created by Log::resolveHandler(); lives for the process duration
#AI test_seam: inject NullHandler via Log::setHandler() to skip file I/O
#AI invariants: [Entries below minLevel are silently dropped; Log directory is auto-created; Rotation runs after every write]
#AI core_behaviors: [Filters by minimum log level; Appends formatted lines to date-suffixed files; Prunes old files beyond retention window]
#AI owns: log files on disk
#AI entry_points: [write]
#AI config_reads: [app.log.path; app.log.level; app.log.days]
#AI non_goals: [Does not support structured JSON logging; Does not send to external services; Does not compress old files]
#AI side_effects: [Appends to log files; Deletes files older than retention window; Creates log directory if missing]
#AI flow: Log::write() -> FileHandler::write() -> level check -> format -> append to file -> rotate()
#AI section_order: [Contract Implementation]

#AI:write
#AI group: Contract Implementation
#AI frequency: high
#AI signature: public function write(string $level, string $message, array $context): void
#AI contract: Appends a formatted log line to the date-suffixed file when the level meets the minimum threshold. Triggers rotation after each write.
#AI param_details: [{name: $level | type: string | required: true | desc: RFC 5424 severity level string.}; {name: $message | type: string | required: true | desc: Log message.}; {name: $context | type: array | required: true | desc: Arbitrary metadata, JSON-encoded after the message.}]
#AI side_effects: [Appends to log file on disk; May delete old log files during rotation]
