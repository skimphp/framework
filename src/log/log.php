<?php declare(strict_types=1);

namespace skim\log;

use skim\dev\profiler;

// PSR-3 compatible logger — no external dependency for basic file logging.
// Monolog can be dropped in via set_handler() for advanced channels (Slack, Sentry, etc).
//
// Log levels follow RFC 5424: debug < info < notice < warning < error < critical < alert < emergency.
// Lifecycle: file rotated every $days days (configurable in config/app.php).
// Integration: profiler::log() called on every write — appears in debug toolbar log panel.
final class log {
    private static ?log_handler $handler = null;

    public static function debug(string $msg, array $ctx = []): void   { self::write('debug',   $msg, $ctx); }
    public static function info(string $msg, array $ctx = []): void    { self::write('info',    $msg, $ctx); }
    public static function notice(string $msg, array $ctx = []): void  { self::write('notice',  $msg, $ctx); }
    public static function warning(string $msg, array $ctx = []): void { self::write('warning', $msg, $ctx); }
    public static function error(string $msg, array $ctx = []): void   { self::write('error',   $msg, $ctx); }
    public static function critical(string $msg, array $ctx = []): void{ self::write('critical',$msg, $ctx); }
    public static function alert(string $msg, array $ctx = []): void   { self::write('alert',   $msg, $ctx); }
    public static function emergency(string $msg, array $ctx = []): void { self::write('emergency', $msg, $ctx); }

    /**
     * @ai-contract injects a custom handler (Monolog, test spy, etc.)
     * @ai-contract replaces the default file handler
     */
    public static function set_handler(log_handler $handler): void {
        self::$handler = $handler;
    }

    /**
     * @ai-contract for tests — reset to null handler (logs discarded)
     */
    public static function reset(): void {
        self::$handler = null;
    }

    // --- internals ---

    private static function write(string $level, string $msg, array $ctx): void {
        $caller = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2] ?? [];
        $file   = $caller['file'] ?? '';
        $line   = $caller['line'] ?? 0;

        profiler::log($level, $msg, $ctx, $file, $line);

        self::handler()->write($level, $msg, $ctx);
    }

    private static function handler(): log_handler {
        return self::$handler ??= self::resolve_handler();
    }

    private static function resolve_handler(): log_handler {
        $channel = \skim\core\config::get('app.log.channel', 'file');
        return match ($channel) {
            'file'  => new file_handler(
                path:  (string) \skim\core\config::get('app.log.path', storage_path('logs/app.log')),
                level: (string) \skim\core\config::get('app.log.level', 'debug'),
                days:  (int) \skim\core\config::get('app.log.days', 14),
            ),
            'null'  => new null_handler(),
            default => new null_handler(),
        };
    }
}
