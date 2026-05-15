# src/cli — Agent Contract

## What this module does
CLI framework for `php skim <command>` entry point.
Commands extend base command, implement handle(): int.

## Critical behaviours
- ANSI colors emitted only when posix_isatty(STDOUT) — safe for CI/piped output
- cli::error() writes to STDERR — correct for shell scripting and CI log separation
- handle() return code: 0 = success, 1+ = error — exit() in bin/skim uses this
- Flags parsed: --flag=value → string, --flag → bool true
- Sub-commands: 'migrate:down' injects 'down' as arg[0]; command resolves from base 'migrate'

## Command registration
Register in config/app.php under 'commands' key:
    'commands' => ['my:command' => app\cli\my_command::class]

## Writing a command
```php
class my_command extends command {
    public function handle(): int {
        $name = $this->arg(0, 'world');
        $this->success("Hello, {$name}!");
        return 0;
    }
}
```

## Common mistakes
- echo/print in commands instead of cli::info()/success() — bypasses TTY detection and stderr routing
- Calling exit() inside handle() — use return code instead; bin/skim calls exit() with the returned code
- Forgetting to return an int from handle() — causes implicit 0 even on failure
