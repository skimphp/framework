# src/cli — Agent Contract

## What this module does
CLI framework for the `php skim <command>` entry point.
Commands extend the base `command` class and implement `handle(): int`.

## Architecture
- `bin/skim` — Slim bootstrap file that parses arguments and delegates to the kernel.
- `\skim\cli\kernel` — Central dispatcher. Owns the command registry, group definitions, help listing, execution timing, error handling, and dispatch loop.
- `\Skim\cli\ArgvParser` — Pure value object for parsing `$argv`. Resolves leading/trailing flags and colon-based sub-commands.
- `\Skim\cli\InteractiveMenu` — Full-screen TUI menu with keyboard navigation and search, displayed automatically when no command is provided in a TTY environment.
- `\skim\cli\cli` — Facade for terminal output, formatting, ASCII banners, and TTY detection.

## Critical behaviours
- ANSI colors and TUI menus are emitted only when `cli::isTty()` is true (checks `stream_isatty` with fallback to `posix_isatty`), making it safe for CI and piped output.
- `cli::error()` writes to STDERR — correct for shell scripting and CI log separation.
- `handle()` return code: 0 = success, 1+ = error — `exit()` uses this code.
- Flags parsed: `--flag=value` → string, `--flag` → bool true. Flags can appear before or after the command name.
- Sub-commands: `migrate:down` injects `down` as `arg[0]`; command resolves from base `migrate` via `kernel::resolve()`.

## Command registration
Register custom commands in `config/app.php` under the `commands` key:
    'commands' => ['my:command' => \app\cli\my_command::class]

## Writing a command
```php
namespace app\cli;
use skim\cli\command;

class my_command extends command {
    // Optionally override default metadata (name, description, group, usage) in configureForName() or constructor.

    public function handle(): int {
        $name = $this->arg(0, 'world');
        $this->success("Hello, {$name}!");
        return 0; // POSIX success
    }
}
```

## Common mistakes
- Using `echo`/`print` in commands instead of `cli::info()`/`cli::success()` — bypasses TTY detection and stderr routing.
- Calling `exit()` inside `handle()` — use the return code instead; the kernel handles process exit cleanly.
- Forgetting to return an `int` from `handle()` — causes implicit 0 even on failure.
