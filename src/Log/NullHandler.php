<?php declare(strict_types=1);

namespace Skim\Log;

/**
 * Log handler that silently discards all entries.
 *
 * Use as the default handler in tests or when config sets log channel to 'null'.
 * Satisfies the log_handler contract without side effects, so Profiler::log
 * still records entries in the debug toolbar even when file output is disabled.
 *
 * Example:
 *   Log::setHandler(new NullHandler());
 *   Log::error('this goes nowhere'); // profiler still sees it
 *
 * #AI:class
 */
final class NullHandler implements \Skim\Log\LogHandler {
    /**
     * Accepts and discards the log entry. #AI:write
     *
     * @param string $level   RFC 5424 level string.
     * @param string $message Log message.
     * @param array  $context Arbitrary metadata.
     */
    public function write(string $level, string $message, array $context): void {}
}

#AI:class
#AI symbol: Skim\Log\NullHandler
#AI source_path: src/Log/NullHandler.php
#AI title: NullHandler
#AI description: Log handler that discards all entries — used in tests and null-channel config.
#AI role: null log handler
#AI layer: log
#AI badges: [handler; log; null-object; testing]
#AI intro: `NullHandler` implements `LogHandler` by discarding every write. It is the fallback when no file handler is configured and the default in test environments.
#AI lifecycle: stateless, no resources opened
#AI test_seam: inject via Log::setHandler()
#AI invariants: [write() never throws; write() produces no output]
#AI core_behaviors: [Accepts all log levels and discards them silently]
#AI owns: nothing
#AI entry_points: [write]
#AI config_reads: []
#AI non_goals: [Does not persist log data; Does not filter by level]
#AI side_effects: []
#AI section_order: [Contract Implementation]

#AI:write
#AI group: Contract Implementation
#AI frequency: high
#AI signature: public function write(string $level, string $message, array $context): void
#AI contract: Accepts the log entry and discards it. No I/O, no exceptions.
#AI param_details: [{name: $level | type: string | required: true | desc: RFC 5424 severity level string.}; {name: $message | type: string | required: true | desc: Log message.}; {name: $context | type: array | required: true | desc: Arbitrary metadata.}]
