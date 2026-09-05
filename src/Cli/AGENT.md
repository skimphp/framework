# src/Cli — Agent Contract

## What this module does
CLI framework for the `php skim <command>` entry point.
Commands extend the base `Command` class and implement `handle(): int`.

## Architecture
- `bin/skim` — Slim bootstrap file that parses arguments and delegates to the kernel.
- `\Skim\Cli\Kernel` — Central dispatcher. Owns the command registry, group definitions, help listing, execution timing, error handling, and dispatch loop.
- `\Skim\Cli\ArgvParser` — Pure value object for parsing `$argv`. Resolves leading/trailing flags and colon-based sub-commands.
- `\Skim\Cli\InteractiveMenu` — Full-screen TUI menu with keyboard navigation and search, displayed automatically when no command is provided in a TTY environment.
- `\Skim\Cli\Cli` — Facade for terminal output, formatting, ASCII banners, and TTY detection.

## Critical behaviours
- ANSI colors and TUI menus are emitted only when `Cli::isTty()` is true (checks `stream_isatty` with fallback to `posix_isatty`), making it safe for CI and piped output.
- `Cli::error()` writes to STDERR — correct for shell scripting and CI log separation.
- `handle()` return code: 0 = success, 1+ = error — `exit()` uses this code.
- Flags parsed: `--flag=value` → string, `--flag` → bool true. Flags can appear before or after the command name.
- Sub-commands: `migrate:down` injects `down` as `arg[0]`; command resolves from base `migrate` via `Kernel::resolve()`.

## Command registration
Register custom commands in `config/app.php` under the `commands` key:
    'commands' => ['my:command' => \App\Cli\MyCommand::class]

## Writing a command
```php
namespace App\Cli;
use Skim\Cli\Command;

class MyCommand extends Command {
    // Optionally override default metadata (name, description, group, usage) in configureForName() or constructor.

    public function handle(): int {
        $name = $this->arg(0, 'world');
        $this->success("Hello, {$name}!");
        return 0; // POSIX success
    }
}
```

## Common mistakes
- Using `echo`/`print` in commands instead of `Cli::info()`/`Cli::success()` — bypasses TTY detection and stderr routing.
- Calling `exit()` inside `handle()` — use the return code instead; the kernel handles process exit cleanly.
- Forgetting to return an `int` from `handle()` — causes implicit 0 even on failure.
