<?php declare(strict_types=1);

namespace skim\log;

use skim\dev\profiler;

/**
 * Static log facade with level-specific methods and lazy handler resolution.
 *
 * Use for all application logging. The handler is resolved on first write
 * from config(app.log.channel) and reused for the process lifetime. Every
 * write also records in profiler::log for the debug toolbar.
 *
 * Example:
 *   log::error('Payment failed', ['order_id' => $id, 'reason' => $e->getMessage()]);
 *   log::info('User logged in', ['user_id' => $user->id]);
 *
 * Testing: Use set_handler() to inject a spy, reset() in tearDown().
 *
 * #AI:class
 */
final class log {
    private static ?log_handler $handler = null;

    /**
     * Logs at debug level. #AI:debug
     *
     * @param string $msg Log message.
     * @param array  $ctx Arbitrary context metadata.
     */
    public static function debug(string $msg, array $ctx = []): void   { self::write('debug',   $msg, $ctx); }

    /**
     * Logs at info level. #AI:info
     *
     * @param string $msg Log message.
     * @param array  $ctx Arbitrary context metadata.
     */
    public static function info(string $msg, array $ctx = []): void    { self::write('info',    $msg, $ctx); }

    /**
     * Logs at notice level. #AI:notice
     *
     * @param string $msg Log message.
     * @param array  $ctx Arbitrary context metadata.
     */
    public static function notice(string $msg, array $ctx = []): void  { self::write('notice',  $msg, $ctx); }

    /**
     * Logs at warning level. #AI:warning
     *
     * @param string $msg Log message.
     * @param array  $ctx Arbitrary context metadata.
     */
    public static function warning(string $msg, array $ctx = []): void { self::write('warning', $msg, $ctx); }

    /**
     * Logs at error level. #AI:error
     *
     * @param string $msg Log message.
     * @param array  $ctx Arbitrary context metadata.
     */
    public static function error(string $msg, array $ctx = []): void   { self::write('error',   $msg, $ctx); }

    /**
     * Logs at critical level. #AI:critical
     *
     * @param string $msg Log message.
     * @param array  $ctx Arbitrary context metadata.
     */
    public static function critical(string $msg, array $ctx = []): void{ self::write('critical',$msg, $ctx); }

    /**
     * Logs at alert level. #AI:alert
     *
     * @param string $msg Log message.
     * @param array  $ctx Arbitrary context metadata.
     */
    public static function alert(string $msg, array $ctx = []): void   { self::write('alert',   $msg, $ctx); }

    /**
     * Logs at emergency level. #AI:emergency
     *
     * @param string $msg Log message.
     * @param array  $ctx Arbitrary context metadata.
     */
    public static function emergency(string $msg, array $ctx = []): void { self::write('emergency', $msg, $ctx); }

    /**
     * Injects a custom handler, replacing the default. #AI:set_handler
     *
     * Use in tests to inject a spy or in production to swap in Monolog.
     *
     * Example:
     *   log::set_handler(new null_handler());
     *
     * @param log_handler $handler Custom handler implementation.
     */
    public static function set_handler(log_handler $handler): void {
        self::$handler = $handler;
    }

