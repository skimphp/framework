<?php declare(strict_types=1);

namespace Skim\Cli;

/**
 * Pure value object produced by parsing $argv into command, args, and flags.
 *
 * Use when the CLI kernel needs structured access to raw command-line tokens.
 * Strips argv[0] (script name) automatically. Supports --flag=value, --flag,
 * -f short flags, and positional args. For colon-commands like `migrate:down`,
 * the sub-part is prepended to args so commands can detect it via arg(0).
 *
 * Example:
 *   $parsed = ArgvParser::parse(['skim', 'migrate:down', '--steps=2']);
 *   // $parsed->command === 'migrate:down', $parsed->args === ['down'], $parsed->flags === ['steps' => '2']
 *
 * Testing: Pure value object with no side-effects — instantiate directly in tests.
 *
 * #AI:class
 */
final class ArgvParser {

    public readonly string $command;
    public readonly array  $args;
    public readonly array  $flags;

    private function __construct(string $command, array $args, array $flags) {
        $this->command = $command;
        $this->args    = $args;
        $this->flags   = $flags;
    }

    /**
     * Parses the raw $argv array into a structured value object. #AI:parse
     *
     * Strips argv[0] automatically. For colon-commands like `migrate:down`,
     * the sub-part ('down') is prepended to args so the command class
     * can detect it via arg(0) without extra wiring.
     *
     * Example:
     *   $parsed = ArgvParser::parse(['skim', 'cache:clear', 'user:', '--force']);
     *   // command='cache:clear', args=['clear','user:'], flags=['force'=>true]
     *
     * @param array $argv Raw $argv as received by PHP (argv[0] is script name).
     */
    public static function parse(array $argv): self {
        $tokens  = array_slice($argv, 1);
        $args    = [];
        $flags   = [];

        $commandRaw = 'help';
        $foundCmd   = false;

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
            } elseif (!$foundCmd) {
                $commandRaw = $token;
                $foundCmd   = true;
            } else {
                $args[] = $token;
            }
        }

        if (str_contains($commandRaw, ':')) {
            $sub = substr($commandRaw, strpos($commandRaw, ':') + 1);
            if (empty($args) || $args[0] !== $sub) {
                array_unshift($args, $sub);
            }
        }

        return new self($commandRaw, $args, $flags);
    }

    /**
     * Returns true when any of the given flag names is set. #AI:hasFlag
     *
     * @param string ...$names Flag names to test (without leading dashes).
     */
    public function hasFlag(string ...$names): bool {
        foreach ($names as $name) {
            if (isset($this->flags[$name])) {
                return true;
            }
        }
        return false;
    }
}

#AI:class
#AI symbol: Skim\Cli\ArgvParser
#AI source_path: src/Cli/ArgvParser.php
#AI title: ArgvParser
#AI description: Pure value object that parses $argv into command name, positional args, and flags.
#AI role: CLI argument parser
#AI layer: cli
#AI badges: [value-object; cli; parser; immutable]
#AI intro: `ArgvParser` is a pure, immutable value object that transforms the raw PHP `$argv` array into structured command, args, and flags. It handles `--flag=value`, `--flag`, `-f` short flags, and positional arguments. Colon-commands like `migrate:down` automatically inject the sub-part as the first positional arg.
#AI lifecycle: instantiated once per CLI invocation via parse()
#AI fallback: defaults to 'help' command when no command token is found
#AI test_seam: pure value object — instantiate directly in tests with known $argv arrays
#AI invariants: [argv[0] is always stripped; first non-flag token becomes the command; colon-commands inject sub-part into args[0]; flags may appear before or after the command token]
#AI core_behaviors: [Parses --flag=value into flags['flag']='value'; Parses --flag into flags['flag']=true; Parses -f into flags['f']=true; All remaining non-flag tokens after the command become positional args]
#AI owns: parsed command, args, and flags
#AI entry_points: [parse; hasFlag]
#AI config_reads: []
#AI non_goals: [Does not validate command names against a registry; Does not handle quoted strings with spaces; Does not support --flag value (space-separated) syntax]
#AI side_effects: []
#AI flow: ArgvParser::parse($argv) -> strip argv[0] -> tokenize flags/args/command -> inject colon sub-part -> return immutable value object
#AI lifecycle_steps: [ArgvParser::parse($argv); -> strip argv[0]; -> iterate tokens; -> classify as flag/command/arg; -> inject colon sub-part; -> return new self(...)]
#AI section_order: [Parsing; Flag Access]
#AI architectural_notes: Pure value object with no I/O or side-effects. Safe to test directly without mocks.

#AI:parse
#AI group: Parsing
#AI frequency: high
#AI signature: public static function parse(array $argv): self
#AI contract: Parses the raw $argv array into a structured value object. Strips argv[0], classifies tokens as flags, command, or positional args, and injects the colon sub-part for commands like `migrate:down`.
#AI param_details: [{name: $argv | type: array | required: true | desc: Raw $argv as received by PHP. argv[0] is the script name and is stripped automatically.}]
#AI return_detail: {type: self | desc: Immutable value object with command, args, and flags properties.}

#AI:hasFlag
#AI group: Flag Access
#AI frequency: medium
#AI signature: public function hasFlag(string ...$names): bool
#AI contract: Returns true when any of the given flag names is set in the parsed flags array. Accepts variadic names for convenience (e.g. checking both 'quiet' and 'q').
#AI param_details: [{name: $names | type: string | required: true | desc: Flag names to test, without leading dashes. Variadic — pass one or more names.}]
#AI return_detail: {type: bool | desc: True if at least one of the given flag names is present.}
