<?php declare(strict_types=1);

namespace Skim\Log;

/**
 * Contract for log message writers consumed by the log facade.
 *
 * Use when implementing a custom log destination (Slack, Sentry, database).
 * The log facade resolves one handler and delegates every write() call to it.
 *
 * Example:
 *   class SentryHandler implements LogHandler {
 *       public function write(string $level, string $message, array $context): void {
 *           \Sentry\captureMessage("[$level] $message");
 *       }
 *   }
 *   Log::setHandler(new SentryHandler());
 *
 * #AI:class
 */
interface LogHandler {
    /**
     * Persists a single log entry at the given level. #AI:write
     *
     * @param string $level   RFC 5424 level: debug, info, notice, warning, error, critical, alert, emergency.
     * @param string $message Interpolated log message.
     * @param array  $context Arbitrary key-value metadata attached to the entry.
     */
    public function write(string $level, string $message, array $context): void;
}

#AI:class
#AI symbol: Skim\Log\LogHandler
#AI source_path: src/Log/LogHandler.php
#AI title: LogHandler
#AI description: Interface contract for log message writers used by the log facade.
#AI role: log handler interface
#AI layer: log
#AI badges: [interface; log; contract]
#AI intro: `LogHandler` is the single-method interface that every log backend must implement. The `log` facade holds one handler instance and delegates all level-specific calls to its `write()` method.
#AI lifecycle: instantiated once by Log::resolveHandler() or injected via Log::setHandler()
#AI test_seam: Log::setHandler(), Log::reset()
#AI invariants: [write() must not throw; implementations should handle their own errors]
#AI core_behaviors: [Receives level, message, and context for every log entry]
#AI owns: nothing
#AI entry_points: [write]
#AI config_reads: []
#AI non_goals: [Does not filter by level — the handler decides internally; Does not format output]
#AI side_effects: []
#AI section_order: [Contract]

#AI:write
#AI group: Contract
#AI frequency: high
#AI signature: public function write(string $level, string $message, array $context): void
#AI contract: Persists one log entry. Implementations decide storage format, destination, and error handling.
#AI param_details: [{name: $level | type: string | required: true | desc: RFC 5424 severity level string.}; {name: $message | type: string | required: true | desc: Pre-interpolated log message.}; {name: $context | type: array | required: true | desc: Arbitrary metadata key-value pairs.}]