    /**
     * Clears the cached handler, forcing re-resolution on next write. #AI:reset
     *
     * Use in test tearDown() after set_handler() to restore default behavior.
     */
    public static function reset(): void {
        self::$handler = null;
    }

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

#AI:class
#AI symbol: skim\log\log
#AI source_path: src/log/log.php
#AI title: log
#AI description: Static log facade with RFC 5424 levels, lazy handler resolution, and profiler integration.
#AI role: static log facade
#AI layer: log
#AI badges: [facade; log; profiler; lazy]
#AI intro: `log` is the static entry point for all application logging. It resolves the configured handler on first write and records every entry in the profiler for the debug toolbar.
#AI lifecycle: static facade, handler resolved on first log call
#AI fallback: null_handler when channel is unrecognized
#AI test_seam: set_handler(), reset()
#AI invariants: [handler is resolved once and reused until reset() or set_handler(); every write records in profiler::log; unknown channels fall back to null_handler]
#AI core_behaviors: [Eight level-specific methods delegate to write(); write() captures caller file/line via backtrace; profiler::log receives every entry]
#AI owns: handler instance cache
#AI entry_points: [debug; info; notice; warning; error; critical; alert; emergency]
#AI config_reads: [app.log.channel; app.log.path; app.log.level; app.log.days]
#AI non_goals: [Does not support channels or named loggers; Does not format messages beyond sprintf; Does not send to external services directly]
#AI side_effects: [profiler::log records every entry; set_handler() replaces active handler; reset() forces re-resolution]
#AI flow: log::level() -> write() -> profiler::log() -> handler()->write()
#AI lifecycle_steps: [log::error() / info() / etc.; -> write(); -> debug_backtrace for caller; -> profiler::log(); -> handler(); -> resolve_handler() if null; -> handler->write()]
#AI section_order: [Log Levels; Testing Hooks; Architecture]

#AI:debug
#AI group: Log Levels
#AI frequency: high
#AI signature: public static function debug(string $msg, array $ctx = []): void
#AI contract: Logs a message at debug level. Records in profiler and delegates to the active handler.
#AI param_details: [{name: $msg | type: string | required: true | desc: Log message.}; {name: $ctx | type: array | required: false | desc: Arbitrary context metadata.}]

#AI:info
#AI group: Log Levels
#AI frequency: high
#AI signature: public static function info(string $msg, array $ctx = []): void
#AI contract: Logs a message at info level. Records in profiler and delegates to the active handler.
#AI param_details: [{name: $msg | type: string | required: true | desc: Log message.}; {name: $ctx | type: array | required: false | desc: Arbitrary context metadata.}]

#AI:notice
#AI group: Log Levels
#AI frequency: medium
#AI signature: public static function notice(string $msg, array $ctx = []): void
#AI contract: Logs a message at notice level.
#AI param_details: [{name: $msg | type: string | required: true | desc: Log message.}; {name: $ctx | type: array | required: false | desc: Arbitrary context metadata.}]

#AI:warning
#AI group: Log Levels
#AI frequency: medium
#AI signature: public static function warning(string $msg, array $ctx = []): void
#AI contract: Logs a message at warning level.
#AI param_details: [{name: $msg | type: string | required: true | desc: Log message.}; {name: $ctx | type: array | required: false | desc: Arbitrary context metadata.}]

#AI:error
#AI group: Log Levels
#AI frequency: high
#AI signature: public static function error(string $msg, array $ctx = []): void
#AI contract: Logs a message at error level. Use for recoverable failures that need attention.
#AI param_details: [{name: $msg | type: string | required: true | desc: Log message.}; {name: $ctx | type: array | required: false | desc: Arbitrary context metadata.}]

#AI:critical
#AI group: Log Levels
#AI frequency: low
#AI signature: public static function critical(string $msg, array $ctx = []): void
#AI contract: Logs a message at critical level. Use for application-level failures.
#AI param_details: [{name: $msg | type: string | required: true | desc: Log message.}; {name: $ctx | type: array | required: false | desc: Arbitrary context metadata.}]

#AI:alert
#AI group: Log Levels
#AI frequency: low
#AI signature: public static function alert(string $msg, array $ctx = []): void
#AI contract: Logs a message at alert level. Use for conditions requiring immediate action.
#AI param_details: [{name: $msg | type: string | required: true | desc: Log message.}; {name: $ctx | type: array | required: false | desc: Arbitrary context metadata.}]

#AI:emergency
#AI group: Log Levels
#AI frequency: low
#AI signature: public static function emergency(string $msg, array $ctx = []): void
#AI contract: Logs a message at emergency level. Use for system-wide failures.
#AI param_details: [{name: $msg | type: string | required: true | desc: Log message.}; {name: $ctx | type: array | required: false | desc: Arbitrary context metadata.}]

#AI:set_handler
#AI group: Testing Hooks
#AI frequency: low
#AI signature: public static function set_handler(log_handler $handler): void
#AI contract: Replaces the active handler. Use in tests or to swap in Monolog for production channels.
#AI param_details: [{name: $handler | type: log_handler | required: true | desc: Custom handler implementation.}]
#AI side_effects: [Mutates static handler state]

#AI:reset
#AI group: Testing Hooks
#AI frequency: low
#AI signature: public static function reset(): void
#AI contract: Clears the cached handler. The next log call resolves the handler again from config.
#AI side_effects: [Clears static handler state]
