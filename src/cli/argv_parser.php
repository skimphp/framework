<?php declare(strict_types=1);

namespace skim\cli;

/**
 * Pure value object produced by parsing $argv.
 * No side-effects, no I/O — safe to test directly.
 */
final class argv_parser {

    public readonly string $command;
    public readonly array  $args;
    public readonly array  $flags;

    private function __construct(string $command, array $args, array $flags) {
        $this->command = $command;
        $this->args    = $args;
        $this->flags   = $flags;
    }

    /**
     * Parse the raw $argv array (as received by PHP).
     * Strips the script name (argv[0]) automatically.
     *
     * Supports:
     *   --flag=value   → flags['flag'] = 'value'
     *   --flag         → flags['flag'] = true
     *   -f             → flags['f']    = true
     *   positional     → args[]
     *
     * Subcommand arg injection: for colon-commands like `migrate:down`,
     * the sub-part ('down') is prepended to args so the command class
     * can detect it via arg(0) without extra wiring in the entrypoint.
     */
    public static function parse(array $argv): self {
        $tokens  = array_slice($argv, 1);
        $args    = [];
        $flags   = [];

        // First pass: collect all flags and find the command name.
        // Flags may appear before OR after the command token.
        $command_raw = 'help';
        $found_cmd   = false;

        foreach ($tokens as $token) {
            if (str_starts_with($token, '--')) {
                $raw = substr($token, 2);
                if (str_contains($raw, '=')) {
                    [$k, $v] = explode('=', $raw, 2);
                    $flags[$k] = $v;
                } else {
                    $flags[$raw] = true;
                }
            } elseif (str_starts_with($token, '-') && strlen($token) > 1) {
                $flags[substr($token, 1)] = true;
            } elseif (!$found_cmd) {
                // First non-flag token is the command name
                $command_raw = $token;
                $found_cmd   = true;
            } else {
                $args[] = $token;
            }
        }

        // Inject sub-command as first positional arg so command classes
        // can read arg(0) without knowing the full name.
        // e.g. "migrate:down" → command="migrate:down", args[0]="down"
        if (str_contains($command_raw, ':')) {
            $sub = substr($command_raw, strpos($command_raw, ':') + 1);
            if (empty($args) || $args[0] !== $sub) {
                array_unshift($args, $sub);
            }
        }

        return new self($command_raw, $args, $flags);
    }

    /** Convenience: check if a flag is set (boolean or with value). */
    public function has_flag(string ...$names): bool {
        foreach ($names as $name) {
            if (isset($this->flags[$name])) {
                return true;
            }
        }
        return false;
    }
}
