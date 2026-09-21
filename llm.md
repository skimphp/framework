# LLM context

> Auto-generated from source annotations. Do not edit manually.

## `Skim\Assets\Assets` — assets

> Vite asset integration — resolves hashed URLs from manifest.json and generates script/style tags with HMR support.

`vite` `assets` `hmr` `manifest`

**Source:** `src/Assets/Assets.php` · **Layer:** `assets` · **Lifecycle:** `static facade, manifest loaded and cached on first production url() call`

`assets` resolves asset paths to Vite-built URLs. In dev mode (APP_DEBUG=true), it proxies through Vite's HMR dev server at localhost:5173. In production, it reads the Vite manifest.json to return cache-busted hashed filenames.

### Core Behavior
- Manifest is loaded lazily on first production url() call and cached
- url() output is HTML-escaped via e() in js()/css() tags

### Warnings
- ⚠ Production throws RuntimeException if manifest.json is missing — run npm run build first

### Asset Resolution

#### `url(path): string`
Returns the URL for a given asset path. In dev mode, returns the Vite HMR proxy URL. In production, returns the hashed filename from manifest.json.
- `$path: string` (required) — Asset path relative to Vite project root.
- **Returns** `string` — Resolved asset URL.
- **Throws** `\RuntimeException` — In production when asset is not found in manifest.

### Tag Generation

#### `js(path): string`
Returns a <script type="module"> tag. In dev mode, also injects the Vite HMR client script.
- `$path: string` (required) — JS entrypoint path.
- **Returns** `string` — HTML script tag(s).

#### `css(path): string`
Returns a <link rel="stylesheet"> tag. In dev mode, returns empty string because Vite injects CSS via HMR JS.
- `$path: string` (required) — CSS file path.
- **Returns** `string` — HTML link tag or empty string in dev mode.

### Testing Hooks

#### `setViteUrl(url): void`
Overrides the Vite dev server URL. Default is http://localhost:5173.
- `$url: string` (required) — Vite dev server base URL.
- **Side effect:** Mutates static state.

#### `setManifest(manifest): void`
Injects a manifest array directly, bypassing file-based loading. Use in tests.
- `$manifest: array` (required) — Fake Vite manifest.
- **Side effect:** Mutates static manifest cache.

#### `reset(): void`
Clears the cached manifest. Forces re-read from disk on the next url() call.
- **Side effect:** Clears static manifest cache.

## `Skim\Cache\ArrayDriver` — ArrayDriver

> In-memory cache driver for tests with process-scoped storage and lazy TTL expiry.

`driver` `cache` `in-memory` `test-only`

**Source:** `src/Cache/ArrayDriver.php` · **Layer:** `cache` · **Lifecycle:** `process-scoped, resets naturally between requests`

`Skim\Cache\ArrayDriver` stores cache entries in a PHP array. Values exist only for the current process and are never persisted. TTL is enforced via microtime expiry checked lazily on read. This is the default driver for Pest/PHPUnit tests.

### Warnings
- ⚠ Not suitable for production — state diverges across PHP-FPM workers

### Read API

#### `get(key, default = null): mixed`
Returns the cached value or $default if missing/expired. Delegates to has() for expiry check.
- `$key: string` (required) — Cache key to read.
- `$default: mixed` (optional) — Fallback returned on miss.
- **Returns** `mixed` — The cached value or $default.

#### `has(key): bool`
Returns true when the key exists and has not expired. Lazily removes expired keys.
- `$key: string` (required) — Cache key to test.
- **Returns** `bool` — True if key exists and is valid.

### Write API

#### `set(key, value, ttl = null): bool`
Stores value with optional TTL. Null TTL means no expiry. Always returns true.
- `$key: string` (required) — Cache key to write.
- `$value: mixed` (required) — Payload to persist.
- `$ttl: ?int` (optional) — TTL in seconds, or null for no expiry.
- **Returns** `bool` — Always true.

#### `delete(key): bool`
Removes a single key. No-ops if absent. Always returns true.
- `$key: string` (required) — Exact cache key to remove.
- **Returns** `bool` — Always true.

### Invalidation

#### `flush(prefix): bool`
Removes all keys whose name starts with the given prefix.
- `$prefix: string` (required) — Key prefix to match.
- **Returns** `bool` — Always true.

#### `flushAll(): bool`
Clears the entire in-memory store.
- **Returns** `bool` — Always true.
- ⚠ Destroys all cached data in this driver instance

### Lifecycle

#### `__construct()`
Throws if constructed inside a worker process. ArrayDriver stores state in a PHP array, so it leaks across requests in FrankenPHP worker mode.
- **Throws** `\RuntimeException` — When WORKER_MODE is defined and true.
- ⚠ Use FileDriver or RedisDriver in worker mode instead

## `Skim\Cache\Cache` — cache

> Static cache facade for configured drivers, fail-soft fallback, tag support, and test driver injection.

`facade` `cache` `driver-backed` `fail-soft`

**Source:** `src/Cache/Cache.php` · **Layer:** `cache` · **Lifecycle:** `static facade, driver resolved on first cache call`

Static entry point for cache operations. Resolves the configured driver on first use and reuses it for the process lifetime. Falls back to a secondary driver when the primary cannot be created.

### Core Behavior
- The facade resolves one configured driver and exposes a single cache API
- Cache reads, writes, misses, and invalidation are recorded in profiler
- Redis tags are available only when active driver is RedisDriver

### Drivers
| Driver | Mutable | Description |
|---|---|---|
| array | yes | In-memory cache driver. Values exist only for the current process and are not persisted. |
| file | yes | Filesystem-backed cache driver. Values are persisted on disk. |
| redis | yes | Redis-backed cache driver. Required for tag-scoped cache operations through Cache::tags(). |

### Warnings
- ⚠ flushAll() clears the entire active cache backend
- ⚠ flush() requires a non-empty prefix
- ⚠ Prefer prefix-based invalidation such as flush('user:') in production

### Read API

#### `remember(key, ttl, default): mixed`
Returns the cached value when the key exists. On a miss, executes the callback, stores the returned value with the provided TTL, records the miss in profiler, and returns the computed value.
- `$key: string` (required) — Cache key. Use stable prefixes such as user:42 or posts:published so related entries can be invalidated by prefix.
- `$ttl: int` (required) — Time to live in seconds for the computed value.
- `$default: callable` (required) — Callback executed only when the key is missing or expired.
- **Returns** `mixed` — The cached or newly computed value.
- **Side effect:** Writes to cache backend only when key is missing or expired.
- **Note:** Use remember() for expensive reads when recomputing the value on cache miss is safe and deterministic.

#### `get(key, default = null): mixed`
Returns the cached value for the key. If the key is missing or expired, returns $default. Each read records a profiler cache event with hit or miss status.
- `$key: string` (required) — Cache key to read.
- `$default: mixed` (optional) — Fallback value returned when the key is missing or expired.
- **Returns** `mixed` — The cached value or $default.

#### `has(key): bool`
Returns true when the active driver contains a non-expired value for the key.
- `$key: string` (required) — Cache key to test.
- **Returns** `bool` — True if key exists and is valid.

### Write API

#### `set(key, value, ttl = null): bool`
Stores a value in the active driver. When $ttl is null, the facade uses cache.ttl from config and falls back to 3600 seconds when that value is not set.
- `$key: string` (required) — Cache key to write.
- `$value: mixed` (required) — Value to store. Must be supported by the active driver.
- `$ttl: ?int` (optional) — Optional TTL in seconds. null means use the configured default TTL.
- **Returns** `bool` — True if backend confirmed successful write.
- **Side effect:** Writes payload to the active cache backend.

#### `delete(key): bool`
Removes one exact cache key from the active driver and records the delete operation in profiler.
- `$key: string` (required) — Exact cache key to remove.
- **Returns** `bool` — True if backend confirmed deletion.
- **Side effect:** Mutates cache backend by removing key.

### Invalidation

#### `flush(prefix): bool`
Removes keys that match the given prefix. The prefix is required — callers who want a full cache wipe must use flushAll().
- `$prefix: string` (required) — Key prefix to match. Use a non-empty prefix like user: for safer invalidation.
- **Returns** `bool` — True if backend confirmed successful invalidation.
- ⚠ Prefix is required. Trying to flush with an empty string triggers a PHP ArgumentCountError — use flushAll() instead.
- **Side effect:** Mass deletion in cache backend for matching keys.

#### `flushAll(): bool`
Unconditionally wipes the entire cache backend by delegating to the driver's flushAll(). Records the operation in profiler.
- **Returns** `bool` — True if backend confirmed flush.
- ⚠ Destroys all cached data across all application contexts sharing this driver
- ⚠ Triggers immediate re-computation on next read
- ⚠ Prefer flush('prefix:') in production
- **Side effect:** Mass deletion of all entries in cache backend.

### Tag Operations

#### `tags(tags): TaggedRedisDriver`
Returns a Redis tag-scoped cache proxy for the given tags. Available only when the active driver is RedisDriver.
- `$tags: array` (required) — Tag identifiers used to scope subsequent cache operations.
- **Returns** `TaggedRedisDriver` — Tag-scoped cache proxy.
- **Throws** `RuntimeException` — Thrown when the active driver is not RedisDriver.

### Testing Hooks

#### `setDriver(driver): void`
Replaces the active driver instance directly. Use in tests to bypass config-based resolution and external services.
- `$driver: driver` (required) — Driver implementation used for subsequent cache calls.
- **Side effect:** Mutates static driver state.

#### `reset(): void`
Clears the cached driver instance. The next cache call resolves the driver again from config.
- **Side effect:** Clears static driver state.

### Architecture

#### `driver(): driver`
Returns the cached driver instance, resolving and caching it lazily if null.
- **Note:** Private for internal subsystem access.

#### `resolveDriver(): driver`
Builds the primary driver from config, falls back to the configured secondary on exception.

#### `makeDriver(name): driver`
Maps string config names to concrete driver instances (redis, file, array).
- `$name: string` (required) — Driver name from config (redis, file, array).
- **Throws** `InvalidArgumentException` — If driver name is unsupported.

#### `driverName(): string`
Returns the configured cache.driver value for profiler metadata. May differ from the actual active driver after setDriver() or fallback.

## `Skim\Cache\FileDriver` — FileDriver

> Filesystem cache driver storing each key as a serialized file with TTL-based expiry.

`driver` `cache` `filesystem` `fail-soft`

**Source:** `src/Cache/FileDriver.php` · **Layer:** `cache` · **Lifecycle:** `persistent across requests, TTL enforced on read`

`Skim\Cache\FileDriver` persists cache entries as individual files on disk. Each file contains a serialized tuple of [expiry_timestamp, value]. Keys are base64-encoded for safe filenames. TTL is tracked via the serialized expiry, not file mtime. The driver falls back gracefully when the directory is not writable.

### Warnings
- ⚠ flushAll() deletes every .cache file in the configured path
- ⚠ Not suitable for high-throughput production workloads — prefer Redis

### Read API

#### `get(key, default = null): mixed`
Reads the cache file, unserializes the payload, checks expiry, and returns the value or $default. Deletes expired files on read.
- `$key: string` (required) — Cache key to read.
- `$default: mixed` (optional) — Fallback returned on miss or expiry.
- **Returns** `mixed` — The cached value or $default.

#### `has(key): bool`
Delegates to get() with a sentinel default to detect misses and expiry.
- `$key: string` (required) — Cache key to test.
- **Returns** `bool` — True if key exists and is valid.

### Write API

#### `set(key, value, ttl = null): bool`
Serializes [expiry, value] and writes atomically with LOCK_EX. Returns false when the directory is not writable.
- `$key: string` (required) — Cache key to write.
- `$value: mixed` (required) — Payload to persist.
- `$ttl: ?int` (optional) — TTL in seconds, or null for no expiry.
- **Returns** `bool` — True if file was written successfully.

#### `delete(key): bool`
Deletes the cache file for the key. Returns true if the file was absent or successfully unlinked.
- `$key: string` (required) — Exact cache key to remove.
- **Returns** `bool` — True if file is gone.

### Invalidation

#### `flush(prefix): bool`
Scans the cache directory for .cache files, decodes filenames to keys, and deletes those matching the prefix.
- `$prefix: string` (required) — Key prefix to match.
- **Returns** `bool` — Always true.

#### `flushAll(): bool`
Deletes every .cache file in the configured cache directory.
- **Returns** `bool` — Always true.
- ⚠ Deletes every .cache file in the configured path unconditionally

### Methods

#### `__construct(path)`

## `Skim\Cache\RedisDriver` — RedisDriver

> Redis cache driver using php-redis extension with lazy connection, SCAN-based flush, and tag support.

`driver` `cache` `redis` `lazy-connection` `tag-support`

**Source:** `src/Cache/RedisDriver.php` · **Layer:** `cache` · **Lifecycle:** `lazy connection on first operation, reused for process lifetime`

`Skim\Cache\RedisDriver` is the production cache backend. It uses the php-redis extension (not Predis) for 5-10x better performance. Connection is lazy — the socket opens on first operation. Prefix-based flush uses SCAN to avoid blocking Redis. Tags are supported via Redis sets through `tags()`.

### Warnings
- ⚠ flushAll() issues FLUSHDB which destroys ALL data in the selected Redis database, not just cache keys
- ⚠ Prefer flush('prefix:') in production

### Read API

#### `get(key, default = null): mixed`
Reads the prefixed key from Redis, unserializes the value, and returns it or $default on miss/deserialization failure.
- `$key: string` (required) — Cache key to read.
- `$default: mixed` (optional) — Fallback returned on miss.
- **Returns** `mixed` — The cached value or $default.

#### `has(key): bool`
Checks key existence via Redis EXISTS command.
- `$key: string` (required) — Cache key to test.
- **Returns** `bool` — True if key exists.

### Write API

#### `set(key, value, ttl = null): bool`
Serializes the value and writes to Redis using SETEX (with TTL) or SET (without TTL).
- `$key: string` (required) — Cache key to write.
- `$value: mixed` (required) — Payload to persist.
- `$ttl: ?int` (optional) — TTL in seconds, or null for no expiry.
- **Returns** `bool` — True if Redis confirmed the write.

#### `delete(key): bool`
Deletes the prefixed key via Redis DEL command.
- `$key: string` (required) — Exact cache key to remove.
- **Returns** `bool` — True if Redis confirmed deletion.

### Invalidation

#### `flush(prefix): bool`
Uses SCAN to find matching keys in batches of 100, then DEL to remove them. Avoids KEYS which blocks Redis.
- `$prefix: string` (required) — Key prefix to match.
- **Returns** `bool` — Always true.

#### `flushAll(): bool`
Issues FLUSHDB on the selected Redis database, destroying all data in that database.
- **Returns** `bool` — True if Redis confirmed the flush.
- ⚠ FLUSHDB destroys ALL data in the selected database, not just cache keys
- ⚠ Prefer flush('prefix:') in production

### Tag Operations

#### `tags(tags): TaggedRedisDriver`
Returns a tag-scoped proxy that tracks key membership in Redis sets and supports grouped invalidation.
- `$tags: array` (required) — Tag identifiers for grouped operations.
- **Returns** `TaggedRedisDriver` — Tag-scoped cache proxy.

### Methods

#### `__construct(host, port, password, database, prefix)`

## `Skim\Cache\TaggedRedisDriver` — TaggedRedisDriver

> Tag-scoped proxy over RedisDriver for grouped cache invalidation via Redis sets.

`proxy` `cache` `redis` `tags` `bulk-invalidation`

**Source:** `src/Cache/TaggedRedisDriver.php` · **Layer:** `cache` · **Lifecycle:** `created per Cache::tags() call, no persistent state beyond Redis sets`

`Skim\Cache\TaggedRedisDriver` is a proxy returned by `Cache::tags()`. It wraps the RedisDriver and adds tag membership tracking via Redis sets. On `set()`, the key is SADD'd to each tag's set. On `flush()`, all member keys are deleted along with the tag sets themselves.

### Warnings
- ⚠ flush() is destructive — removes all keys associated with any constructor tag
- ⚠ Requires live Redis connection

### Read API

#### `get(key, default = null): mixed`
Delegates directly to the underlying RedisDriver. Tags do not affect reads.
- `$key: string` (required) — Cache key to read.
- `$default: mixed` (optional) — Fallback returned on miss.
- **Returns** `mixed` — The cached value or $default.

### Write API

#### `set(key, value, ttl = null): bool`
Adds the prefixed key to each tag's Redis set via SADD, then delegates the write to the underlying RedisDriver.
- `$key: string` (required) — Cache key to write.
- `$value: mixed` (required) — Payload to persist.
- `$ttl: ?int` (optional) — TTL in seconds, or null for no expiry.
- **Returns** `bool` — True if backend confirmed successful write.
- **Side effect:** Adds key to Redis tag sets via SADD

### Invalidation

#### `flush(): bool`
For each constructor tag, reads all member keys from the Redis set via SMEMBERS, deletes them, then deletes the tag set itself.
- **Returns** `bool` — Always true.
- ⚠ Destructive — removes all keys associated with any constructor tag
- ⚠ Deletes the tag sets themselves
- **Side effect:** Mass deletion of tagged keys in Redis
- **Side effect:** Removes tag sets

### Methods

#### `__construct(driver, redis, prefix, tags)`

## `Skim\Cli\ArgvParser` — ArgvParser

> Pure value object that parses $argv into command name, positional args, and flags.

`value-object` `cli` `parser` `immutable`

**Source:** `src/Cli/ArgvParser.php` · **Layer:** `cli` · **Lifecycle:** `instantiated once per CLI invocation via parse()`

`ArgvParser` is a pure, immutable value object that transforms the raw PHP `$argv` array into structured command, args, and flags. It handles `--flag=value`, `--flag`, `-f` short flags, and positional arguments. Colon-commands like `migrate:down` automatically inject the sub-part as the first positional arg.

### Core Behavior
- Parses --flag=value into flags['flag']='value'
- Parses --flag into flags['flag']=true
- Parses -f into flags['f']=true
- All remaining non-flag tokens after the command become positional args

### Parsing

#### `parse(argv): self`
Parses the raw $argv array into a structured value object. Strips argv[0], classifies tokens as flags, command, or positional args, and injects the colon sub-part for commands like `migrate:down`.
- `$argv: array` (required) — Raw $argv as received by PHP. argv[0] is the script name and is stripped automatically.
- **Returns** `self` — Immutable value object with command, args, and flags properties.

### Flag Access

#### `hasFlag(names): bool`
Returns true when any of the given flag names is set in the parsed flags array. Accepts variadic names for convenience (e.g. checking both 'quiet' and 'q').
- `$names: string` (required) — Flag names to test, without leading dashes. Variadic — pass one or more names.
- **Returns** `bool` — True if at least one of the given flag names is present.

## `Skim\Cli\Cli` — cli

> ANSI-colored CLI output helper with TTY detection, interactive prompts, tables, and styled error boxes.

`cli` `output` `ansi` `tty-aware` `interactive`

**Source:** `src/Cli/Cli.php` · **Layer:** `cli` · **Lifecycle:** `static utility — no instantiation needed, methods called directly`

`cli` is the central terminal output helper for the SKIM CLI. It provides colored output methods, interactive prompts (ask, confirm, choice), ASCII tables, progress bars, and styled error boxes. All ANSI escape codes are suppressed when stdout is not a TTY or when forcePlain(true) is set.

### Core Behavior
- TTY detection via stream_isatty/posix_isatty
- Colored output with automatic reset
- Interactive prompts read from STDIN
- ASCII tables with auto-fitted column widths
- Error boxes with Unicode or plain borders

### Output

#### `line(msg = ''): void`
Prints a line to stdout followed by a newline. Empty string prints a blank line.
- `$msg: string` (optional) — Text to print. Defaults to empty string for blank lines.

#### `info(msg): void`
Prints a cyan informational message to stdout.
- `$msg: string` (required) — Informational message text.

#### `success(msg): void`
Prints a green success message with checkmark prefix to stdout.
- `$msg: string` (required) — Success message text.

#### `warn(msg): void`
Prints a yellow warning message with warning prefix to stdout.
- `$msg: string` (required) — Warning message text.

#### `error(msg): void`
Prints a red error message to STDERR with cross prefix.
- `$msg: string` (required) — Error message text.
- **Side effect:** Writes to STDERR, not STDOUT.

#### `muted(msg): void`
Prints a gray dimmed message for secondary information.
- `$msg: string` (required) — Muted text.

#### `bold(msg): void`
Prints bold text to stdout.
- `$msg: string` (required) — Bold text.

#### `newline(n = 1): void`
Prints one or more blank lines.
- `$n: int` (optional) — Number of blank lines to print.

### Interactive Prompts

#### `ask(question, default = ''): string`
Prompts user for text input via STDIN. Shows $default in brackets and returns it when user presses Enter without typing.
- `$question: string` (required) — Prompt text displayed to the user.
- `$default: string` (optional) — Value returned when user presses Enter without typing.
- **Returns** `string` — Trimmed user input, or $default if empty.

#### `confirm(question, default = true): bool`
Prompts user with y/n question. Returns true for y/yes, false for n/no. $default determines what Enter alone returns.
- `$question: string` (required) — Prompt text.
- `$default: bool` (optional) — Value returned when user presses Enter without typing.
- **Returns** `bool` — True for yes, false for no.

#### `choice(question, options, default = null): mixed`
Presents a numbered list of options and returns the selected item. Re-prompts on invalid input until a valid selection is made.
- `$question: string` (required) — Prompt heading displayed above the options.
- `$options: array` (required) — Indexed array of selectable items.
- `$default: mixed` (optional) — Returned when user presses Enter without typing.
- **Returns** `mixed` — The selected option value, or $default.

### Progress and Tables

#### `progressBar(total = 0, label = ''): ProgressBar`
Creates a progress bar instance. Pass total=0 for indeterminate spinner mode.
- `$total: int` (optional) — Total steps. 0 for indeterminate spinner mode.
- `$label: string` (optional) — Text displayed alongside the bar.
- **Returns** `ProgressBar` — Progress bar instance to call advance() and finish() on.

#### `table(headers, rows): void`
Renders rows as an ASCII table with auto-fitted column widths.
- `$headers: array` (required) — Column header labels.
- `$rows: array` (required) — Array of row arrays, values aligned to headers by index.

### Display Components

#### `header(version, php, env, os): void`
Prints the SKIM ASCII logo and environment metadata banner. Loads logo from skim_ascii.txt if present, falls back to built-in art.
- `$version: string` (required) — Framework version string.
- `$php: string` (required) — PHP version string.
- `$env: string` (required) — Application environment name.
- `$os: string` (required) — Operating system identifier.

#### `section(title): void`
Prints an uppercase section heading in dim gray.
- `$title: string` (required) — Section title text.

#### `step(n, total, msg, status = 'running'): void`
Prints a numbered step indicator with colored status icon (running=blue, success=green, error=red).
- `$n: int` (required) — Current step number.
- `$total: int` (required) — Total step count.
- `$msg: string` (required) — Step description.
- `$status: string` (optional) — One of 'running', 'success', 'error'.

#### `errorBox(title, body = ''): void`
Renders a boxed error message with Unicode box-drawing on TTY or plain ASCII borders otherwise. Truncates lines wider than terminal width.
- `$title: string` (required) — Error title displayed in the box header.
- `$body: string` (optional) — Error body. When empty, $title is used as the body.

#### `didYouMean(input, candidates): void`
Suggests the closest matching command using Levenshtein distance. Only prints when a candidate is within edit distance 3.
- `$input: string` (required) — Mistyped command name.
- `$candidates: array` (required) — List of valid command names to compare against.

#### `duration(start): void`
Prints elapsed time since $start with a green checkmark.
- `$start: float` (required) — microtime(true) value captured at operation start.

#### `divider(char = '─'): void`
Prints a horizontal divider spanning the terminal width. Uses Unicode on TTY, ASCII dash otherwise.
- `$char: string` (optional) — Character to repeat. Defaults to Unicode horizontal line.

### Architecture

#### `forcePlain(plain): void`
Forces plain-text output regardless of TTY detection. Used by --no-ansi flag and in tests.
- `$plain: bool` (required) — True to suppress all ANSI escape codes.
- **Side effect:** Mutates static forcePlain flag.

#### `color(code, text): string`
Wraps text in ANSI color codes when output is a TTY, returns plain text otherwise.

#### `isTty(): bool`
Returns true when stdout is connected to an interactive terminal. Uses stream_isatty with posix_isatty fallback.
- **Note:** Public because other CLI classes (InteractiveMenu, ProgressBar) need TTY detection.

## `Skim\Cli\Command` — command

> Abstract base class for CLI commands with arg/flag access, output helpers, and auto-configured metadata.

`cli` `command` `abstract` `base-class`

**Source:** `src/Cli/Command.php` · **Layer:** `cli` · **Lifecycle:** `instantiated by kernel, setInput() called with parsed argv, then handle() invoked`

`command` is the abstract base class that all SKIM CLI commands extend. It provides positional arg access, flag access, output helper proxies (info, success, warn, error), and auto-configuration of name/group/description/usage from the registered command name.

### Core Behavior
- arg() and flag() provide safe access with defaults
- configureForName() auto-fills metadata from command name
- output helpers delegate to Cli:: static methods

### Metadata Access

#### `getName(): string`
Returns the registered command name as set by configureForName().
- **Returns** `string` — Command name (e.g. 'migrate:down').

#### `getDescription(): string`
Returns the command description for help display.
- **Returns** `string` — Human-readable description.

#### `getGroup(): string`
Returns the command group for categorized help listing.
- **Returns** `string` — Group key (e.g. 'database', 'queue', 'general').

#### `getUsage(): string`
Returns the usage string for help display.
- **Returns** `string` — Usage string (e.g. '[--steps=N]').

### Configuration

#### `configureForName(name): void`
Auto-fills name, group, description, and usage from the registered command name. Only sets values still at defaults — subclass property overrides are preserved.
- `$name: string` (required) — Registered command name (e.g. 'migrate:down').

#### `help(): void`
Prints usage and description to the terminal via Cli:: output helpers.

### Command Execution

#### `handle(): int`
Implements the command logic. Must return a POSIX exit code (0 = success, 1+ = error).
- **Returns** `int` — POSIX exit code. 0 for success, 1+ for error.

### Input Access

#### `setInput(args, flags): void`
Injects parsed argv args and flags. Called by the kernel before handle().
- `$args: array` (required) — Positional arguments after the command name.
- `$flags: array` (required) — Parsed flags (--flag=value or --flag as true).

#### `arg(index, default = null): mixed`
Returns a positional argument by zero-based index, or $default if the index does not exist.
- `$index: int` (required) — Zero-based argument position.
- `$default: mixed` (optional) — Returned when the index does not exist.
- **Returns** `mixed` — The argument value at $index, or $default.

#### `flag(name, default = null): mixed`
Returns a flag value. --flag=val returns 'val', --flag returns true, absent returns $default.
- `$name: string` (required) — Flag name without leading dashes.
- `$default: mixed` (optional) — Returned when the flag is absent.
- **Returns** `mixed` — Flag value, true for boolean flags, or $default.

## `Skim\Cli\Commands\CacheBuildCommand` — CacheBuildCommand

> CLI command that pre-compiles env, config, and extensions into pure PHP array cache files for OPcache.

`cli` `command` `cache` `build` `opcache`

**Source:** `src/Cli/Commands/CacheBuildCommand.php` · **Layer:** `cli` · **Lifecycle:** `instantiated by CLI kernel, handle() called once per invocation`

`CacheBuildCommand` provides the `php skim cache:build` CLI entry point. It generates compiled PHP array files in `storage/config_cache/` for env, config, and extensions, enabling OPcache shared-memory hits with zero parse overhead on subsequent requests.

### Core Behavior
- Resets and reloads env from .env
- Resets and reloads config from config/
- Reads sys.extensions from app container
- Writes var_export() output as PHP return statements

### Warnings
- ⚠ Calls App::instance() which triggers full boot — ensure .env and config/ are present

### Command Execution

#### `handle(): int`
Builds compiled cache files for env, config, and extensions in storage/config_cache/. Resets env and config before loading to ensure fresh state. Creates the cache directory if missing.
- **Returns** `int` — 0 on success.

## `Skim\Cli\Commands\CacheCommand` — CacheCommand

> CLI command for cache invalidation by prefix or full backend flush.

`cli` `command` `cache` `destructive`

**Source:** `src/Cli/Commands/CacheCommand.php` · **Layer:** `cli` · **Lifecycle:** `instantiated by CLI kernel, handle() called once per invocation`

`CacheCommand` provides the `php skim cache:clear` and `php skim cache:flush` CLI entry points. It delegates to `Cache::flush($prefix)` for prefix-scoped invalidation or `Cache::flushAll()` when no prefix is supplied.

### Core Behavior
- Delegates prefix flush to Cache::flush()
- Delegates full flush to Cache::flushAll()
- Prints success or error message to stdout

### Warnings
- ⚠ Running `php skim cache:clear` without a prefix calls Cache::flushAll() which clears the entire cache backend

### Command Execution

#### `handle(): int`
Dispatches clear/flush sub-commands. When a prefix is provided, calls Cache::flush($prefix). When no prefix is given, calls Cache::flushAll() which clears the entire backend.
- **Returns** `int` — 0 on success, 1 on unknown sub-command.
- ⚠ Without a prefix argument
- ⚠ the entire cache backend is cleared — prefer prefix-scoped invalidation in production

## `Skim\Cli\Commands\ExtInstallCommand` — ExtInstallCommand

> CLI command that installs a SKIM extension package with composer, config publishing, and atomic migrations.

`cli` `command` `extension` `installer` `atomic-migrations`

**Source:** `src/Cli/Commands/ExtInstallCommand.php` · **Layer:** `cli` · **Lifecycle:** `instantiated by kernel, handle() called once per invocation`

`ExtInstallCommand` implements the `php skim ext:install` CLI command. It runs the full installation pipeline: preflight DB check, composer require, extension discovery, config publishing, atomic migration execution (with rollback on failure), and post-install hints.

### Core Behavior
- Preflight checks PHP version and DB connectivity
- Composer require runs as subprocess with optional TTY streaming
- Extension discovery via ExtRegistry after composer install
- Config publishing copies to project config dir
- Migrations run atomically with rollback on failure

### Warnings
- ⚠ Migration failure rolls back all migrations applied in this session
- ⚠ composer require modifies vendor/ and composer.json

### Command Execution

#### `handle(): int`
Runs the full extension installation pipeline: resolve package, preflight, composer require, discover, publish config, run migrations atomically, print hints.
- **Returns** `int` — 0 on success, 1 on any failure.
- ⚠ Migration failure triggers rollback of all migrations applied in this session

### Installation Pipeline

#### `resolvePackage(name): string`
Normalizes short names to vendor/package format. Empty input returns empty string.
- `$name: string` (required) — Raw package name from CLI arg.
- **Returns** `string` — Normalized package name or empty string.

#### `preflight(): bool`
Verifies PHP >= 8.5 and database connectivity before proceeding with installation.

#### `composerRequire(package, noInteraction): array`
Runs composer require as a subprocess. Streams output to TTY when interactive. Returns exit code and captured output.
- `$package: string` (required) — Composer package name.
- `$noInteraction: bool` (required) — Suppress interactive prompts.
- **Returns** `array{code:int,output:string}` — Exit code and captured output.

#### `printEnvAdditions(extension): void`
Prints missing .env keys that the extension requires.

#### `printPostInstall(extension): void`
Prints post-install next steps from extension metadata.

### Configuration

#### `publishConfig(extension): ?string`
Copies extension config file to the project config directory. Skips if config already exists.
- `$extension: array` (required) — Extension metadata from registry.
- **Returns** `?string` — Path to published config, or null if no config to publish.

#### `tablePrefix(noInteraction): string`
Resolves table prefix from --prefix flag, non-interactive default, or interactive prompt.

#### `writeTablePrefix(configPath, prefix): void`
Writes the table_prefix value into the published config file.

### Architecture

#### `__construct(registry = null, migrator = null)`
Accepts optional test doubles for extension registry and migration executor. Defaults are created lazily.
- `$registry: ?ExtRegistry` (optional) — Test double for extension registry. Null uses default.
- `$migrator: ?ExtMigrator` (optional) — Test double for migration executor. Null uses default.

#### `envHas(key): bool`
Checks if a key already exists in the .env file.

#### `packageSlug(package): string`
Converts a package name to a filesystem-safe slug by replacing / and - with _.

#### `registry(): ExtRegistry`
Returns the injected or default extension registry.

#### `migrator(): ExtMigrator`
Returns the injected or default extension migrator.

## `Skim\Cli\Commands\ExtListCommand` — ExtListCommand

> CLI command that lists installed SKIM extensions from local Composer metadata with capability and conflict reporting.

`cli` `command` `extension` `read-only`

**Source:** `src/Cli/Commands/ExtListCommand.php` · **Layer:** `cli` · **Lifecycle:** `instantiated by kernel, handle() called once per invocation`

`ExtListCommand` implements the `php skim ext:list` CLI command. It scans local Composer vendor metadata to list installed SKIM extensions, their capabilities, and any inter-extension conflicts. No network access is required.

### Core Behavior
- Lists extensions from ExtRegistry->installed()
- Prints capabilities per extension
- Warns on unknown capabilities via CapabilityVocabulary
- Reports conflicts from ExtRegistry->conflicts()

### Command Execution

#### `handle(): int`
Prints installed extensions with capabilities and conflict warnings. Returns 0 always.
- **Returns** `int` — Always 0.

### Architecture

#### `__construct(registry = null)`
Accepts optional registry test double. Defaults to scanning basePath()/vendor.
- `$registry: ?ExtRegistry` (optional) — Test double for extension registry. Null uses default.

#### `registry(): ExtRegistry`
Returns the injected or default extension registry.

## `Skim\Cli\Commands\ExtManifestCommand` — ExtManifestCommand

> CLI command that generates skim.json manifest from an extension class for distribution.

`cli` `command` `extension` `manifest`

**Source:** `src/Cli/Commands/ExtManifestCommand.php` · **Layer:** `cli` · **Lifecycle:** `instantiated by kernel, handle() called once per invocation`

`ExtManifestCommand` implements the `php skim ext:manifest` CLI command. It takes a fully-qualified extension class name, validates it extends `extension`, and generates `skim.json` at the project root via `ExtManifest::generate()`.

### Core Behavior
- Validates class exists and extends Extension
- Delegates to ExtManifest::generate()
- Writes skim.json to project root

### Command Execution

#### `handle(): int`
Generates skim.json from the given extension class. Validates the argument is a class extending extension, then delegates to ExtManifest::generate().
- **Returns** `int` — 0 on success, 1 on invalid argument.

## `Skim\Cli\Commands\IdeCommand` — IdeCommand

> CLI command that generates .ide-helper.php with typed model property stubs for IDE autocomplete.

`cli` `command` `ide` `code-generation`

**Source:** `src/Cli/Commands/IdeCommand.php` · **Layer:** `cli` · **Lifecycle:** `instantiated by kernel, handle() called once per invocation`

`IdeCommand` implements the `php skim ide:generate` CLI command. It discovers model classes in app/Models, reads their database schema (from cache or DESCRIBE queries), and generates typed property stubs in `.skim/ide-helper.php` for IDE autocomplete.

### Core Behavior
- Discovers models via glob on app/Models/*.php
- Reads column metadata via Model::schema()
- Maps SQL types to PHP scalar types
- Writes typed property stubs to .skim/ide-helper.php

### Command Execution

#### `handle(): int`
Discovers models, reads schema, and writes .ide-helper.php. Only supports the 'generate' sub-command.
- **Returns** `int` — 0 on success, 1 on unknown sub-command.

### Model Discovery

#### `discoverModels(): array`
Scans app/Models for PHP files and returns fully-qualified class names that are loadable.
- **Returns** `array` — Array of fully-qualified model class names.

### Schema Reading

#### `getColumns(class): array`
Reads column metadata from a model's schema() method. Returns empty array on failure.
- `$class: string` (required) — Fully-qualified model class name.
- **Returns** `array` — Array of column metadata with name, type, null keys.

#### `mapType(sqlType): string`
Maps SQL column types to PHP scalar types (int, float, bool, string).
- `$sqlType: string` (required) — Raw SQL type string from DESCRIBE.
- **Returns** `string` — PHP type name (int, float, bool, or string).

### Code Generation

#### `buildOutput(classes): string`
Builds the PHP source for .ide-helper.php from discovered model classes.
- `$classes: array` (required) — Fully-qualified model class names.
- **Returns** `string` — Complete PHP source for the ide-helper file.

#### `stub(fqn, short, cols): string`
Generates a PHP class stub with typed properties for one model.
- `$fqn: string` (required) — Fully-qualified class name.
- `$short: string` (required) — Short class name.
- `$cols: array` (required) — Column metadata with name, type, null keys.
- **Returns** `string` — PHP source for one model stub.

## `Skim\Cli\Commands\InstallCommand` — InstallCommand

> Interactive CLI installer that generates .env from prompts and optionally runs migrations.

`cli` `command` `installer` `interactive` `setup`

**Source:** `src/Cli/Commands/InstallCommand.php` · **Layer:** `cli` · **Lifecycle:** `instantiated by kernel, handle() called once per invocation`

`InstallCommand` implements the `php skim install` CLI command. It provides an interactive wizard that prompts for application, database, cache, and logging configuration, writes the results to `.env`, reloads env/config, and optionally runs migrations.

### Core Behavior
- Interactive prompts via Cli::ask/confirm/choice
- Generates cryptographic APP_KEY
- Writes .env file
- Reloads env and config after writing
- Optionally delegates to MigrateCommand

### Warnings
- ⚠ Overwrites .env when --force is passed or user confirms
- ⚠ DB password is shown in plain text during prompt

### Command Execution

#### `handle(): int`
Runs the interactive installation wizard. Prompts for config, writes .env, reloads env/config, and optionally runs migrations.
- **Returns** `int` — 0 on success or cancellation, 1 on migration failure.
- ⚠ Overwrites .env when --force is passed or user confirms overwrite

### Key Generation

#### `generateKey(): string`
Generates a base64-encoded 32-byte cryptographic key for APP_KEY.
- **Returns** `string` — Key in format 'base64:<encoded>'.

### Environment Building

#### `buildEnv(vars): string`
Formats key-value pairs as .env file content with one KEY=VALUE per line.
- `$vars: array` (required) — Associative array of ENV_KEY => value pairs.
- **Returns** `string` — Formatted .env file content.

## `Skim\Cli\Commands\MigrateCommand` — migrate_command

> CLI dispatcher for database migration operations — run, rollback, fresh, status, and make.

`cli` `command` `database` `migration` `destructive`

**Source:** `src/Cli/Commands/MigrateCommand.php` · **Layer:** `cli` · **Lifecycle:** `instantiated by kernel, handle() called once per invocation`

`MigrateCommand` implements the `php skim migrate` family of CLI commands. It dispatches to the `migrator` class for all SQL operations and handles only CLI output and exit codes.

### Core Behavior
- Dispatches sub-commands via match expression
- Delegates to migrator for all DB operations
- Prints success/warn/info for each migration file

### Warnings
- ⚠ migrate:fresh drops ALL tables and destroys all data — use only in development

### Command Execution

#### `handle(): int`
Dispatches to the appropriate migration sub-command (run, down, fresh, status, make). Defaults to run when no sub-command is given.
- **Returns** `int` — 0 on success.

### Sub-commands

#### `run(mig): int`
Runs all pending migrations via the migrator and prints each applied file.

#### `down(mig): int`
Rolls back the last batch of migrations. Use --steps=N flag to roll back multiple batches.

#### `fresh(mig): int`
Drops all tables and re-runs all migrations from scratch.

#### `status(mig): int`
Displays a table showing each migration's filename, batch number, and applied/pending status.

#### `make(conn): int`
Creates a new Migration file from the stub template.

## `Skim\Cli\Commands\QueueCommand` — QueueCommand

> CLI dispatcher for queue operations — work, status, flush, and restart.

`cli` `command` `queue` `worker` `destructive`

**Source:** `src/Cli/Commands/QueueCommand.php` · **Layer:** `cli` · **Lifecycle:** `instantiated by kernel, handle() called once per invocation`

`QueueCommand` implements the `php skim queue:*` family of CLI commands. It dispatches to work (start worker), status (show pending counts), flush (remove pending jobs), and restart (signal workers to stop) sub-commands.

### Core Behavior
- work starts a long-lived worker with BRPOP polling
- status reads queue sizes from Redis
- flush deletes queue keys
- restart writes a timestamp to Redis

### Warnings
- ⚠ flush destroys all pending jobs in the specified queue
- ⚠ work blocks the terminal until stopped

### Command Execution

#### `handle(): int`
Dispatches to work, status, flush, or restart sub-commands. Defaults to work.
- **Returns** `int` — 0 on success, 1 on unknown sub-command.

### Sub-commands

#### `work(): int`
Starts a long-lived queue worker that blocks on Redis BRPOP. Processes jobs until stopped by signal, max-jobs, or restart signal.

#### `status(): int`
Displays pending job count per queue in an ASCII table.

#### `flush(): int`
Removes all pending jobs from the specified queue.
- ⚠ Destroys all pending jobs in the queue — already-processing jobs are not affected

#### `restart(): int`
Sends a restart signal to all running workers via a Redis timestamp key. Workers stop after completing their current job.

## `Skim\Cli\Commands\ServeCommand` — ServeCommand

> CLI command that starts PHP built-in dev server and optional Vite in parallel.

`cli` `command` `server` `development`

**Source:** `src/Cli/Commands/ServeCommand.php` · **Layer:** `cli` · **Lifecycle:** `instantiated by kernel, handle() blocks until both processes exit`

`ServeCommand` implements the `php skim serve` CLI command. It launches PHP's built-in development server pointed at the public/ directory and, if package.json exists, starts a Vite dev server alongside it using proc_open for parallel process control.

### Core Behavior
- Launches PHP built-in server via proc_open
- Conditionally launches Vite
- Blocks until processes exit
- Ctrl+C kills both via signal propagation

### Command Execution

#### `handle(): int`
Launches PHP dev server and optional Vite in parallel. Blocks until both processes exit.
- **Returns** `int` — Always 0.

## `Skim\Cli\Commands\WorkerInstallCommand` — WorkerInstallCommand

> CLI command that generates FrankenPHP worker mode files.

`cli` `command` `worker` `frankenphp`

**Source:** `src/Cli/Commands/WorkerInstallCommand.php` · **Layer:** `cli`

CLI command that generates FrankenPHP worker mode files. Creates: - public/worker.php - Caddyfile - docker/Dockerfile.frankenphp Example: php skim worker:install

### Methods

#### `handle(): int`
Generates the worker entrypoint, Caddyfile, and Dockerfile. :handle

## `Skim\Cli\Commands\WorkerUninstallCommand` — WorkerUninstallCommand

> CLI command that removes FrankenPHP worker mode files.

`cli` `command` `worker` `frankenphp`

**Source:** `src/Cli/Commands/WorkerUninstallCommand.php` · **Layer:** `cli`

CLI command that removes FrankenPHP worker mode files generated by worker:install. Removes: - public/worker.php - Caddyfile - docker/Dockerfile.frankenphp Example: php skim worker:uninstall

### Methods

#### `handle(): int`
Removes generated worker files. :handle

## `Skim\Cli\InteractiveMenu` — InteractiveMenu

> Full-screen interactive terminal menu for CLI command discovery with keyboard navigation and live search.

`cli` `interactive` `tui` `keyboard-driven` `ansi`

**Source:** `src/Cli/InteractiveMenu.php` · **Layer:** `cli` · **Lifecycle:** `instantiated by kernel with command groups, run() enters event loop, returns selected command or null`

`InteractiveMenu` provides a full-screen, keyboard-driven terminal UI for browsing and selecting CLI commands. It supports collapsible command groups, live search with / or :, and ANSI-styled rendering. Returns null when not on a TTY.

### Core Behavior
- Raw TTY mode for character-by-character input
- Collapsible groups with arrow keys
- Live search with / or : prefix
- ANSI-styled rendering with focus highlighting
- Escape sequences decoded to named keys

### Menu Execution

#### `run(): ?string`
Runs the interactive menu loop. Sets up raw TTY mode, renders frames, reads key input, and returns the selected command name or null on quit.
- **Returns** `?string` — Selected command name, or null if user quit.

### Key Handling

#### `handleKey(key): void`
Dispatches a decoded key press to navigation (up/down/left/right/enter) or search logic.
- `$key: string` (required) — Decoded key name (up, down, left, right, enter, esc, backspace, or single character).

#### `readKey(): string`
Reads raw key bytes from STDIN with a 100ms timeout using stream_select.
- **Returns** `string` — Raw bytes from STDIN, or empty string on timeout.

#### `decodeKey(raw): string`
Decodes raw terminal escape sequences into named key identifiers (up, down, left, right, enter, esc, backspace).
- `$raw: string` (required) — Raw bytes from STDIN.
- **Returns** `string` — Named key identifier or the raw character.

### Rendering

#### `clearMenu(): void`
Moves cursor up over the rendered menu and erases it using ANSI escape sequences.

#### `rebuildFlatItems(): void`
Rebuilds the flat item list from groups. In search mode, filters by query. In browse mode, respects group expansion state.

#### `render(): void`
Renders the full menu to stdout, overwriting the previous frame with ANSI cursor control.

### TTY Management

#### `setupTty(): void`
Saves current TTY settings and puts terminal into raw mode for character-by-character input. Hides cursor.

#### `restoreTty(): void`
Restores original TTY settings and shows the cursor. Falls back to stty echo icanon if saved settings are unavailable.

### Architecture

#### `isTty(): bool`
Returns true when stdout is connected to an interactive terminal.

### Methods

#### `__construct(groups)`

## `Skim\Cli\Kernel` — kernel

> CLI kernel — central dispatcher owning command registry, help rendering, dispatch, timing, and error output.

`cli` `kernel` `dispatcher` `command-registry`

**Source:** `src/Cli/Kernel.php` · **Layer:** `cli` · **Lifecycle:** `instantiated once per CLI invocation in bin/skim, run() called with parsed argv`

`kernel` is the central dispatcher for the SKIM CLI. It owns the built-in command registry, merges user-defined commands from config, resolves command names, prints the header banner, dispatches commands with timing and error handling, and provides interactive or static help listings.

### Core Behavior
- Resolves command names with colon-prefix fallback
- Merges built-in and user commands
- Prints header banner unless --quiet
- Catches exceptions and renders error boxes
- Shows duration on TTY
- Interactive TUI help on TTY, static listing otherwise
- Agent mode prints compact tab-separated help and errors

### Dispatch

#### `run(input): int`
Runs the CLI. Handles --version/--quiet/--no-ansi/--agent flags, resolves the command, dispatches it, or shows help for unknown/help/list commands.
- `$input: ArgvParser` (required) — Parsed CLI input from ArgvParser::parse().
- **Returns** `int` — POSIX exit code from the dispatched command.

#### `dispatch(class, commandName, input): int`
Instantiates, configures, and dispatches a resolved command class. Prints header, records timing, catches exceptions.
- `$class: string` (required) — FQCN of the command class.
- `$commandName: string` (required) — Registered command name.
- `$input: ArgvParser` (required) — Parsed CLI input.
- **Returns** `int` — Exit code from command handle(), or 1 on exception.

### Command Resolution

#### `resolve(name): ?string`
Resolves a command name to its FQCN. Supports direct match and colon-prefix fallback.
- `$name: string` (required) — Command name from argv.
- **Returns** `?string` — FQCN of the command class, or null if not registered.

#### `allCommands(): array`
Merges built-in COMMANDS with user-defined commands from config/app.php.
- **Returns** `array` — Merged command registry (name => FQCN).

### Help Display

#### `showHelp(): ?string`
Shows interactive TUI help on TTY or static command listing otherwise. Returns selected command name for re-dispatch or null to exit.
- **Returns** `?string` — Selected command name for re-dispatch, or null to exit.

#### `buildGroups(): array`
Builds ordered command groups from all registered commands, sorted by GROUP_ORDER.
- **Returns** `array` — Array of group records with label and commands keys.

### Architecture

#### `printHeader(): void`
Prints the SKIM header banner with logo and environment metadata via Cli::header().

## `Skim\Cli\ProgressBar` — ProgressBar

> Terminal progress bar with TTY-aware rendering, percentage display, and braille spinner for unknown totals.

`cli` `progress` `tty-aware` `spinner`

**Source:** `src/Cli/ProgressBar.php` · **Layer:** `cli` · **Lifecycle:** `instantiated with total and label, advance() called per step, finish() completes the bar`

`ProgressBar` provides a terminal progress indicator that renders a filled bar with percentage when total is known, or an animated braille spinner for indeterminate operations. Degrades to plain text on non-TTY output.

### Core Behavior
- Filled bar with █ and ░ characters
- Percentage and count display
- Braille spinner for indeterminate mode
- Elapsed time on finish
- TTY detection for ANSI vs plain output

### Progress Control

#### `__construct(total = 0, label = '')`
Creates and immediately renders the progress bar. Total of 0 activates indeterminate spinner mode.
- `$total: int` (optional) — Total steps. 0 for indeterminate spinner mode.
- `$label: string` (optional) — Text displayed alongside the bar.

#### `advance(step = 1): void`
Increments the progress counter by $step and re-renders. Clamps at total when known.
- `$step: int` (optional) — Number of steps to advance. Default 1.

#### `finish(msg = 'Done'): void`
Completes the bar, prints a newline, and displays elapsed time with a green success message.
- `$msg: string` (optional) — Completion message. Default 'Done'.

### Rendering

#### `render(): void`
Renders the current state to stdout using carriage return for in-place updates. Switches between bar and spinner based on total.

### Architecture

#### `isTty(): bool`
Returns true when stdout is connected to an interactive terminal.

## `Skim\Core\App` — app

> Singleton container and HTTP kernel combining a scoped key-value store, DI container with auto-wiring, and middleware pipeline.

`singleton` `container` `kernel` `di` `middleware` `scoped-store`

**Source:** `src/Core/App.php` · **Layer:** `core` · **Lifecycle:** `singleton, created on first `instance()` call, booted lazily via `ensureBooted()` in `run()` or `dispatch()`, frozen before request dispatch`

`Skim\Core\App` is the central application kernel. It combines three responsibilities in one singleton: a scoped key-value store (sys/app/user), a dependency injection container with factory bindings and reflection auto-wiring, and an HTTP kernel with a middleware pipeline. Extensions register services and middleware through `withExtensionContext()` during the boot phase.

### Core Behavior
- Three scopes (sys, app, user) isolate framework internals from config and per-request state
- DI resolution caches singletons and falls back to reflection auto-wiring
- Middleware runs in registration order before route handlers
- Extensions register services with priority-based conflict resolution

### Drivers
| Driver | Mutable | Description |
|---|---|---|
| sys | no | Framework internals (router, extensions). Write-once in production, mutable in debug. |
| app | yes | Config values from config/*.php or testInstance(). Falls back to Config::get on read. |
| user | yes | Per-request mutable state. Cleared on clone. |

### Warnings
- ⚠ `run()` installs a global exception handler and is not re-entrant
- ⚠ `freeze()` is irreversible for the instance lifetime
- ⚠ sys.* writes throw LogicException in production after first set

### Lifecycle

#### `__construct(root)`
Private constructor enforces singleton access via instance(). Stores the project root for .env and config path resolution.
- `$root: string` (required) — Project root directory.

#### `instance(): static`
Returns the process-wide singleton. On first call, creates the instance with SKIM_ROOT (or auto-detected root) but does NOT call boot(). Boot is deferred to run() or dispatch() via ensureBooted(). Subsequent calls return the cached instance.
- **Returns** `static` — The application instance (not yet booted).
- **Side effect:** Creates the singleton on first call
- **Side effect:** does not trigger boot

#### `boot(): void`
Initializes framework subsystems in strict dependency order: view layout → router → pipeline → extension discovery and registration. Idempotent — subsequent calls after the first are no-ops. Profiler and RequestTrace are NOT enabled here
- **Side effect:** Sets $booted = true
- **Side effect:** initialises router, pipeline, extensionManager
- **Side effect:** registers extensions

#### `ensureBooted(): void`
Calls boot() if not yet booted. Called from run() and dispatch() to guarantee the framework is initialised before any request handling occurs.

#### `isDebugMode(): bool`
Returns whether debug mode is enabled.
- **Returns** `bool` — True when app.debug config is enabled.

#### `beginRequest(): void`
Begins a request in worker mode — enables profiler and RequestTrace if debug. Separated from run() so the worker entrypoint can call it once per request without re-running the full boot sequence.
- **Side effect:** Enables profiler and RequestTrace when app.debug is true

#### `endRequest(): void`
Ends a request in worker mode — resets per-request state. Clears user scope and request-scoped DI bindings, then runs the global WorkerReset orchestrator.
- **Side effect:** Clears user scope
- **Side effect:** clears request-scoped resolved singletons
- **Side effect:** disables tracing
- **Side effect:** runs WorkerReset::apply()

#### `handleException(e): void`
Handles an uncaught exception. Used by the global exception handler installed in run(). Renders debug error page when debugMode is true, otherwise returns 500.
- `$e: \Throwable` (required) — The uncaught exception to handle.
- **Side effect:** Renders ErrorPage or sends HTTP 500 response

#### `run(): void`
Executes the full HTTP request cycle: calls ensureBooted(), enables profiler and RequestTrace (if debug), installs a global exception handler, boots extensions, freezes the app, builds the request from PHP globals, dispatches through the middleware pipeline, records request traces, and sends the response. Called once per request from public/index.php.
- ⚠ Not re-entrant
- ⚠ Installs a global exception handler that persists for the process lifetime
- ⚠ In production, 500 errors return a bare 'Internal Server Error' string
- **Side effect:** Calls ensureBooted()
- **Side effect:** Enables profiler and RequestTrace (if debug)
- **Side effect:** Installs global exception handler
- **Side effect:** Boots extensions
- **Side effect:** Freezes the app
- **Side effect:** Sends HTTP response headers and body
- **Side effect:** Records request trace or error log

#### `shutdown(): void`
Shuts down process-scoped resources before the worker exits. Closes all pooled database connections and clears the cache facade.
- **Side effect:** Resets db connection pool and cache driver

#### `assertMutable(action): void`
Throws LogicException if the app is frozen, preventing post-boot mutation. Logs the violation with the active extension name for diagnostics.
- `$action: string` (required) — Human-readable action name included in the error message and log.
- **Throws** `\LogicException` — If the app is frozen.

### Scoped Store

#### `set(key, value): void`
Stores a value in the scope determined by the key prefix. Keys prefixed `sys.` go to the immutable-after-boot sys scope, `app.` to config scope, `user.` or bare keys to per-request scope. In production (non-debug), sys.* keys throw LogicException on duplicate writes.
- `$key: string` (required) — Scoped key. Prefix determines target scope: sys.*, app.*, user.*, or bare (→ user).
- `$value: mixed` (required) — Value to store.
- **Throws** `\LogicException` — When overwriting a sys.* key in production (app.debug === false).
- **Side effect:** Mutates the target scope array

#### `get(key, default = null): mixed`
Reads from the scope matching the key prefix. For app.* keys, falls back to Config::get("app.{$k}") when the key is not set directly. Never throws — returns $default for missing keys.
- `$key: string` (required) — Scoped key to read. Prefix determines source scope.
- `$default: mixed` (optional) — Returned when the key is absent from the target scope.
- **Returns** `mixed` — The stored value, config fallback for app.*, or $default.

#### `userScopeEmpty(): bool`
Returns true when the user scope contains no keys. Used by the leak detector to verify endRequest() cleared per-request state.
- **Returns** `bool` — True when $user array is empty.

### DI Container

#### `bind(abstract, factory, priority = null, lifetime = null): void`
Registers a factory callable for DI resolution. Clears the resolved singleton cache for the abstract so the new factory takes effect on the next make() call. Higher-priority bindings replace lower ones
- `$abstract: string` (required) — Abstract type or identifier to bind. Typically a fully-qualified class name.
- `$factory: callable` (required) — Callable receiving the app instance, or a class name string for auto-wiring.
- `$priority: ?int` (optional) — Binding priority (higher wins). Null uses the current extension context priority (default 100).
- `$lifetime: ?lifetime` (optional) — Binding lifetime. Null defaults to singleton; null is rejected when strict_di is enabled.
- **Throws** `\LogicException` — If called after freeze().
- **Throws** `\LogicException` — If strict_di is enabled and no lifetime is provided.
- **Side effect:** Clears resolved singleton cache for $abstract
- **Side effect:** Mutates bindings and bindingPriorities arrays

#### `bindRequest(abstract, factory, priority = null): void`
Registers a request-scoped binding. Behaves like bind() but the resolved singleton is cleared from the container at the end of each request via endRequest().
- `$abstract: string` (required) — Abstract type or identifier to bind.
- `$factory: callable` (required) — Callable receiving app, or class name for auto-wiring.
- `$priority: ?int` (optional) — Binding priority (higher wins).
- **Side effect:** Registers binding and marks it as request-scoped

#### `bindTransient(abstract, factory, priority = null): void`
Registers a transient binding — built fresh on every make() call. Never cached in $resolved.
- `$abstract: string` (required) — Abstract type or identifier to bind.
- `$factory: callable` (required) — Callable receiving app, or class name for auto-wiring.
- `$priority: ?int` (optional) — Binding priority (higher wins).
- **Side effect:** Registers binding and marks it as transient

#### `applyBinding(abstract, factory, priority, lifetime): void`
Performs the actual binding registration for a given lifetime. Shared by bind(), bindRequest() and bindTransient(). Keeps the strict-DI check out of the request/transient helpers so they are never blocked by app.strict_di.
- `$abstract: string` (required) — Abstract type or identifier to bind.
- `$factory: callable` (required) — Callable receiving app, or class name for auto-wiring.
- `$priority: ?int` (optional) — Binding priority (higher wins). Null uses current extension priority.
- `$lifetime: lifetime` (required) — Resolved lifetime to apply.
- **Throws** `\LogicException` — If called after freeze().
- **Side effect:** Mutates bindings and bindingPriorities arrays
- **Side effect:** Clears lifetime meta for the abstract

#### `decorate(abstract, decorator, priority = null): void`
Wraps a resolved service with a decorator factory. Decorators are applied in ascending priority order, then by registration order for equal priorities. Clears the resolved cache so the next make() call applies the full decoration chain.
- `$abstract: string` (required) — Abstract type whose resolved instances should be decorated.
- `$decorator: callable` (required) — Receives ($service, $app) and returns the decorated service.
- `$priority: ?int` (optional) — Decoration priority. Null uses the current extension context priority.
- **Throws** `\LogicException` — If called after freeze().
- **Side effect:** Clears resolved singleton cache for $abstract
- **Side effect:** Appends to decorators array

#### `make(abstract): mixed`
Resolves an abstract to a singleton instance. Returns the cached singleton if already resolved. Otherwise invokes the registered factory (or auto-wires via reflection when no binding exists but the class is loadable), applies decorators in priority order, and caches the result.
- `$abstract: string` (required) — Class name or identifier to resolve. Must have a binding or be an instantiable class.
- **Returns** `mixed` — The resolved (and possibly decorated) singleton instance.
- **Throws** `\RuntimeException` — If no binding exists and the class cannot be auto-wired (missing constructor dependency with no binding or default).
- **Side effect:** Caches resolved instance in $resolved array

#### `makeTransient(abstract): mixed`
Resolves a service fresh every time
- `$abstract: string` (required) — Class name or identifier to resolve.
- **Returns** `mixed` — A freshly built (and possibly decorated) instance.
- **Throws** `\RuntimeException` — If no binding exists and the class cannot be auto-wired.
- **Side effect:** Never writes to $resolved array

#### `requestScopedServices(): array`
Returns the list of abstracts registered with request lifetime. Used by the leak detector and tests to verify that request-scoped bindings are correctly tracked.
- **Returns** `string[]` — Abstract identifiers bound as request-scoped.

#### `clearLifetimeMeta(abstract): void`
Clears resolved cache and all lifetime flags for an abstract. Centralizes lifetime transition so bind(), bindRequest(), and bindTransient() cannot leave stale requestScoped/transient flags behind when an abstract is rebound with a different lifetime.
- `$abstract: string` (required) — Abstract whose lifetime metadata should be cleared.
- **Side effect:** Unsets entries in $resolved
- **Side effect:** $requestScoped
- **Side effect:** and $transient arrays

#### `normalizeFactory(factory): callable`
Converts a string class name to an auto-wiring factory closure that calls build(). Passes callables through unchanged.
- `$factory: callable` (required) — Class name string or callable.
- **Returns** `callable` — Always a callable accepting an app instance.

#### `build(abstract): mixed`
Instantiates a class via reflection auto-wiring. Resolves constructor dependencies recursively through make(). Uses default parameter values for scalar params without bindings.
- `$abstract: string` (required) — Fully-qualified class name to instantiate.
- **Throws** `\RuntimeException` — If a constructor parameter has no binding and no default value.

#### `applyDecorators(abstract, service): mixed`
Applies registered decorators to a resolved service in ascending priority order, then registration order. Returns the service unchanged when no decorators exist for the abstract.
- `$abstract: string` (required) — Abstract type used to look up decorators.
- `$service: mixed` (required) — The resolved service to decorate.
- **Returns** `mixed` — The decorated (or original) service.

### Middleware

#### `use(class, args): void`
Registers a global middleware class applied to every HTTP request in registration order. Must be called before run() or freeze().
- `$class: string` (required) — Middleware class name implementing the middleware interface.
- `$args: mixed` (optional) — Constructor arguments passed to the middleware when instantiated.
- **Throws** `\LogicException` — If called after freeze().
- **Side effect:** Appends to globalMiddleware stack

#### `freeze(): void`
Freezes all mutation points: service bindings, decorators, middleware registration, and route mutations. Called automatically by run() before dispatch. Irreversible for this instance.
- ⚠ Irreversible — no thaw() method exists. After freeze
- ⚠ bind()
- ⚠ decorate()
- ⚠ use()
- ⚠ and route registration all throw LogicException.
- **Side effect:** Sets internal frozen flag to true

#### `isFrozen(): bool`
Returns true after freeze() has been called.
- **Returns** `bool` — True if the app mutation points are frozen.

### Request Dispatch

#### `emit(result, fallback): void`
Emits a controller result through the response object. Normalises mixed return values into a proper HTTP response and sends it.
- `$result: mixed` (required) — Controller return value.
- `$fallback: response` (required) — Response object used as fallback for non-response types.
- **Side effect:** Sends HTTP response

#### `dispatch(req, res, skipMiddleware = false): response`
Dispatches a request through the router and middleware pipeline without sending headers or body. Calls ensureBooted() first to guarantee the framework is initialised. Returns 404 for unmatched routes, 405 for method mismatches. Temporarily sets this instance as the global singleton during dispatch and restores the previous instance in a finally block.
- `$req: request` (required) — The request to dispatch.
- `$res: response` (required) — The response object to populate.
- `$skipMiddleware: bool` (optional) — When true, bypasses all global and route middleware. Useful for unit tests.
- **Returns** `response` — The populated response. Status 404 if no route matches, 405 if path matches but method does not.
- **Side effect:** Temporarily replaces self::$instance during dispatch

#### `callHandler(handler, req, res, params): mixed`
Resolves controller handler arguments via DI and route params. For closures, passes request/response and route params directly. For class-based handlers, resolves the controller via make() and injects method dependencies by type-hint, matching route params by name.
- `$handler: array` (required) — Route handler — either a closure or [class, method] array.
- `$req: request` (required) — Current request.
- `$res: response` (required) — Current response.
- `$params: array` (required) — Route parameters matched by the router.

### Extensions

#### `withExtensionContext(name, priority, callback): mixed`
Temporarily sets the active extension name and priority so that bind(), decorate(), and similar calls inside $callback inherit the correct priority. The previous extension context is restored in a finally block, even if the callback throws.
- `$name: string` (required) — Extension identifier used for diagnostics and assertMutable error messages.
- `$priority: int` (required) — Priority applied to registrations (bind, decorate) inside the callback.
- `$callback: callable` (required) — Executed with the extension context active. Return value is passed through.
- **Returns** `mixed` — Whatever the callback returns.
- **Side effect:** Temporarily mutates extensionContext
- **Side effect:** restored in finally block

#### `capabilities(): array`
Aggregates capability declarations from all loaded extensions into a flat map keyed by capability name. Each value includes `provided_by` (extension name) and any extension-declared details. First provider wins for duplicate capability names.
- **Returns** `array<string, array{provided_by: string}>` — Flat map of capability name to provider details.
- **Note:** Safe to call before or after freeze — reads sys.extensions which is set during extension discovery.

#### `bootExtensions(): void`
Boots extensions once via the extension manager. Idempotent — subsequent calls after the first are no-ops.

#### `currentExtensionPriority(): int`
Returns the priority from the active extension context set by withExtensionContext(). Defaults to 100 when no context is active.

### Testing

#### `__clone()`
Resets resolved singletons and user scope on clone. Preserves bindings, decorators, sys/app data, and middleware. Creates a fresh pipeline.

#### `testInstance(config = []): static`
Creates a fresh isolated container that skips .env and config/*.php loading. Config values are injected directly via the $config array. The returned instance has its own router, pipeline, and mutation guard but shares no state with App::instance().
- `$config: array` (optional) — Key-value pairs injected into app scope. Keys use dot notation without the app. prefix (e.g. 'db.driver' => 'memory').
- **Returns** `static` — A fresh, unbooted container with router and pipeline ready.
- **Note:** Safe to call multiple times per test. Each call returns an independent instance.

### Methods

#### `snapshotBindings(): array`
Returns a metadata array of every currently-registered binding. :snapshot_bindings Used by ErrorPage::collectContainer() to render the "registered services" list in the Container panel. Captures [abstract, factory_kind, priority] triples; never the factory closure itself (closures don't survive var_export and would leak memory in the error page).

#### `enableTracing(): void`
Enables DI tracing — make() starts populating resolve_stack, failed_at, :enable_tracing and partial_args so the error page can render the in-flight chain when a binding throws. Off by default to keep the hot path allocation-free. Should be turned on by the error handler before render(), not at request time, so normal requests pay nothing.

#### `disableTracing(): void`
Disables DI tracing and clears the captured state. :disable_tracing Safe to call between requests — resets the trace fields so a leftover state from a previous request can't leak into a new one.

#### `resolvedServices(): array`
Returns the list of abstracts that have been resolved during this request. :resolved_services Mirrors Laravel's container->resolved() — used by the error page's Container tab to show which services have been instantiated and which are still pending. Exposes only the abstract names (not the instances themselves) so the list is safe to render.

## `Skim\Core\Config` — config

> Static config registry with lazy-loading and dot-notation read access across plain PHP array files.

`facade` `config` `dot-notation` `lazy-load` `frozen-after-boot`

**Source:** `src/Core/Config.php` · **Layer:** `core` · **Lifecycle:** `static facade, lazy-loaded on first get() call, frozen afterwards`

`Skim\Core\Config` is the single source of configuration for the entire application. It lazy-loads a directory of plain PHP array files on first `get()` call, namespaces each file's return value by its filename, and exposes dot-notation reads. After the first load() call the registry is frozen — further load() calls are silently ignored.

### Core Behavior
- Each config file (db.php, app.php, etc.) becomes a top-level key in the registry
- Dot-notation traversal walks nested arrays segment by segment
- Config is frozen after boot to prevent runtime mutations from load()

### Warnings
- ⚠ Config is frozen after boot — set() sets the boot flag and should only be used in tests

### Read API

#### `load(dir): void`
Scans $dir for *.php files, requires each one, and stores the returned array under a key derived from the filename (without extension). Only the first call takes effect
- `$dir: string` (required) — Absolute path to the directory containing config PHP files.
- **Side effect:** Sets the boot flag to true
- **Side effect:** Populates the internal data map from disk files

#### `get(key, default = null): mixed`
Auto-loads config directory on first call when not yet booted. Traverses the config data using dot-separated segments. Returns $default when any segment is missing or a non-array intermediate is encountered.
- `$key: string` (required) — Dot-separated path such as 'db.default.host'.
- `$default: mixed` (optional) — Fallback returned when the path does not resolve.
- **Returns** `mixed` — The resolved config value, or $default if the path is not found.

#### `loadCompiledCache(): bool`
Attempts to load config from a pre-compiled PHP array cache at storage/config_cache/config.php. Returns false when the cache file is missing or stale (APP_DEBUG=true and any config/*.php file newer than cache). Sets booted flag on success.
- **Returns** `bool` — True if cache was loaded, false if caller should fall back to load().
- **Side effect:** Populates self::$data from compiled file
- **Side effect:** Sets self::$booted to true on success

#### `all(): array`
Returns the entire internal config data array as-is. Auto-loads on first call.
- **Returns** `array` — The full nested config map keyed by filename then array keys.

### Testing Hooks

#### `set(key, value): void`
Writes a value at the given dot-notation path, creating intermediate arrays as needed. Sets the boot flag to prevent auto-load from overwriting test values. Intended for test setup only.
- `$key: string` (required) — Dot-separated path to write.
- `$value: mixed` (required) — Value to store at the resolved path.
- **Side effect:** Sets boot flag to true
- **Side effect:** Mutates the in-memory config map
- **Note:** Always pair with reset() in tearDown() to avoid leaking state between tests.

#### `reset(): void`
Clears all loaded config data and resets the boot flag so the next load() or get() call will re-scan the config directory.
- **Side effect:** Clears the internal data map
- **Side effect:** Resets boot flag to false

## `Skim\Core\Env` — env

> Static facade for lazy-loading .env files and accessing typed environment variables with OS-var priority.

`facade` `env` `static` `zero-dependency` `lazy-load`

**Source:** `src/Core/Env.php` · **Layer:** `core` · **Lifecycle:** `static facade, .env lazy-loaded on first get() call`

`Skim\Core\Env` lazy-loads a `.env` file on first `get()` call and provides typed access to environment variables. OS environment variables (`$_SERVER`, `$_ENV`) always take priority over `.env` values, ensuring deployment-injected values are never silently overwritten. No `putenv()` or `$_ENV` mutations — internal cache only.

### Core Behavior
- Parses KEY=VALUE lines from .env into internal cache only
- OS var priority enforced at read time in get(), not at load time
- set() writes to cache and sets loaded flag to prevent auto-load clobbering

### Warnings
- ⚠ OS environment variables in $_SERVER or $_ENV always override .env file values — this is intentional

### Read API

#### `get(key, default = null): mixed`
Returns the environment variable value for the given key. Auto-loads .env on first call when not yet loaded. Read priority: $_SERVER → $_ENV → internal cache → $default. Casts string values 'true', 'false', and 'null' (case-insensitive) to native PHP types.
- `$key: string` (required) — Environment variable name.
- `$default: mixed` (optional) — Fallback value returned as-is when the key is not found in any source.
- **Returns** `mixed` — The typed value (bool for 'true'/'false', null for 'null', string otherwise) or $default if absent.
- **Note:** Lookup order is $_SERVER, then $_ENV, then internal cache. Numeric strings are NOT cast to int or float.

#### `all(): array`
Returns all cached environment variables as a flat array. Auto-loads on first call. Includes values from .env and set() overrides. Does not include $_SERVER or $_ENV values.
- **Returns** `array` — Flat key-value map of all cached environment variables.

#### `loadCompiledCache(): bool`
Attempts to load env values from a pre-compiled PHP array cache at storage/config_cache/env.php. Returns false when the cache file is missing or stale (APP_DEBUG=true and .env newer than cache). Sets loaded flag on success.
- **Returns** `bool` — True if cache was loaded, false if caller should fall back to load().
- **Side effect:** Populates self::$cache from compiled file
- **Side effect:** Sets self::$loaded to true on success

### Write API

#### `load(path): void`
Parses the .env file at the given path into the internal cache only. Idempotent — only the first call has effect. Silently skips missing files. OS variable priority is enforced at read time in get(), not at load time.
- `$path: string` (required) — Absolute path to the .env file. Typically basePath('.env').
- ⚠ Idempotent — calling load() a second time with a different path has no effect
- **Side effect:** Populates self::$cache with parsed key-value pairs
- **Side effect:** No $_ENV or putenv() mutations
- **Note:** Lines starting with # are treated as comments. Surrounding single or double quotes are stripped from values.

### Testing Hooks

#### `set(key, value): void`
Overrides a single environment variable in the internal cache only. Sets the loaded flag to prevent auto-load from overwriting test values. Does not touch $_ENV or putenv(). Intended for test isolation.
- `$key: string` (required) — Environment variable name to override.
- `$value: mixed` (required) — Value to store. Persists for the process lifetime until reset().
- **Side effect:** Mutates self::$cache
- **Side effect:** Sets self::$loaded to true
- **Note:** Override is visible to Env::get() but not to getenv() or $_ENV readers.

#### `reset(): void`
Clears the internal cache and resets the loaded flag, allowing a subsequent load() call to re-parse the .env file. Use in test tearDown().
- **Side effect:** Clears self::$cache
- **Side effect:** Sets self::$loaded to false

## `Skim\Core\Pipeline` — pipeline

> Middleware chain builder and executor using the onion model.

`pipeline` `middleware` `onion-model`

**Source:** `src/Core/Pipeline.php` · **Layer:** `core` · **Lifecycle:** `Fresh pipeline instance per request — no mutable state is retained between calls.`

`Skim\Core\Pipeline` builds a composed callable from an ordered list of middleware entries and a terminal handler. The list is reversed internally so execution follows FIFO order: first-registered = first to run.

### Execution

#### `run(req, res, middlewares, core): mixed`
Resolves and chains middlewares, invokes the composed chain, and returns the terminal or short-circuit response.
- `$req: request` (required) — Incoming request
- `$res: response` (required) — Mutable response
- `$middlewares: array` (required) — Ordered list of class-string, instance, or factory-array entries
- `$core: callable` (required) — Terminal handler, signature (request, response): mixed
- **Returns** `mixed` — Response from terminal handler or short-circuit middleware.

### Internals

#### `build(middlewares, core): callable`
Reverses the middleware list and wraps $core with each resolved instance from innermost to outermost.
- `$middlewares: array` (required) — Ordered middleware entries
- `$core: callable` (required) — Terminal handler
- **Returns** `callable` — Composed closure with signature (request, response): mixed.

#### `resolve(entry): middleware`
Normalizes a middleware entry to a concrete middleware instance. Accepts instances (returned as-is), class-strings (instantiated via new), and factory arrays ['class' => ..., 'args' => [...]].
- `$entry: string` (required) — Entry in one of three supported formats
- **Returns** `middleware` — Resolved middleware instance.

### Methods

#### `resetInstanceCache(): void`
Clears the middleware singleton cache. Called between requests in worker mode. Middleware instances should be stateless; clearing the cache prevents request-scoped state from leaking across requests while still allowing process-lifetime caching to be rebuilt lazily.

#### `recordPipelineTrace(req, middlewares): void`
Emits one timeline event summarising the middleware stack for this request. :record_pipeline_trace Called at the top of run() so the trace always carries a 'middleware_ran' event with the full class list (in execution order) and a synthetic marker identifying the innermost entry. Read by the error page's Request panel and the toolbar. No-op when request_trace is disabled — never throws.

## `Skim\Core\Request` — request

> HTTP request abstraction wrapping superglobals with typed accessors for query, POST, JSON, headers, files, and route params.

`request` `http` `value-object` `superglobal-wrapper`

**Source:** `src/Core/Request.php` · **Layer:** `core` · **Lifecycle:** `created once by App::run() via fromGlobals(), shared across middleware and controller`

`Skim\Core\Request` wraps PHP superglobals into a typed, testable object. It is injected by the container into controllers and middleware — never instantiated manually in application code. The same instance is shared across the entire request lifecycle.

### Warnings
- ⚠ X-Forwarded-For is trusted without proxy validation — do not use ip() as a security boundary
- ⚠ Mutations to $_GET after fromGlobals() are not reflected

### Construction

#### `fromGlobals(): static`
Creates a request from PHP superglobals. Called once by App::run(). Reads superglobals at call time.

#### `make(args): static`
Fabricates a request from explicit arrays for testing. Delegates to RequestFactory::make().
- `$args: mixed` (optional) — Arguments forwarded to RequestFactory::make().
- **Returns** `static` — A fabricated request instance.
- **Note:** For test use only — not for production request creation.

### Input Access

#### `get(key, default = null): mixed`
Returns a query string value from $_GET. Does not fall through to POST or JSON.
- `$key: string` (required) — Query parameter name.
- `$default: mixed` (optional) — Returned when the key is absent.
- **Returns** `mixed` — The query value or $default.

#### `post(key, default = null): mixed`
Returns a POST body value from $_POST. Does not include JSON body or query string.
- `$key: string` (required) — POST field name.
- `$default: mixed` (optional) — Returned when the key is absent.
- **Returns** `mixed` — The POST value or $default.

#### `input(key, default = null): mixed`
Searches GET first, then POST. Returns the first match.
- `$key: string` (required) — Field name to search.
- `$default: mixed` (optional) — Returned when absent from both sources.
- **Returns** `mixed` — First match from GET then POST, or $default.

#### `json(): array`
Parses the JSON body on first call and caches the result. Returns empty array when Content-Type is not application/json or body is invalid JSON.
- **Returns** `array` — Parsed JSON body or empty array.
- **Note:** Never throws on malformed JSON. Safe to call multiple times per request.

#### `file(key): ?array`
Returns the uploaded file array from $_FILES, or null if absent or no file uploaded.
- `$key: string` (required) — File input field name.
- **Returns** `?array` — File array or null.

### Headers & IP

#### `header(name): ?string`
Returns a request header value. Name is case-insensitive. Normalizes to $_SERVER HTTP_ format.
- `$name: string` (required) — Header name (case-insensitive).
- **Returns** `?string` — Header value or null if absent.

#### `ip(): string`
Returns the client IP. Reads X-Forwarded-For first, falls back to REMOTE_ADDR.
- **Returns** `string` — Client IP address.
- ⚠ Does not validate X-Forwarded-For against trusted proxies — not a security boundary

### URL & Method

#### `method(): string`
Returns the HTTP method, always uppercase.
- **Returns** `string` — GET, POST, PUT, PATCH, DELETE, etc.

#### `path(): string`
Returns the request path without query string.
- **Returns** `string` — Path like '/users/5'.

#### `url(): string`
Returns the full URL including scheme and host.
- **Returns** `string` — Full URL.

### Detection Helpers

#### `isHypermedia(library = null): bool`
Detects hypermedia library from configured headers. Returns true for ANY known library when $library is null, or for a specific library when specified.
- `$library: string` (optional) — Specific library to check ('htmx', 'datastar', 'turbo'). If null, checks any known library.
- **Returns** `bool` — True when the library's header is present.
- **Note:** Headers are configured in config/realtime.php. Users can add custom libraries via config.

#### `isJson(): bool`
Returns true when Accept header contains application/json.
- **Returns** `bool` — True for JSON-accepting requests.

#### `isAjax(): bool`
Returns true when X-Requested-With is XMLHttpRequest.
- **Returns** `bool` — True for XHR requests.

#### `isCli(): bool`
Returns true when running via PHP CLI.
- **Returns** `bool` — True for CLI SAPI.

### Route Params

#### `setRouteParams(params): void`
Injects route parameters after dispatch. Called by framework, never by application code.
- `$params: array` (required) — Route parameter key-value pairs.

#### `param(key, default = null): mixed`
Returns a route segment value. Type casting is applied by the router before injection.
- `$key: string` (required) — Route parameter name.
- `$default: mixed` (optional) — Returned when the param is absent.
- **Returns** `mixed` — Route param value or $default.

#### `allParams(): array`
Returns all route parameters as a flat key-value array.
- **Returns** `array` — All route params.

### Raw Access

#### `cookie(key, default = null): mixed`
Returns a cookie value from $_COOKIE.
- `$key: string` (required) — Cookie name.
- `$default: mixed` (optional) — Returned when absent.
- **Returns** `mixed` — Cookie value or $default.

#### `raw(): string`
Returns the raw php://input body. Useful for non-form payloads.
- **Returns** `string` — Raw request body.

### Methods

#### `__construct(query, post, server, cookies, files, rawBody)`

## `Skim\Core\Response` — response

> HTTP response builder with fluent chaining for status, headers, JSON, views, redirects, streaming, and file downloads.

`response` `http` `fluent` `builder`

**Source:** `src/Core/Response.php` · **Layer:** `core` · **Lifecycle:** `created fresh per request by App::run(), populated by controller, sent after middleware`

`Skim\Core\Response` builds the HTTP response through fluent method chaining. Controllers return the response object

### Warnings
- ⚠ stream() sends headers inline — middleware response modifications after stream() have no effect
- ⚠ Never echo or die in controllers — always return $res->...

### Status

#### `status(code): static`
Sets the HTTP status code. Returns $this for chaining. Does not send headers.
- `$code: int` (required) — HTTP status code (200, 201, 404, 500, etc.).
- **Returns** `static` — $this for fluent chaining.

### JSON & HTML

#### `json(data, status = 0): static`
Serializes $data to JSON with JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES. Sets Content-Type to application/json. Optional $status overrides the current status code.
- `$data: mixed` (required) — Data to serialize to JSON.
- `$status: int` (optional) — Optional status code override. 0 means use current.
- **Returns** `static` — $this for fluent chaining.

#### `html(content): static`
Static factory that creates a new Response with raw HTML content and text/html Content-Type.
- `$content: string` (required) — Raw HTML string.
- **Returns** `static` — New response instance with HTML body.

### Views

#### `view(template, data = []): static`
Renders a PHP template via View::render() and sets Content-Type to text/html.
- `$template: string` (required) — Template path relative to views directory.
- `$data: array` (optional) — Variables extracted into template scope.
- **Returns** `static` — $this for fluent chaining.
- **Throws** `\Skim\View\Exceptions\ViewException` — If template file not found.

#### `fragment(template, data, fragment): static`
Renders only a named @fragment block from the template. Smaller response than view() — no layout overhead.
- `$template: string` (required) — Template path.
- `$data: array` (required) — Template variables.
- `$fragment: string` (required) — Fragment name.
- **Returns** `static` — $this for fluent chaining.

#### `smartView(template, data, req): static`
Auto-selects full view vs fragment based on HX-Target or datastar-target request headers.
- `$template: string` (required) — Template path.
- `$data: array` (required) — Template variables.
- `$req: request` (required) — Current request for header inspection.
- **Returns** `static` — $this for fluent chaining.

### Redirects

#### `redirect(url = ''): static`
Sets 302 status and Location header. Empty URL is a no-op. Does not send headers immediately.
- `$url: string` (optional) — Target URL. Empty string is a no-op.
- **Returns** `static` — $this for fluent chaining.

#### `back(req, fallback = '/'): static`
Redirects to the Referer header value, falling back to $fallback when absent.
- `$req: request` (required) — Current request to read Referer from.
- `$fallback: string` (optional) — URL used when Referer is absent.
- **Returns** `static` — $this for fluent chaining.

### Streaming & Downloads

#### `stream(callback, driver = null): static`
Sends SSE headers immediately, disables output buffering, and passes an Sse or resolved driver instance to the callback. Driver is resolved from the container so apps/extensions can override with one bind().
- `$callback: callable` (required) — Receives an Sse or driver instance to emit events.
- `$driver: ?string` (optional) — Optional driver class to resolve from the container. Falls back to config('realtime.driver'). Defaults to plain Sse.
- **Returns** `static` — $this for fluent chaining.
- ⚠ Headers are sent inline — middleware response modifications after this call have no effect
- **Side effect:** Sends HTTP headers immediately
- **Side effect:** Disables output buffering
- **Side effect:** Resolves driver from container when configured

#### `download(filePath, filename = ''): static`
Sets Content-Disposition: attachment headers. File is read and sent by send().
- `$filePath: string` (required) — Absolute path to the file on disk.
- `$filename: string` (optional) — Suggested save name. Defaults to basename.
- **Returns** `static` — $this for fluent chaining.
- **Throws** `\RuntimeException` — If filePath does not exist.

### Headers & Body

#### `withHeader(name, value): static`
Adds or overwrites a response header.
- `$name: string` (required) — Header name.
- `$value: string` (required) — Header value.
- **Returns** `static` — $this for fluent chaining.

#### `setBody(body): static`
Replaces the response body.
- `$body: string` (required) — Raw body content.
- **Returns** `static` — $this for fluent chaining.

### Output

#### `send(): void`
Sends headers and body to the PHP output buffer. Idempotent — no-op if already sent. Handles stream and file download sentinels internally.
- **Side effect:** Writes HTTP headers and body to output buffer

### Test Accessors

#### `getStatus(): int`
Returns the current HTTP status code.
- **Returns** `int` — HTTP status code.

#### `getBody(): string`
Returns the raw response body string.
- **Returns** `string` — Response body.

#### `getHeaders(): array`
Returns all response headers as an associative array.
- **Returns** `array` — Header name => value pairs.

#### `getHeader(key): ?string`
Returns a response header value by name. Case-insensitive lookup.
- `$key: string` (required) — Header name.
- **Returns** `?string` — Header value or null if absent.

#### `getJson(): array`
Decodes the JSON response body as an array. Returns empty array when invalid.
- **Returns** `array` — Decoded JSON or empty array.

## `Skim\Core\RouteEntry` — RouteEntry

> Fluent route configuration object returned by router registration methods.

`fluent` `route` `middleware` `named-route`

**Source:** `src/Core/RouteEntry.php` · **Layer:** `core` · **Lifecycle:** `Created fresh per route registration. Passed around by reference (object, not copy) until consumed by Router::dispatch().`

`Skim\Core\RouteEntry` is the return value of every `Router::add()` and `App::get()/post()/put()/patch()/delete()` call. It exposes two fluent configuration methods — `name()` and `middleware()` — and two read-only accessors consumed internally by the router during dispatch.

### Configuration

#### `name(name): static`
Assigns a unique name and registers it with the router for reverse URL generation via route(). Overwrites any previous registration for the same name.
- `$name: string` (required) — Unique route name. Must be non-empty. Overwrites duplicates.
- **Returns** `static` — $this for fluent chaining.
- **Side effect:** Calls $router->registerName($name, $pattern).

#### `middleware(classes): static`
Appends one or more middleware class-strings to this route's execution stack. Route middleware runs after global and group middleware.
- `$classes: string` (required) — Middleware class-strings. Each must implement the middleware interface.
- **Returns** `static` — $this for fluent chaining.
- **Note:** middleware is additive — call it multiple times to accumulate. Order within route middleware matches call order.

### Accessors

#### `getName(): ?string`
Returns the assigned route name or null if name() was never called.
- **Returns** `?string` — Route name or null.

#### `getMiddleware(): array`
Returns all appended middleware class-strings in registration order.
- **Returns** `array` — Ordered list of middleware class-strings.

### Methods

#### `__construct(method, pattern, handler, router)`

## `Skim\Core\Router` — router

> HTTP and CLI router wrapping nikic/fast-route with compiled regex dispatch, F3-compatible @param tokens, and route groups.

`router` `fast-route` `dispatch` `named-routes` `groups` `cli`

**Source:** `src/Core/Router.php` · **Layer:** `core` · **Lifecycle:** `created during app boot, routes registered before freeze(), compiled on first dispatch`

`Skim\Core\Router` wraps nikic/fast-route to compile all routes into a single regex on first dispatch. It supports F3-compatible @param token syntax (@id, @id:int, @slug:str, @any), route groups with additive prefix and middleware, named routes for reverse URL generation, and CLI command routing.

### Warnings
- ⚠ Route registration after freeze() throws LogicException
- ⚠ url() requires the app singleton to be available

### Route Registration

#### `setMutationGuard(guard): void`
Installs a callback that returns false when route mutation should be blocked.
- `$guard: callable` (required) — Returns true when mutation is allowed.

#### `add(methods, pattern, handler): RouteEntry`
Registers a route for one or more HTTP methods. Converts @param tokens, applies group prefix and middleware, invalidates the compiled dispatcher.
- `$methods: string` (required) — HTTP method(s).
- `$pattern: string` (required) — URL pattern with optional @param tokens.
- `$handler: array` (required) — Controller reference or closure.
- **Returns** `RouteEntry` — Fluent route configuration object.
- **Throws** `\LogicException` — If the mutation guard blocks the call.
- **Side effect:** Invalidates compiled dispatcher
- **Side effect:** Appends to routes array

### HTTP Methods

#### `get(pattern, handler, middleware = []): RouteEntry`
Registers a GET route. Delegates to add().
- `$pattern: string` (required) — URL pattern with optional @param tokens.
- `$handler: array` (required) — Controller reference or closure.
- `$middleware: array` (optional) — Route-level middleware classes.
- **Returns** `RouteEntry` — Fluent route configuration object.

#### `post(pattern, handler, middleware = []): RouteEntry`
Registers a POST route. Delegates to add().
- `$pattern: string` (required) — URL pattern with optional @param tokens.
- `$handler: array` (required) — Controller reference or closure.
- `$middleware: array` (optional) — Route-level middleware classes.
- **Returns** `RouteEntry` — Fluent route configuration object.

#### `put(pattern, handler, middleware = []): RouteEntry`
Registers a PUT route. Delegates to add().
- `$pattern: string` (required) — URL pattern.
- `$handler: array` (required) — Controller reference or closure.
- `$middleware: array` (optional) — Route-level middleware.
- **Returns** `RouteEntry` — Fluent route configuration object.

#### `patch(pattern, handler, middleware = []): RouteEntry`
Registers a PATCH route. Delegates to add().
- `$pattern: string` (required) — URL pattern.
- `$handler: array` (required) — Controller reference or closure.
- `$middleware: array` (optional) — Route-level middleware.
- **Returns** `RouteEntry` — Fluent route configuration object.

#### `delete(pattern, handler, middleware = []): RouteEntry`
Registers a DELETE route. Delegates to add().
- `$pattern: string` (required) — URL pattern.
- `$handler: array` (required) — Controller reference or closure.
- `$middleware: array` (optional) — Route-level middleware.
- **Returns** `RouteEntry` — Fluent route configuration object.

#### `any(pattern, handler, middleware = []): RouteEntry`
Registers a route for all HTTP methods (GET, POST, PUT, PATCH, DELETE).
- `$pattern: string` (required) — URL pattern.
- `$handler: array` (required) — Controller reference or closure.
- `$middleware: array` (optional) — Route-level middleware.
- **Returns** `RouteEntry` — Fluent route configuration object.

#### `map(methods, pattern, handler): RouteEntry`
Registers a route for one or more HTTP methods. Thin alias for add() — provides Slim/Laravel-style map() for compatibility with code that expects that convention.
- `$methods: string` (required) — One HTTP method ('GET') or a list (['GET','POST']).
- `$pattern: string` (required) — URL pattern with optional @param tokens.
- `$handler: array` (required) — Controller reference or closure.
- **Returns** `RouteEntry` — Fluent route configuration object.
- **Throws** `\LogicException` — If the mutation guard blocks the call.
- **Note:** Prefer get()/post()/put()/patch()/delete() for single-method routes and any() for all-method routes. Use map() when the method set is dynamic or when mirroring Slim/Laravel-style code.

### Groups & Commands

#### `group(prefix, callback, middleware = []): void`
Groups routes under a shared prefix and middleware stack. Groups nest additively.
- `$prefix: string` (required) — URL prefix prepended to all routes in the group.
- `$callback: callable` (required) — Receives the router for route registration.
- `$middleware: array` (optional) — Middleware classes applied to all group routes.
- **Throws** `\LogicException` — If the mutation guard blocks the call.

#### `command(name, handler): void`
Registers a CLI command route dispatched by bin/skim when argv[1] matches.
- `$name: string` (required) — Command name.
- `$handler: array` (required) — Command handler.
- **Throws** `\LogicException` — If the mutation guard blocks the call.

### Named Routes

#### `registerName(name, pattern): void`
Registers a name-to-pattern mapping for reverse URL generation. Called by RouteEntry::name().
- `$name: string` (required) — Route name.
- `$pattern: string` (required) — URL pattern with placeholders.
- **Throws** `\LogicException` — If the mutation guard blocks the call.

#### `url(name, params = []): string`
Static entry point that resolves the router from the app singleton and generates a URL from a named route.
- `$name: string` (required) — Registered route name.
- `$params: array` (optional) — Key-value pairs for route placeholders.
- **Returns** `string` — Generated URL path.
- **Throws** `\InvalidArgumentException` — If name is not registered or params are missing.
- **Throws** `\RuntimeException` — If router is not available in container.

#### `buildUrl(name, params = []): string`
Instance method that replaces {param:regex} segments with values from $params.
- `$name: string` (required) — Registered route name.
- `$params: array` (optional) — Key-value pairs for placeholders.
- **Returns** `string` — Generated URL path.
- **Throws** `\InvalidArgumentException` — If name not found or params missing.

### Dispatch

#### `dispatch(method, path): array|null|false`
Compiles routes on first call, then dispatches method+path against the fast-route dispatcher. Returns route info on match, null on 404, false on 405.
- `$method: string` (required) — HTTP method.
- `$path: string` (required) — Request path.
- **Returns** `array` — Route info {handler, params, middleware} on match, null (404), or false (405).

#### `dispatchCommand(name): array|null`
Dispatches a CLI command by name. Returns handler info or null if not registered.
- `$name: string` (required) — Command name from argv[1].
- **Returns** `array` — Handler info or null.

## `Skim\Db\Db` — db

> Static facade for database operations using query_gen SQL templates with lazy connections and profiler integration.

`facade` `db` `query_gen` `lazy-connect` `profiler`

**Source:** `src/Db/Db.php` · **Layer:** `db` · **Lifecycle:** `static facade, connections resolved lazily on first query per named connection`

`db` is the static entry point for all raw SQL operations. It uses query_gen `%placeholder%` templates processed by `QueryBuilder` (internal). Connections are lazy — PDO is created on first query, not on config load. All queries are recorded in the profiler when APP_DEBUG is enabled.

### Core Behavior
- query_gen %placeholders% are substituted by QueryBuilder
- unused placeholders stripped silently
- debug:true returns interpolated SQL without executing
- profiler records every query with timing

### Warnings
- ⚠ Always use transaction() for multi-table writes
- ⚠ Always use limit on large tables with all()

### Connection Management

#### `connect(name, config): void`
Registers and immediately opens a named PDO connection. The config array must contain driver, host/database, and optional port/charset/credentials.
- `$name: string` (required) — Connection name used in all query methods.
- `$config: array` (required) — Driver config array from config/db.php.
- **Side effect:** Opens a PDO connection and stores it in the pool.

#### `pdo(connection = 'default'): \PDO`
Returns the PDO instance for the named connection. Auto-connects from config/db.php on first access.
- `$connection: string` (optional) — Named connection. Default 'default'.
- **Returns** `\PDO` — The PDO instance for the named connection.
- **Throws** `\RuntimeException` — When the named connection is not defined in config/db.php.

### Query Execution

#### `query(sql, params = [], debug = false, connection = 'default'): mixed`
Executes a query_gen SQL template. Returns array of rows for SELECT, int rowCount for INSERT/UPDATE/DELETE. When $debug is true, returns the interpolated SQL string without executing.
- `$sql: string` (required) — SQL template with %placeholders%.
- `$params: array` (optional) — Placeholder values and :named params.
- `$debug: bool` (optional) — When true, returns interpolated SQL without executing.
- `$connection: string` (optional) — Named DB connection.
- **Returns** `array` — Rows for SELECT, rowCount for DML, SQL string when debug is true.
- **Side effect:** Executes SQL, records in profiler.

#### `val(sql, params = [], connection = 'default'): mixed`
Returns a single scalar value from the first column of the first row. Returns null when no row matches.
- `$sql: string` (required) — SQL template with %placeholders%.
- `$params: array` (optional) — Placeholder values and :named params.
- `$connection: string` (optional) — Named DB connection.
- **Returns** `mixed` — Scalar value or null.
- **Side effect:** Executes SQL, records in profiler.

#### `row(sql, params = [], connection = 'default'): ?array`
Returns a single row as an associative array. Returns null when no row matches — never throws.
- `$sql: string` (required) — SQL template with %placeholders%.
- `$params: array` (optional) — Placeholder values and :named params.
- `$connection: string` (optional) — Named DB connection.
- **Returns** `?array` — Associative array or null.
- **Side effect:** Executes SQL, records in profiler.

#### `all(sql, params = [], connection = 'default'): array`
Returns all matching rows as an array. Returns empty array when no rows match — never null.
- `$sql: string` (required) — SQL template with %placeholders%.
- `$params: array` (optional) — Placeholder values and :named params.
- `$connection: string` (optional) — Named DB connection.
- **Returns** `array` — Array of associative arrays.
- **Side effect:** Executes SQL, records in profiler.

### Transactions

#### `transaction(fn, connection = 'default'): mixed`
Wraps $fn in BEGIN/COMMIT. Auto-ROLLBACK on any Throwable. The original exception is rethrown after rollback — never swallowed.
- `$fn: callable` (required) — Code to execute inside the transaction.
- `$connection: string` (optional) — Named DB connection.
- **Returns** `mixed` — Return value of $fn.
- ⚠ Always use for multi-table writes — partial writes corrupt data
- **Side effect:** Manages transaction state on the PDO connection.

### Utilities

#### `null(): NullMarker`
Returns a NullMarker sentinel for use in %set% to force SET col = NULL. Plain null skips the column.
- **Returns** `NullMarker` — Sentinel that QueryBuilder translates to literal NULL.

### Testing Hooks

#### `reset(): void`
Clears all pooled PDO connections. Forces re-connection from config on the next query.
- **Side effect:** Closes all pooled connections.

### Methods

#### `connectionCount(): int`
Returns the number of active pooled connections. :connection_count

#### `hasOpenTransaction(): bool`
Returns true if any pooled connection has an open transaction. :has_open_transaction

#### `rollbackAll(): void`
Rolls back any open transactions on all pooled connections. :rollback_all Safety net for worker mode: if a request exits with an uncommitted transaction, the next request must not inherit it. Called by WorkerReset::apply() between requests.

## `Skim\Db\Exceptions\DbException` — DbException

> RuntimeException wrapping PDO failures with the failed SQL string for debugging.

`exception` `db` `error-context`

**Source:** `src/Db/Exceptions/DbException.php` · **Layer:** `db` · **Lifecycle:** `thrown on PDO failure, caught by application code`

`DbException` extends `\RuntimeException` and prepends the failed SQL to the error message. All PDO calls in `Db::` are wrapped so that failures carry the originating query for log and debug output.

### Core Behavior
- Provides SQL context in exception message for debugging

### Constructor

#### `__construct(sql, previous)`
Builds the exception message by prepending the failed SQL to the PDO error.
- `$sql: string` (required) — The SQL statement that failed.
- `$previous: \Throwable` (required) — The underlying PDOException.

## `Skim\Db\Exceptions\NotFoundException` — NotFoundException

> RuntimeException thrown by Model::findOrFail() when a record is missing.

`exception` `orm` `404`

**Source:** `src/Db/Exceptions/NotFoundException.php` · **Layer:** `db` · **Lifecycle:** `thrown by findOrFail(), caught by controller or global error handler`

`NotFoundException` is thrown by `Model::findOrFail()` when no record matches the given primary key. Controllers catch it to return structured 404 responses.

### Core Behavior
- Provides a human-readable error message identifying the model and missing id

### Constructor

#### `__construct(model, id)`
Builds a "{model} with id '{id}' not found." message.
- `$model: string` (required) — Fully-qualified model class name.
- `$id: int` (required) — The primary key value that was searched.

## `Skim\Db\MerryModel` — MerryModel

> ORM layer extending model with eager/lazy relation loading and many-to-many pivot operations.

`orm` `relations` `eager-loading` `pivot` `n+1-safe`

**Source:** `src/Db/MerryModel.php` · **Layer:** `db` · **Lifecycle:** `extends Model lifecycle — relations cached per instance after first load`

`MerryModel` extends `model` with relation declarations (hasMany, hasOne, belongsTo, manyToMany) and eager/lazy loading. Relations are declared as static arrays for IDE navigation and explicit contracts. Eager loading via `with()` uses IN queries to prevent N+1 automatically.

### Core Behavior
- with() eager-loads using IN queries to prevent N+1
- load() lazy-loads on first access and caches
- attach/detach/sync manage manyToMany pivot tables
- dot-notation supports nested eager loading

### Warnings
- ⚠ detach() with empty $ids removes ALL pivot rows for this model
- ⚠ sync() detaches everything before re-attaching

### Eager Loading

#### `with(models, relations): array`
Eager-loads named relations onto a collection of models using IN queries. Supports dot-notation for nested eager loading. Returns the same array with relations populated.
- `$models: array` (required) — Collection of model instances.
- `$relations: string` (required) — Relation names. Use dot-notation for nesting (e.g. 'posts.comments').
- **Returns** `array` — Same models array with relations loaded.
- **Side effect:** Executes one SELECT query per relation level.

### Lazy Access

#### `load(name): mixed`
Returns a relation's value — lazy-loads on first access, returns cached value on subsequent calls. For eager-loaded relations, returns immediately.
- `$name: string` (required) — Relation name as declared in static arrays.
- **Returns** `mixed` — Model, array of models, or null depending on relation type.
- **Side effect:** May execute a SELECT query on first access.

### Pivot Operations

#### `attach(relation, ids): void`
Inserts pivot rows for the given IDs. INSERT IGNORE skips duplicates silently.
- `$relation: string` (required) — manyToMany relation name.
- `$ids: array` (required) — Related model IDs to attach.
- **Throws** `\InvalidArgumentException` — If relation is not declared as manyToMany.
- **Side effect:** Inserts rows into the pivot table.

#### `detach(relation, ids = []): void`
Deletes pivot rows for the given IDs. Empty $ids removes ALL pivot rows for this model.
- `$relation: string` (required) — manyToMany relation name.
- `$ids: array` (optional) — Specific IDs to detach. Empty = detach all.
- **Throws** `\InvalidArgumentException` — If relation is not declared as manyToMany.
- ⚠ Empty $ids removes ALL pivot rows for this model's foreign key
- **Side effect:** Deletes rows from the pivot table.

#### `sync(relation, ids): void`
Detaches all existing pivot rows, then attaches the given IDs. Result: pivot matches exactly $ids.
- `$relation: string` (required) — manyToMany relation name.
- `$ids: array` (required) — Exact set of related IDs to maintain.
- ⚠ Detaches ALL existing pivot rows before re-attaching
- **Side effect:** Deletes and inserts rows in the pivot table.

## `Skim\Db\Migration` — migration

> Abstract base class for SQL-first database migrations with up/down contract.

`abstract` `migration` `sql-first`

**Source:** `src/Db/Migration.php` · **Layer:** `db` · **Lifecycle:** `instantiated by Migrator::loadAll() via require, filename assigned from basename`

`migration` is the abstract base for all database migrations. Each migration file returns an anonymous class extending this base, implementing `up()` and `down()` with raw SQL strings. The migrator tracks applied migrations by filename in the `_migrations` table.

### Core Behavior
- SQL-first approach — no schema builder abstraction
- multi-statement SQL supported via semicolon splitting

### Migration Contract

#### `up(): string|array|callable`
Returns raw SQL, an array of SQL statements, or a callable receiving PDO to execute when migrating forward.
- **Returns** `string` — SQL string, one element per statement, or custom callable.

#### `down(): string|array|callable`
Returns raw SQL, an array, or a callable to reverse up(). Used by migrate:down and migrate:fresh. Optional — base default throws when $reversible is false.
- **Returns** `string` — SQL string, array, or callable to reverse the migration.

## `Skim\Db\MigrationExecutor` — MigrationExecutor

> Executes migration payloads (SQL or callables) with optional dry-run collection. Extracted from Migrator for isolated testing and pretend mode support.

**Source:** `src/Db/MigrationExecutor.php`

Executes migration payloads (SQL or callables) with optional dry-run collection. Extracted from Migrator for isolated testing and pretend mode support.

### Methods

#### `pretend(value): static`

#### `collected(): array`

#### `run(payload, connection): void`

## `Skim\Db\Migrator` — Migrator

> Tracks and executes SQL-first migrations with batch-based rollback. :class Use via CLI commands (migrate, migrate:down, migrate:fresh, migrate:status). State is stored in the `_migrations` table: filename + batch number. Each `migrate` call groups all pending migrations into one batch for atomic rollback targeting. Example: $m = new Migrator(basePath('migrations')); $ran = $m->run(); // ['2024_01_01_create_users.php', ...] $m->down(); // rollback last batch $m->status(); // [['filename' => ..., 'batch' => ..., 'status' => 'applied'], ...] Testing: Use test_db() SQLite :memory: — migrator creates its own tracking table.

**Source:** `src/Db/Migrator.php`

Tracks and executes SQL-first migrations with batch-based rollback. :class Use via CLI commands (migrate, migrate:down, migrate:fresh, migrate:status). State is stored in the `_migrations` table: filename + batch number. Each `migrate` call groups all pending migrations into one batch for atomic rollback targeting. Example: $m = new Migrator(basePath('migrations')); $ran = $m->run(); // ['2024_01_01_create_users.php', ...] $m->down(); // rollback last batch $m->status(); // [['filename' => ..., 'batch' => ..., 'status' => 'applied'], ...] Testing: Use test_db() SQLite :memory: — migrator creates its own tracking table.

### Methods

#### `__construct(migrationsDir, connection)`

#### `before(fn): static`
Register a hook fired before each migration runs. :before

#### `after(fn): static`
Register a hook fired after each migration runs. :after

#### `run(pretend, force): array`
Runs all pending migrations in filename-sorted order. :run

#### `down(steps, force, skipMissing): array`
Rolls back all migrations in the last batch. :down

#### `fresh(): void`
Drops all tables and re-runs all migrations from scratch. :fresh WARNING: DESTRUCTIVE — drops every table by running all down() methods in reverse order, then re-applies everything. Dev environments only.

#### `status(): array`
Returns the status of all known migrations. :status

## `Skim\Db\Model` — model

> Active record base class with dirty tracking, schema caching, guarded mass-assignment, and type casting.

`orm` `active-record` `dirty-tracking` `schema-cache` `guarded`

**Source:** `src/Db/Model.php` · **Layer:** `db` · **Lifecycle:** `instantiated via find/create/new, persisted via save(), schema cached per table for 1 hour`

`model` is the active record base class for all database-backed models. It provides find/create/save/delete operations with automatic dirty tracking for efficient UPDATE queries, schema caching for introspection, guarded mass-assignment protection, and configurable type casting.

### Core Behavior
- dirty tracking via setAttribute() records changed columns
- hydrateOne() uses setRaw() to bypass dirty tracking
- casts apply on both set and hydrate
- QueryScope provides fluent WHERE/ORDER/LIMIT chaining

### Drivers
| Driver | Mutable | Description |
|---|---|---|
| $table | no | Database table name. |
| $connection | no | Named DB connection from config/db.php. |
| $primary | no | Primary key column name. |
| $guarded | no | Columns excluded from mass-assignment. |
| $casts | no | Column type casting rules. |

### Warnings
- ⚠ all() defaults to limit 1000 — always paginate large tables
- ⚠ deleteWhere() has no limit — deletes ALL matching rows

### Finders

#### `find(id): ?static`
Finds a record by primary key. Returns null if not found — never throws.
- `$id: int` (required) — Primary key value.
- **Returns** `?static` — Hydrated model instance or null.

#### `findOrFail(id): static`
Finds a record by primary key. Throws NotFoundException if missing — use in controllers for 404 responses.
- `$id: int` (required) — Primary key value.
- **Returns** `static` — Hydrated model instance.
- **Throws** `NotFoundException` — When no record matches the primary key.

#### `findBy(col, val): ?static`
Finds the first record matching a column=value condition. Validates column name against injection. Returns null if no match.
- `$col: string` (required) — Column name (validated as identifier).
- `$val: mixed` (required) — Value to match.
- **Returns** `?static` — Hydrated model instance or null.
- **Throws** `\InvalidArgumentException` — If column name contains invalid characters.

#### `all(limit = 1000): array`
Returns all rows as hydrated models. Default limit of 1000 prevents accidental full-table scans.
- `$limit: int` (optional) — Maximum rows to return. Default 1000.
- **Returns** `array` — Array of hydrated model instances.

### Query Building

#### `where(conditions, params = []): QueryScope`
Returns a QueryScope for building filtered queries. Chain order/limit/paginate before terminal methods.
- `$conditions: string` (required) — SQL fragment or column=>value pairs.
- `$params: array` (optional) — PDO params when conditions is a string.
- **Returns** `QueryScope` — Fluent query builder scoped to this model.
- **Note:** #[\NoDiscard] — always capture or chain the return value.

#### `count(conditions = []): int`
Returns COUNT(*) for the table, optionally filtered by conditions.
- `$conditions: array` (optional) — Column=>value filter pairs.
- **Returns** `int` — Number of matching rows.

#### `raw(sql, params = []): array`
Runs raw query_gen SQL scoped to this model's connection. Returns hydrated model instances. Use for JOINs and subqueries.
- `$sql: string` (required) — SQL template with %placeholders%.
- `$params: array` (optional) — Placeholder values and :named params.
- **Returns** `array` — Array of hydrated model instances.

### Creation

#### `create(data): static`
Creates a new Model, mass-assigns non-guarded columns, and persists via save(). Guarded columns in $data are silently ignored.
- `$data: array` (required) — Column=>value pairs to assign.
- **Returns** `static` — Persisted model instance with assigned id.
- **Side effect:** Executes INSERT query.

### Persistence

#### `save(): void`
INSERT if no primary key set
- **Side effect:** Executes INSERT or UPDATE query.

### Deletion

#### `deleteWhere(conditions): int`
Deletes all rows matching the conditions. No limit — deletes ALL matching rows.
- `$conditions: array` (required) — Column=>value filter pairs.
- **Returns** `int` — Number of rows deleted.
- ⚠ No limit — deletes ALL matching rows. Use with specific conditions.
- **Side effect:** Executes DELETE query.

#### `delete(): void`
Deletes the current record from the database.
- **Throws** `\LogicException` — If model has no primary key set.
- **Side effect:** Executes DELETE query.

### Mass Assignment

#### `fill(data): void`
Assigns non-guarded attributes from an array. Guarded columns are silently skipped.
- `$data: array` (required) — Column=>value pairs to assign.

### Hydration

#### `hydrateOne(row): static`
Creates a model instance from a DB row. Bypasses guarded check and dirty tracking. Never call directly in application code.
- `$row: array` (required) — Associative array from DB fetch.
- **Returns** `static` — Clean hydrated model instance.

#### `hydrateMany(rows): array`
Bulk hydrates an array of DB rows into model instances.
- `$rows: array` (required) — Array of associative arrays.
- **Returns** `array` — Array of hydrated model instances.

### Schema

#### `schema(): array`
Returns column definitions from the DB schema. Cached for 1 hour. Supports MySQL, PostgreSQL, and SQLite.
- **Returns** `array` — Array of column definition arrays with Field, Type, Null keys.
- **Side effect:** Reads DB schema on first call, caches result.

#### `columnNames(): array`
Returns a flat array of column names from the cached schema.
- **Returns** `array` — Array of column name strings.

### Accessors

#### `toArray(): array`
Returns all current attributes as a plain associative array.
- **Returns** `array` — Model attributes.

#### `getTable(): string`
Returns the table name for this model.
- **Returns** `string` — Table name.

#### `getConnection(): string`
Returns the connection name for this model.
- **Returns** `string` — Connection name.

### Methods

#### `__get(name): mixed`

#### `__set(name, value): void`

#### `__isset(name): bool`

## `Skim\Db\NullMarker` — NullMarker

> Sentinel object that forces SET col = NULL in query_gen %set% placeholders.

`sentinel` `query_gen` `null-safe`

**Source:** `src/Db/NullMarker.php` · **Layer:** `db` · **Lifecycle:** `stateless value object, created per use`

`NullMarker` is a value object used exclusively in `%set%` placeholder arrays to force `SET col = NULL`. Plain PHP `null` skips the column entirely (partial-update pattern), while `Db::null()` produces a `NullMarker` that the query builder translates to literal `NULL`.

### Core Behavior
- QueryBuilder::buildSet() checks instanceof NullMarker to emit literal NULL

### Factory

#### `make(): self`
Returns the singleton NullMarker instance. Called internally by Db::null().
- **Returns** `self` — The NullMarker sentinel.
- **Note:** Always prefer `Db::null()` over calling `NullMarker::make()` directly.

## `Skim\Db\Pagination` — pagination

> Immutable result object for paginated queries with computed page navigation properties.

`value-object` `pagination` `immutable`

**Source:** `src/Db/Pagination.php` · **Layer:** `db` · **Lifecycle:** `created by QueryScope::paginate(), consumed by controllers and views`

`pagination` is an immutable value object returned by `QueryScope::paginate()`. It holds the current page of hydrated model instances, total count, and provides computed navigation properties via PHP 8.4 property hooks.

### Core Behavior
- Property hooks compute pages
- hasNext
- hasPrev on each access

### Constructor

#### `__construct(items, total, perPage, current)`
Stores the page of results and pagination metadata. All properties are readonly.
- `$items: array` (required) — Hydrated model instances for the current page.
- `$total: int` (required) — Total matching rows across all pages.
- `$perPage: int` (required) — Number of items per page.
- `$current: int` (required) — Current page number (1-indexed).

## `Skim\Db\QueryBuilder` — QueryBuilder

> Internal SQL template processor for query_gen %placeholder% substitution.

`internal` `query_gen` `sql-template` `null-safe`

**Source:** `src/Db/QueryBuilder.php` · **Layer:** `db` · **Lifecycle:** `stateless — called per query by Db:: methods`

`QueryBuilder` processes SQL templates with `%placeholder%` tokens into executable SQL and PDO parameter arrays. It is internal to the `db` facade — not part of the public SKIM API. Unused placeholders are stripped silently, enabling dynamic queries without conditionals.

### Core Behavior
- build() processes placeholders in fixed order: set, values, where, order_by, group_by, limit, offset
- buildWhere() supports flat and nested and/or structures
- interpolate() sorts by key length to avoid partial replacements

### Warnings
- ⚠ interpolate() output is NOT safe to execute — for debug display only

### Core Processing

#### `build(sql, params): array`
Processes a SQL template with %placeholders% into executable SQL and PDO params. Unused placeholders are stripped. Null :named params are excluded.
- `$sql: string` (required) — SQL template with %placeholders%.
- `$params: array` (required) — Placeholder values and :named params.
- **Returns** `array{string, array}` — [built SQL, PDO params] ready for PDO::prepare + execute.

### Clause Builders

#### `buildWhere(conditions): string`
Builds a WHERE clause from flat or nested and/or conditions. Returns empty string when conditions are empty.
- `$conditions: array` (required) — Flat list ['a = :a'] or nested ['or' => [...], 'and' => [...]].
- **Returns** `string` — WHERE clause body (without WHERE keyword) or empty string.

#### `buildSet(data): array`
Builds a SET clause for UPDATE. Null skips the column, NullMarker forces SET col = NULL.
- `$data: array` (required) — Column => value pairs.
- **Returns** `array{string, array}` — [SET clause string, PDO params].

#### `buildValues(data): array`
Builds INSERT column list and VALUES placeholders.
- `$data: array` (required) — Column => value pairs.
- **Returns** `array{string, array}` — [(col1, col2) VALUES (:col1, :col2), PDO params].

### Debug

#### `interpolate(sql, params): string`
Substitutes PDO params into SQL for debug display. NOT safe to execute — values are not driver-escaped.
- `$sql: string` (required) — Built SQL with :named placeholders.
- `$params: array` (required) — PDO param values.
- **Returns** `string` — Human-readable SQL with values substituted.
- ⚠ Output is NOT safe to execute — for profiler and debug:true display only

## `Skim\Db\QueryScope` — QueryScope

> Fluent query builder scoped to a model class with WHERE/ORDER/LIMIT/OFFSET collection and terminal execution.

`fluent` `builder` `orm` `no-discard`

**Source:** `src/Db/QueryScope.php` · **Layer:** `db` · **Lifecycle:** `created by Model::where(), consumed by terminal method call`

`QueryScope` collects WHERE, ORDER BY, LIMIT, and OFFSET clauses without executing any SQL. Terminal methods (`all()`, `first()`, `count()`, `paginate()`) compile the collected state into a query_gen SQL template and execute it via `Db::query()`.

### Core Behavior
- Collects conditions without DB access until terminal method
- Array conditions auto-generate unique placeholders to avoid collisions
- Terminal methods compile to query_gen SQL and execute

### Warnings
- ⚠ Discarding the return of where()/order()/limit()/offset() loses that clause — always capture or chain

### Clause Methods

#### `where(condition, params = []): static`
Adds a WHERE condition. Array form ['col' => 'val'] generates unique placeholders automatically. String form 'col = :col' requires matching $params. Repeated calls join with AND.
- `$condition: string` (required) — SQL fragment or column=>value equality pairs.
- `$params: array` (optional) — PDO params when $condition is a string fragment.
- **Returns** `static` — Returns $this for chaining.
- **Note:** #[\NoDiscard] — always capture or chain the return value.

#### `order(clause): static`
Sets ORDER BY clause. Last call wins — does not accumulate.
- `$clause: string` (required) — SQL ORDER BY expression like 'created_at DESC'.
- **Returns** `static` — Returns $this for chaining.

#### `limit(n): static`
Sets LIMIT. Last call wins.
- `$n: int` (required) — Maximum rows to return.
- **Returns** `static` — Returns $this for chaining.

#### `offset(n): static`
Sets OFFSET. Last call wins.
- `$n: int` (required) — Number of rows to skip.
- **Returns** `static` — Returns $this for chaining.

### Terminal Methods

#### `all(): array`
Executes the collected query and returns an array of hydrated model instances.
- **Returns** `array` — Array of hydrated model instances.
- **Side effect:** Executes a SELECT query.

#### `first(): mixed`
Executes the query with LIMIT 1 and returns the first hydrated model or null.
- **Returns** `mixed` — Hydrated model instance or null.
- **Side effect:** Executes a SELECT query.

#### `count(): int`
Executes COUNT(*) with current WHERE conditions. Ignores limit and offset.
- **Returns** `int` — Number of matching rows.
- **Side effect:** Executes a SELECT COUNT(*) query.

#### `paginate(page = 1, perPage = 20): pagination`
Executes count() and a limited all() to produce a pagination value object with items, total, and navigation properties.
- `$page: int` (optional) — Current page number (1-indexed). Default 1.
- `$perPage: int` (optional) — Items per page. Default 20.
- **Returns** `pagination` — Immutable pagination value object.
- **Side effect:** Executes two queries — COUNT(*) and SELECT with LIMIT/OFFSET.

### Internal

#### `toBuilderParams(): array`
Exports collected conditions and params in QueryBuilder::build() format. Used by Model::deleteWhere().
- **Returns** `array` — Params array compatible with QueryBuilder::build().

### Methods

#### `__construct(modelClass)`

## `Skim\Dev\DevTheme` — DevTheme

> Shared CSS design system for dev tool HTML output — dark theme tokens, component classes, and toolbar styles.

`dev` `debug` `css` `theme` `design-system`

**Source:** `src/Dev/DevTheme.php` · **Layer:** `dev` · **Lifecycle:** `stateless — returns static CSS strings on each call`

`DevTheme` provides the shared CSS design system used by all dev tool renderers (error page, toolbar, future debug panels). It returns CSS strings for embedding in `<style>` blocks — no external .css files needed.

### Core Behavior
- Provides CSS custom properties for colors, typography, and spacing
- Provides shared component classes (kv-grid, badges, buttons, code-box, tabs, toast)
- Provides toolbar-scoped CSS that avoids host page conflicts

### CSS Providers

#### `css(): string`
Returns the complete shared CSS for full-page dev tools — custom properties, base reset, scrollbar, and shared component classes (kv-grid, badges, buttons, code-box, tabs, toast).
- **Returns** `string` — Complete CSS string for embedding in a <style> block.

#### `toolbarCss(): string`
Returns toolbar-scoped CSS with --tb- prefixed variables and #skim-tb selectors to prevent style leakage into the host page.
- **Returns** `string` — Scoped CSS string for the debug toolbar.

### Utilities

#### `iconFont(): string`
Returns the Tabler Icons webfont CDN <link> tag used by both error page and toolbar.
- **Returns** `string` — HTML <link> tag for Tabler Icons CDN.

## `Skim\Dev\DevView` — DevView

> Standalone template renderer for dev tools — zero framework dependencies, supports layouts, slots, and partial includes.

`dev` `debug` `template` `renderer` `standalone`

**Source:** `src/Dev/DevView.php` · **Layer:** `dev` · **Lifecycle:** `instantiated per render() call, stateless between calls`

`DevView` is a minimal, self-contained template renderer for dev tool HTML output. It supports layouts, named slots, and partial includes with the same API patterns as `Skim\View\Template`, but has zero dependencies on any framework module — ensuring it works even when the view system, config, or profiler is broken.

### Core Behavior
- Two-pass layout: child renders first capturing slots, then layout renders with slot() access
- Non-slot output auto-captured as content slot
- Partials via include() with isolated data context

### Warnings
- ⚠ Throws RuntimeException if template file not found — ErrorPage::render() must catch this and fall back to inline HTML

### Rendering

#### `render(template, data = []): string`
Renders a template file with data extracted as locals. When the template declares a layout, wraps output in the layout with slot access. Returns the final HTML string.
- `$template: string` (required) — Template path relative to src/dev/views/, no .php extension needed.
- `$data: array` (optional) — Key-value pairs extracted as local variables inside the template.
- **Returns** `string` — Rendered HTML string.
- **Throws** `\RuntimeException` — If the template .php file does not exist in src/dev/views/.
- **Side effect:** uses output buffering
- **Side effect:** extract() creates local variables

#### `renderFile(template): string`
Renders a single template file by resolving the .php extension, extracting data as local variables, and including the file within an output buffer.
- `$template: string` (required) — Template path relative to views root, no extension.
- **Returns** `string` — Raw rendered HTML without layout wrapping.
- **Throws** `\RuntimeException` — If the template file does not exist.
- **Side effect:** uses extract() and ob_start/ob_get_clean

### Template API

#### `include(template, extra = []): string`
Renders a partial template within the current data context. The partial does not inherit the parent's layout.
- `$template: string` (required) — Template path relative to src/dev/views/, no extension.
- `$extra: array` (optional) — Additional data merged for this partial only.
- **Returns** `string` — Rendered partial HTML.

### Layout System

#### `layout(name): void`
Declares the layout template to wrap this template's output. Must be called before any output.
- `$name: string` (required) — Layout template path relative to src/dev/views/, no extension.

#### `start(name): void`
Starts capturing output into a named slot. Must be paired with end().
- `$name: string` (required) — Slot identifier used by the layout to retrieve content.
- **Side effect:** starts output buffering via ob_start()

#### `end(): void`
Ends the slot capture started by start(). Stores captured output in the named slot.
- **Throws** `\LogicException` — If called without a matching start().
- **Side effect:** ends output buffering via ob_get_clean()

#### `slot(name): string`
Returns captured slot content for use inside layout files. Returns empty string if the slot was never captured.
- `$name: string` (required) — Slot identifier matching a previous start()/end() pair.
- **Returns** `string` — Captured slot HTML or empty string.

### Methods

#### `shortPath(file): string`
Strips the project root from a file path, falling back to basename. :short_path Used by dev tool templates to display readable file locations.

#### `valueClass(type): string`
Returns a CSS class name representing a PHP value type. :value_class Used by dev tool templates to color-code argument values in stack frames.

## `Skim\Dev\Docs\Commands\DocsCommand` — DocsCommand

> Umbrella CLI command that runs the full docs generation pipeline (extract → llm → site) with optional watch mode.

`cli` `docs` `pipeline` `watch-mode`

**Source:** `src/Dev/Docs/Commands/DocsCommand.php` · **Layer:** `dev` · **Lifecycle:** `instantiated by CLI router, runs synchronously, exits with pipeline status`

`DocsCommand` chains the three sub-commands (extract, llm, site) into a single invocation. With `--watch`, it polls source directories every 2 seconds and triggers a full rebuild when any .php file mtime changes.

### Core Behavior
- Runs extract → llm → site in sequence
- Watch mode polls scanPaths every 2 seconds and rebuilds on mtime change

### Warnings
- ⚠ Watch mode runs indefinitely until interrupted

### Pipeline

#### `handle(): int`
Dispatches to watch mode when --watch flag is set, otherwise runs a one-shot full build pipeline.
- **Returns** `int` — 0 on success, first non-zero exit code from any sub-command on failure.

### Watch Mode

#### `mtimeHash(paths): string`
Computes an md5 hash of sorted file:mtimes pairs across all .php files in the given directories. Used to detect any file change between polling intervals.
- `$paths: string[]` (required) — Directories to scan recursively for .php files.
- **Returns** `string` — MD5 hash representing the current mtime state of all .php files.

## `Skim\Dev\Docs\Commands\DocsExtractCommand` — DocsExtractCommand

> CLI command that scans PHP source files, extracts @ai.* annotations via AST, and writes llm.json.

`cli` `docs` `extraction` `ast`

**Source:** `src/Dev/Docs/Commands/DocsExtractCommand.php` · **Layer:** `dev` · **Lifecycle:** `instantiated by CLI router or DocsCommand, runs synchronously`

`DocsExtractCommand` is the first step in the docs pipeline. It uses `ProjectScanner` to walk source paths, runs `ClassExtractor` on each .php file, enriches the result with extension capability data from `ExtRegistry`, and writes the output to llm.json via `JsonEmitter`.

### Core Behavior
- Scans configured or overridden source paths for .php files
- Extracts annotations via AST using ClassExtractor
- Enriches output with extension capabilities from ExtRegistry

### Pipeline

#### `handle(): int`
Scans source paths, extracts class annotations, enriches with extension data, and writes llm.json. Returns 1 when the source directory is missing or any step throws.
- **Returns** `int` — 0 on success, 1 on scan or write failure.
- **Side effect:** writes llm.json to disk

## `Skim\Dev\Docs\Commands\DocsLlmCommand` — DocsLlmCommand

> CLI command that reads llm.json and writes a compact llm.md Markdown file for LLM context windows.

`cli` `docs` `markdown` `llm`

**Source:** `src/Dev/Docs/Commands/DocsLlmCommand.php` · **Layer:** `dev` · **Lifecycle:** `instantiated by CLI router or DocsCommand, runs synchronously`

`DocsLlmCommand` converts the structured llm.json into a single grouped Markdown file (llm.md) that fits within LLM context windows. Optionally prepends the framework-level llm.md for full API context.

### Core Behavior
- Loads llm.json via JsonEmitter
- Delegates Markdown rendering to LlmMdEmitter
- Optionally prepends framework llm.md

### Pipeline

#### `handle(): int`
Loads llm.json, optionally prepends framework context, and writes llm.md. Returns 1 when llm.json is missing or write fails.
- **Returns** `int` — 0 on success, 1 on failure.
- **Side effect:** writes llm.md to disk

## `Skim\Dev\Docs\Commands\DocsSiteCommand` — DocsSiteCommand

> CLI command that reads llm.json and generates one MDX file per class for the Starlight documentation site.

`cli` `docs` `mdx` `starlight`

**Source:** `src/Dev/Docs/Commands/DocsSiteCommand.php` · **Layer:** `dev` · **Lifecycle:** `instantiated by CLI router or DocsCommand, runs synchronously`

`DocsSiteCommand` converts the structured llm.json into component-style MDX files suitable for a Starlight/Astro documentation site. Each class becomes one .mdx file

### Core Behavior
- Loads llm.json via JsonEmitter
- Delegates MDX generation to MdxEmitter
- Reports count of files written

### Pipeline

#### `handle(): int`
Loads llm.json and generates one MDX file per class. Returns 1 when llm.json is missing or write fails.
- **Returns** `int` — 0 on success, 1 on failure.
- **Side effect:** writes MDX files to disk

## `Skim\Dev\Docs\Commands\DocsValidateCommand` — DocsValidateCommand

> CLI command that reports @ai.* annotation coverage and fails the build when below the configured threshold.

`cli` `docs` `validation` `ci`

**Source:** `src/Dev/Docs/Commands/DocsValidateCommand.php` · **Layer:** `dev` · **Lifecycle:** `instantiated by CLI router, runs synchronously`

`DocsValidateCommand` scans all source files, counts public methods with at least one @ai.* annotation, and compares the ratio against a configurable threshold. Use in CI pipelines or pre-commit hooks to prevent annotation regressions.

### Core Behavior
- Scans all configured source paths via ProjectScanner
- Prints a table of unannotated methods
- Compares coverage ratio against threshold

### Pipeline

#### `handle(): int`
Scans all source files, reports unannotated public methods in a table, and exits 1 when coverage is below the configured threshold.
- **Returns** `int` — 0 if coverage meets or exceeds threshold, 1 if below threshold or scan fails.
- **Side effect:** prints coverage report and missing-methods table to stdout

## `Skim\Dev\Docs\Commands\McpServeCommand` — McpServeCommand

> CLI command that launches the stdio MCP server for LLM tool integration with Claude Code, Cursor, etc.

`cli` `mcp` `stdio` `llm`

**Source:** `src/Dev/Docs/Commands/McpServeCommand.php` · **Layer:** `dev` · **Lifecycle:** `instantiated by CLI router, runs until stdin closes or Ctrl+C`

`McpServeCommand` spawns the `skim-mcp` native binary as a child process communicating over stdio. It validates that llm.json and the binary exist before launching, and forwards the child's exit code.

### Core Behavior
- Validates llm.json and skim-mcp binary exist
- Spawns skim-mcp binary via passthru
- Forwards child process exit code

### Pipeline

#### `handle(): int`
Validates prerequisites and spawns the MCP server as a child process over stdio. Returns 1 when llm.json or the server script is missing.
- **Returns** `int` — Exit code from the child MCP server process, or 1 on pre-flight failure.
- **Side effect:** spawns long-running child process

## `Skim\Dev\Docs\Emitter\JsonEmitter` — JsonEmitter

> Serializes extracted class metadata to llm.json and loads it back — the single source of truth for all doc outputs.

`emitter` `json` `docs` `no-framework-deps`

**Source:** `src/Dev/Docs/Emitter/JsonEmitter.php` · **Layer:** `dev` · **Lifecycle:** `instantiated per-use by commands, no state retained`

`JsonEmitter` is the serialization boundary between the AST extraction pipeline and all downstream consumers (LlmMdEmitter, MdxEmitter, mcp_server). It writes pretty-printed JSON with optional extension capability enrichment.

### Core Behavior
- Serializes ExtractedClass[] to structured JSON
- Optionally enriches with extension capabilities
- Loads and validates llm.json for downstream consumers

### Serialization

#### `emit(classes, outputPath, capabilityMap = [], installedExtensions = []): void`
Serializes ExtractedClass[] to pretty-printed JSON at the given path. Creates parent directories if needed. Optionally includes extension capability data and installed extension names as top-level sections.
- `$classes: ExtractedClass[]` (required) — Class records to serialize.
- `$outputPath: string` (required) — Filesystem path for the output JSON file.
- `$capabilityMap: array` (optional) — Extension capability details written as top-level "capabilities" section when non-empty.
- `$installedExtensions: string[]` (optional) — Installed extension names written under "extensions.installed" when non-empty.
- **Throws** `\RuntimeException` — When json_encode fails or the file cannot be written.
- **Side effect:** writes JSON file to disk
- **Side effect:** creates parent directories

### Deserialization

#### `load(path): array`
Reads and decodes llm.json from the given path. Throws when the file is missing, unreadable, or contains invalid JSON.
- `$path: string` (required) — Path to the llm.json file.
- **Returns** `array` — Decoded JSON data as an associative array.
- **Throws** `\RuntimeException` — When file is missing, unreadable, or contains invalid JSON.

## `Skim\Dev\Docs\Emitter\LlmMdEmitter` — LlmMdEmitter

> Converts decoded llm.json data into compact grouped Markdown optimized for LLM context windows.

`emitter` `markdown` `llm` `docs`

**Source:** `src/Dev/Docs/Emitter/LlmMdEmitter.php` · **Layer:** `dev` · **Lifecycle:** `instantiated per-use by DocsLlmCommand, no state retained`

`LlmMdEmitter` transforms the structured llm.json array into a single Markdown file with class sections grouped by section_order. It renders compact signatures, param tables, and warning annotations in a format that fits within LLM context windows.

### Core Behavior
- Renders class sections with symbol, badges, metadata, and intro
- Groups methods by section_order
- Optionally prepends framework llm.md content
- Strips markers from output

### Emit

#### `emit(data, outputPath, frameworkLlmMd = null): void`
Accepts decoded llm.json array and writes compact class sections grouped by section_order. Prepends framework llm.md content when the optional path is provided and the file exists.
- `$data: array` (required) — Decoded llm.json array with 'classes' and 'generated_at' keys.
- `$outputPath: string` (required) — Filesystem path for the output Markdown file.
- `$frameworkLlmMd: ?string` (optional) — Optional path to framework-level llm.md to prepend.
- **Throws** `\RuntimeException` — When the output directory cannot be created or the file cannot be written.
- **Side effect:** writes Markdown file to disk

## `Skim\Dev\Docs\Emitter\MdxEmitter` — MdxEmitter

> Generates component-style MDX files from llm.json class records for the Starlight documentation site.

`emitter` `mdx` `starlight` `docs`

**Source:** `src/Dev/Docs/Emitter/MdxEmitter.php` · **Layer:** `dev` · **Lifecycle:** `instantiated per-use by DocsSiteCommand, no state retained`

`MdxEmitter` transforms decoded llm.json data into one .mdx file per class using Starlight-compatible components (ApiBadge, ApiMethod, ApiParam, ApiThrows, WarningBox, NoteBox, ScopeBox, AiContext, LifecycleFlow). Handles duplicate class names and filename collisions.

### Core Behavior
- Renders frontmatter with title and description
- Emits ApiBadge, WarningBox, ScopeBox, and AiContext components
- Groups methods by section_order
- Escapes MDX special characters outside code spans
- Organizes output into namespace subdirectories
- Generates index.mdx landing page

### Emit

#### `emit(data, outputDir): int`
Accepts decoded llm.json array and writes one MDX file per class into namespace subdirectories under the output directory. Also writes an index.mdx landing page. Returns the count of files written. Handles duplicate class names and filename collisions. Cleans stale .mdx files before writing.
- `$data: array` (required) — Decoded llm.json array with 'classes' key.
- `$outputDir: string` (required) — Directory to write .mdx files into. Created if it does not exist.
- **Returns** `int` — Number of MDX files written (including index.mdx).
- **Throws** `\RuntimeException` — When a file cannot be written.
- **Side effect:** writes .mdx files to disk
- **Side effect:** creates output directory
- **Side effect:** cleans stale .mdx files recursively
- **Side effect:** creates namespace subdirectories

## `Skim\Dev\Docs\Extractor\AnnotationParser` — AnnotationParser

> Parses PHPDoc blocks, inline comments, and hash blocks to extract @ai.* tags into typed arrays.

`extractor` `parser` `annotations` `no-framework-deps`

**Source:** `src/Dev/Docs/Extractor/AnnotationParser.php` · **Layer:** `dev` · **Lifecycle:** `instantiated per-use by ClassVisitor, no state retained between calls`

`AnnotationParser` is the low-level parsing engine for the docs extraction pipeline. It handles three input formats (PHPDoc, inline //, and hash blocks) and coerces values into PHP types (arrays, records, booleans).

### Core Behavior
- Parses PHPDoc @ai.* tags with continuation line support
- Parses inline // comments with summary extraction
- Parses hash blocks with nested bracket/brace awareness
- Coerces values to PHP types

### Drivers
| Driver | Mutable | Description |
|---|---|---|
| $strict | yes | When true, throws UnexpectedValueException on unknown keys. Default false. |

### Parsing

#### `parse(docblock): array`
Accepts a raw docblock string and extracts all @ai.* and tags into an associative array keyed by tag suffix. Each value is an array of strings. Continuation lines starting with whitespace are appended to the previous tag.
- `$docblock: string` (required) — Raw docblock string, with or without /** delimiters.
- **Returns** `array<string, array<string>>` — Tag values keyed by tag suffix (e.g. 'contract', 'invariant').

#### `parseInline(source): array`
Parses inline // comments, extracting @ai.* tags and capturing preceding non-tag lines as a 'summary' key. Stops at the first non-// line.
- `$source: string` (required) — Raw source lines starting with //.
- **Returns** `array<string, mixed>` — Tags plus 'summary' key containing preceding comment text.

#### `parseHashAi(source): array`
Parses hash annotation blocks. Detects `:{target}` section headers (stored as __target) and ` key: value` pairs split by semicolons. Nested brackets and braces are preserved during splitting.
- `$source: string` (required) — One or more lines starting with .
- **Returns** `array<string, mixed>` — Parsed key-value pairs, with __target for section headers.
- **Throws** `\UnexpectedValueException` — When $strict is true and an unknown key is encountered.

### Value Coercion

#### `parseBracketList(value): array`
Parses a bracket-delimited list string into an array of coerced values. Supports both comma and semicolon delimiters.
- `$value: string` (required) — Bracket-delimited string like `[a; b; c]`.
- **Returns** `array` — Array of coerced values.

#### `parseRecord(value): array`
Parses a pipe-delimited record string `{key: value | key: value}` into an associative array with coerced values.
- `$value: string` (required) — Record string with optional braces.
- **Returns** `array` — Associative array of coerced values.

#### `coerceValue(value): mixed`
Coerces a string annotation value into its PHP equivalent. Bracket lists become arrays, brace records become associative arrays, and true/false/yes/no/1/0 become booleans.
- `$value: string` (required) — Raw string value from an annotation.
- **Returns** `mixed` — Coerced PHP value (string, bool, array).

### Utilities

#### `splitTopLevel(value, delimiter): array`
Splits a string at top-level delimiters only, preserving content inside square brackets and curly braces. Tracks nesting depth to avoid splitting nested structures.
- `$value: string` (required) — String to split.
- `$delimiter: string` (required) — Single-character delimiter.
- **Returns** `array<string>` — Trimmed parts split at top-level delimiters only.

#### `extractSummary(docblock): string`
Extracts the first non-tag, non-empty lines from a docblock as the summary sentence. Stops at the first @tag or line.
- `$docblock: string` (required) — Raw docblock string.
- **Returns** `string` — Summary text, or empty string if none found.

### Architecture

#### `stripLines(docblock): array`
Strips PHPDoc delimiters (/** and */) and leading * characters from each line, returning trimmed content lines.

### Methods

#### `extractExamples(docblock): array`
Extracts Example: blocks from a raw docblock. :extract_examples

## `Skim\Dev\Docs\Extractor\ClassExtractor` — ClassExtractor

> Extracts documentation metadata from a single PHP file via nikic/php-parser AST traversal.

`extractor` `ast` `php-parser` `docs`

**Source:** `src/Dev/Docs/Extractor/ClassExtractor.php` · **Layer:** `dev` · **Lifecycle:** `instantiated per-use by ProjectScanner, parser created once in constructor`

`ClassExtractor` parses a single PHP file using nikic/php-parser, delegates AST traversal to `ClassVisitor`, and returns an `ExtractedClass` value object. It silently returns null for non-class files, unreadable files, and parse errors.

### Core Behavior
- Parses PHP source via nikic/php-parser
- Delegates AST traversal to ClassVisitor
- Silently skips files that cannot be processed

### Extraction

#### `extract(file): ExtractedClass|null`
Parses a PHP file and extracts documentation metadata into an ExtractedClass value object. Returns null when the file has no class, cannot be read, or has a parse error.
- `$file: string` (required) — Absolute path to the PHP file to extract.
- **Returns** `ExtractedClass` — Extracted metadata, or null on skip/failure.

### Methods

#### `__construct()`

## `Skim\Dev\Docs\Extractor\ClassVisitor` — ClassVisitor

> Internal AST visitor that captures class-level and method-level @ai.* annotations from PHPDoc, inline comments, and detached blocks.

`extractor` `ast` `visitor` `internal`

**Source:** `src/Dev/Docs/Extractor/ClassVisitor.php` · **Layer:** `dev` · **Lifecycle:** `created per-file by ClassExtractor, single-use`

`ClassVisitor` is the AST traversal engine used by `ClassExtractor`. It visits namespace and class nodes, merges annotations from three sources (PHPDoc, inline //, detached blocks), and builds `ExtractedClass` and `ExtractedMethod` value objects.

### Core Behavior
- Merges tags from PHPDoc, inline comments, and detached blocks
- Builds method signatures from AST type nodes
- Falls back to role tag when summary is empty
- Parses detached blocks from file bottom

### Traversal

#### `enterNode(node): null`
Visits each AST node. Tracks namespace context and captures the first non-anonymous class with all its annotated methods. Merges tags from PHPDoc, inline comments, trailing comment blocks, and detached blocks.

### Extraction

#### `extractMethod(method, owner, tags): ExtractedMethod`
Builds an ExtractedMethod from a ClassMethod AST node and its merged annotation tags. Constructs the method signature string from AST type nodes.

#### `parseDetachedBlocks(): array`
Reads the source file and parses all detached blocks at the bottom into class-level and method-level tag arrays. Uses :{target} headers to determine section boundaries.

### Value Helpers

#### `rawValue(tags, key): mixed`
Returns the raw tag value for a key, unwrapping single-element list arrays to their scalar value.

#### `scalarValue(tags, key, default = ''): string`
Returns a scalar string from tags with optional default fallback.

#### `listValue(tags, key): array`
Returns a list value from tags, parsing bracket-delimited strings when needed.

#### `recordValue(tags, key): array`
Returns a record value from tags, parsing pipe-delimited record strings when needed.

### Architecture

#### `typeToString(type): string`
Converts a PhpParser type node (Identifier, Name, Nullable, Union, Intersection) to its PHP string representation.

### Methods

#### `__construct(annotations, file)`

## `Skim\Dev\Docs\Extractor\ProjectScanner` — ProjectScanner

> Walks configured scan paths, runs ClassExtractor on each .php file, and returns a flat ExtractedClass[] collection.

`extractor` `scanner` `docs` `recursive`

**Source:** `src/Dev/Docs/Extractor/ProjectScanner.php` · **Layer:** `dev` · **Lifecycle:** `instantiated per-use by commands, extractor created once in constructor`

`ProjectScanner` is the top-level entry point for the docs extraction pipeline. It walks directories, finds .php files, and delegates extraction to `ClassExtractor`. Supports both config-driven and explicit path scanning.

### Core Behavior
- Reads scanPaths from config/docs.php
- Recursively finds .php files in each path
- Runs ClassExtractor on each file
- Discards null results

### Scanning

#### `scan(): array`
Reads scanPaths from config/docs.php, recursively finds all .php files in each path, runs ClassExtractor on each, and returns a flat ExtractedClass[] with nulls discarded.
- **Returns** `ExtractedClass[]` — One entry per class found across all scan paths.

#### `scanPaths(paths): array`
Scans the given explicit paths instead of config scan_paths. Same null-discard behaviour as scan(). Used by commands that override paths via --source flag.
- `$paths: string[]` (required) — Directories to scan recursively for .php files.
- **Returns** `ExtractedClass[]` — One entry per class found.

### Architecture

#### `phpFiles(dir): iterable`
Yields absolute paths of all .php files under the given directory, recursively, using RecursiveIteratorIterator.

### Methods

#### `__construct()`

## `Skim\Dev\Docs\Value\DocsGenerationPaths` — DocsGenerationPaths

> Resolves CLI --source and --output flag overrides against config/docs.php defaults for docs generation paths.

`value` `docs` `paths` `cli`

**Source:** `src/Dev/Docs/Value/DocsGenerationPaths.php` · **Layer:** `dev` · **Lifecycle:** `instantiated per-command via fromFlags(), immutable after construction`

`DocsGenerationPaths` encapsulates the resolution logic for docs generation file paths. It merges CLI flag overrides with config/docs.php defaults, handling relative-to-absolute path resolution.

### Core Behavior
- Resolves scanPaths from --source or config
- Resolves json_path, llmMdPath, mdx_dir from --output or config
- Detects absolute vs relative paths

### Construction

#### `__construct(sourceDir = null, outputDir = null)`
Stores optional CLI source/output overrides. Null values preserve config/docs.php defaults.
- `$sourceDir: ?string` (optional) — Override for docs.scan_paths; null preserves config default.
- `$outputDir: ?string` (optional) — Override for all output paths; null preserves config defaults.

#### `fromFlags(flags): self`
Builds a DocsGenerationPaths from CLI flags, reading 'source' and 'output' keys. Empty strings are treated as null.
- `$flags: array` (required) — CLI flags array from command.

### Path Resolution

#### `scanPaths(): array`
Returns the explicit source override as a single-element array, or falls back to config('docs.scan_paths'). Relative paths resolve from basePath().
- **Returns** `string[]` — Directories to scan for .php files.

#### `hasSourceOverride(): bool`
Returns true when --source was supplied, indicating the caller should use scanPaths() instead of the default config scan.
- **Returns** `bool` — True if --source flag was provided.

#### `jsonPath(): string`
Returns the llm.json output path. When --output is set, returns DIR/llm.json
- **Returns** `string` — Absolute or relative path to llm.json.

#### `llmMdPath(): string`
Returns the llm.md output path. When --output is set, returns DIR/llm.md
- **Returns** `string` — Absolute or relative path to llm.md.

#### `mdxDir(): string`
Returns the MDX output directory. When --output is set, returns the resolved output dir
- **Returns** `string` — Directory path for MDX file output.

## `Skim\Dev\Docs\Value\ExtractedClass` — ExtractedClass

> Readonly value object representing a single extracted class with all annotation metadata from @ai.* tags.

`value` `readonly` `docs` `no-framework-deps`

**Source:** `src/Dev/Docs/Value/ExtractedClass.php` · **Layer:** `dev` · **Lifecycle:** `created once by ClassExtractor, never mutated, serialized by emitters`

`ExtractedClass` is the immutable data carrier between the AST extraction pipeline and all downstream emitters. It holds every annotation field extracted from a single PHP class file.

### Core Behavior
- Holds all annotation fields from class-level and method-level tags
- Serializes to JSON-safe array via toArray()
- Derives source_path from file path when not explicitly set

### Construction

#### `__construct(className, namespace, file)`
Creates an immutable value object with all extracted annotation fields. All fields except className, namespace, and file default to empty strings or empty arrays.

### Serialization

#### `toArray(): array`
Serializes to a plain associative array suitable for json_encode. Falls back to derived values for symbol, title, description, intro, and source_path when explicit values are empty.
- **Returns** `array` — JSON-safe associative array with all class and method data.

#### `annotatedMethodCount(): int`
Returns the count of public methods that have at least one @ai.* tag across any annotation field.
- **Returns** `int` — Number of annotated methods.

## `Skim\Dev\Docs\Value\ExtractedMethod` — ExtractedMethod

> Readonly value object representing a single extracted public method with all annotation metadata.

`value` `readonly` `docs` `no-framework-deps`

**Source:** `src/Dev/Docs/Value/ExtractedMethod.php` · **Layer:** `dev` · **Lifecycle:** `created once by ClassVisitor, held inside ExtractedClass, serialized by emitters`

`ExtractedMethod` is the method-level data carrier within `ExtractedClass`. It holds every annotation field extracted from a single method's PHPDoc, inline comments, and detached blocks.

### Core Behavior
- Holds all annotation fields from method-level tags
- Serializes to JSON-safe array via toArray()
- Backward-compatible with legacy contracts/throws fields

### Construction

#### `__construct(name, signature)`
Creates an immutable value object with all extracted method annotation fields. Only name and signature are required

### Serialization

#### `toArray(): array`
Serializes to a plain associative array suitable for json_encode. Falls back to contracts[0] for contract and converts legacy throws to throws_details format.
- **Returns** `array` — JSON-safe associative array with all method annotation data.

## `Skim\Dev\ErrorPage` — ErrorPage

> Developer-friendly error page rendered when APP_DEBUG=true. :class Use only via the exception handler registered in App::run(). Delegates HTML generation to dev_view templates with a fallback to inline HTML if the template system itself fails. Never expose in production. Example: // Registered by App::run() when APP_DEBUG=true: set_exception_handler(fn(\Throwable $e) => ErrorPage::render($e)); Testing: Call render() directly with a test Throwable; output goes to stdout.

**Source:** `src/Dev/ErrorPage.php`

Developer-friendly error page rendered when APP_DEBUG=true. :class Use only via the exception handler registered in App::run(). Delegates HTML generation to dev_view templates with a fallback to inline HTML if the template system itself fails. Never expose in production. Example: // Registered by App::run() when APP_DEBUG=true: set_exception_handler(fn(\Throwable $e) => ErrorPage::render($e)); Testing: Call render() directly with a test Throwable; output goes to stdout.

### Methods

#### `render(e): void`
Renders a full HTML error page to stdout using dev_view templates. :render Falls back to minimal inline HTML if template rendering fails, ensuring the developer always sees the error even when the template system breaks.

## `Skim\Dev\IdeLink` — IdeLink

> Builds deep-link URLs that open a file:line in the developer's editor — phpstorm (default), vscode, cursor, sublime, idea, webstorm, textmate, emacs, macvim, atom.

`dev` `debug` `ide` `deep-link` `protocol-handler` `config`

**Source:** `src/Dev/IdeLink.php` · **Layer:** `dev` · **Lifecycle:** `stateless — pure string lookups`

`IdeLink` is a single source of truth for editor deep-links. It owns the registry of supported IDEs and the URL scheme each one uses, so the error page and toolbar never hard-code `phpstorm://` directly. The active IDE is read from `app.debug_ide` config (default: `phpstorm`)

### Core Behavior
- Maps IDE identifier → display name and URL builder
- Reads active IDE from app.debug_ide config
- Builds JetBrains, VS Code, Sublime, TextMate, Emacs, MacVim, Atom URL schemes
- Maps each IDE to a tabler-icons icon name

### Registry

#### `supported(): array`
Returns the list of IDE identifiers this build knows about, in canonical display order.
- **Returns** `string[]` — IDE identifier keys (e.g. ['phpstorm', 'idea', 'webstorm', 'vscode', 'cursor', 'sublime', 'textmate', 'emacs', 'macvim', 'atom']).

#### `name(ide): string`
Returns the human-friendly display name for an IDE identifier. Returns the identifier itself for unknown values.
- `$ide: string` (required) — IDE identifier from supported().
- **Returns** `string` — Display name (e.g. 'PhpStorm', 'VS Code') or the identifier when unknown.

#### `isSupported(ide): bool`
Returns true when the given IDE identifier has a registered URL scheme.
- `$ide: string` (required) — IDE identifier to check.
- **Returns** `bool` — True when the IDE is in the registry.

### Resolution

#### `resolve(ide = null): string`
Returns the IDE identifier that should be used for deep-links. Resolution order: explicit argument → app.debug_ide config → 'phpstorm'. Unknown values fall back to 'phpstorm' silently.
- `$ide: ?string` (optional) — Optional explicit override. Null reads config.
- **Returns** `string` — Resolved IDE identifier — always a supported key, never an empty string.

### URL Building

#### `url(file, line, column = 1, ide = null): string`
Builds a deep-link URL for the resolved IDE that opens file:line(:column) in the editor.
- `$file: string` (required) — Absolute path to the source file.
- `$line: int` (required) — 1-based line number.
- `$column: ?int` (optional) — 1-based column number. Defaults to 1.
- `$ide: ?string` (optional) — Optional IDE override. Null uses config.
- **Returns** `string` — Full deep-link URL ready for an anchor href.

### Icons

#### `icon(ide): ?string`
Returns the tabler-icons icon name for the given IDE. Returns null when no icon mapping exists.
- `$ide: string` (required) — IDE identifier to check.
- **Returns** `?string` — tabler-icons class suffix (e.g. 'brand-phpstorm', 'brand-vscode', 'cursor') or null.

## `Skim\Dev\Profiler` — profiler

> Static event collector that records DB queries, cache ops, view renders, and log entries for debug toolbar display.

`dev` `debug` `profiler` `static` `toolbar`

**Source:** `src/Dev/Profiler.php` · **Layer:** `dev` · **Lifecycle:** `created empty at boot, enabled by App::run() when APP_DEBUG=true, populated during request, read by toolbar, reset between requests`

`profiler` is a static event collector that records framework operations (DB queries, cache ops, view renders, log entries) during a request. All methods are no-ops when disabled, ensuring zero overhead in production. The toolbar reads collected events for display.

### Core Behavior
- Records DB queries with interpolated SQL and timing
- Records cache operations with hit/miss status
- Records view renders with timing
- Records log entries with source location
- Provides aggregate summary for toolbar badges

### Drivers
| Driver | Mutable | Description |
|---|---|---|
| $events | yes | Array of recorded event arrays, cleared by reset(). |
| $enabled | yes | Boolean toggle — when false, all recording methods are no-ops. |

### Control

#### `enable(): void`
Enables event collection. Called by App::run() when APP_DEBUG=true.

#### `disable(): void`
Disables event collection. All recording methods become no-ops.

### Recording

#### `db(sql, ms, connection = 'default', rows = 0): void`
Records a database query event with interpolated SQL, timing, connection name, and row count. No-op when disabled.
- `$sql: string` (required) — Interpolated SQL string (not raw template).
- `$ms: float` (required) — Execution time in milliseconds.
- `$connection: string` (optional) — Connection name from config.
- `$rows: int` (optional) — Number of affected or returned rows.

#### `cache(op, key, hit = false, ttl = null, driver = 'redis'): void`
Records a cache operation event with operation type, key, hit/miss status, optional TTL, and driver name. No-op when disabled.
- `$op: string` (required) — Operation name (get, set, delete, remember).
- `$key: string` (required) — Cache key operated on.
- `$hit: bool` (optional) — Whether the operation was a cache hit.
- `$ttl: ?int` (optional) — TTL in seconds, or null for reads/deletes.
- `$driver: string` (optional) — Active cache driver name.

#### `view(template, fragment = null, ms = 0.0): void`
Records a template render event with template path, optional fragment name, and render time. No-op when disabled.
- `$template: string` (required) — Template path relative to views directory.
- `$fragment: ?string` (optional) — Fragment name for partial renders, null for full page.
- `$ms: float` (optional) — Render time in milliseconds.

#### `log(level, message, context = [], file = '', line = 0): void`
Records a log entry with level, message, context, and source location from debug_backtrace. No-op when disabled.
- `$level: string` (required) — Log level (debug, info, warning, error).
- `$message: string` (required) — Log message.
- `$context: array` (optional) — Additional context data.
- `$file: string` (optional) — Source file from debug_backtrace.
- `$line: int` (optional) — Source line from debug_backtrace.

### Reading

#### `summary(): array`
Returns an aggregate summary of all collected events grouped by type, with counts and timing totals for toolbar badge display.
- **Returns** `array` — Associative array with db, cache, views, viewMs, and logs keys.

#### `events(): array`
Returns all raw events collected during the current request. Used by the toolbar renderer to build detailed panels.
- **Returns** `array` — Array of event arrays in insertion order.

#### `reset(): void`
Clears the event buffer without disabling the profiler. Call in tests between requests to isolate per-request data.
- **Side effect:** clears static $events array

### Methods

#### `panel(id, label, options): void`
Registers a custom toolbar panel for user-defined debug extensions. :panel Use in controllers, middleware, or extensions to add debug panels to the toolbar. Panels appear as additional tabs after the built-in ones. When $html is provided, it renders directly. When $template is set, the toolbar renders it via dev_view with $data. Example: Profiler::panel('htmx', 'htmx Debug', [ 'icon' => 'arrows exchange', 'data' => ['swaps' => 3, 'boosts' => 1], ]);

#### `panels(): array`
Returns all registered custom panels for toolbar rendering. :panels

## `Skim\Dev\RequestTrace` — RequestTrace

> Per-request structured trace that records middleware, queries, controller calls, and response as a timestamped timeline.

`dev` `debug` `trace` `timeline` `static`

**Source:** `src/Dev/RequestTrace.php` · **Layer:** `dev` · **Lifecycle:** `start() at dispatch entry, event() throughout, finish() at response send, reset() in tests`

`RequestTrace` captures a structured timeline of events during a single HTTP request. In dev mode (APP_DEBUG=true), the full timeline is stored in sys.last_trace. In production, only errors and summaries are emitted.

### Core Behavior
- Records timestamped timeline events with elapsed time
- Records Throwables with class and message
- Tracks active extensions
- Computes total duration on finish

### Drivers
| Driver | Mutable | Description |
|---|---|---|
| $current | yes | Current in-progress trace array, null when not started. |
| $startTime | yes | microtime(true) at trace start. |
| $enabled | yes | Boolean toggle for trace collection. |

### Control

#### `enable(): void`
Enables trace collection. Called by App::run() when APP_DEBUG=true.

#### `disable(): void`
Disables trace collection and clears the current in-progress trace.

#### `isEnabled(): bool`
Returns whether trace collection is currently enabled.

### Recording

#### `start(requestId, method, path): void`
Begins a new per-request trace, discarding any previous unfinished trace. No-op when not enabled.
- `$requestId: string` (required) — Unique request identifier.
- `$method: string` (required) — HTTP method (GET, POST, etc.).
- `$path: string` (required) — Request path.

#### `event(event, context = []): void`
Appends a timestamped event to the current trace timeline. The context array is merged into the event entry alongside elapsed time and event name. No-op when trace not started.
- `$event: string` (required) — Event name (e.g. 'middleware.auth', 'db.query').
- `$context: array` (optional) — Additional key-value data merged into the event entry.

#### `error(e): void`
Records a Throwable into the errors list with elapsed timestamp, exception class, and message. No-op when trace not started.
- `$e: \Throwable` (required) — The exception to record.

#### `setExtensions(names): void`
Sets the list of active extension names on the current trace. No-op when trace not started.
- `$names: string[]` (required) — Extension names active for this request.

### Reading

#### `finish(status): array`
Closes the current trace, recording the HTTP status code and total duration in milliseconds. Returns the completed trace array and clears the current state.
- `$status: int` (required) — HTTP response status code.
- **Returns** `array` — Completed trace array with timeline, errors, extensions, status, and duration_ms. Empty array when not started.

#### `current(): ?array`
Returns the in-progress trace without closing it, or null when no trace is active.
- **Returns** `?array` — Current trace array, or null when not started.

#### `reset(): void`
Clears all trace state (current trace and start time) without disabling the tracer. Call in tests between requests.
- **Side effect:** clears static $current and $startTime

## `Skim\Dev\Toolbar` — toolbar

> Debug toolbar rendered before </body> for HTML responses when APP_DEBUG=true, showing queries, cache, timeline, views, and log.

`dev` `debug` `toolbar` `html` `profiler`

**Source:** `src/Dev/Toolbar.php` · **Layer:** `dev` · **Lifecycle:** `called by ToolbarMiddleware after controller returns, before response is sent`

`toolbar` generates a fixed-bottom debug panel with tabbed views for request info, DB queries, cache statistics, timeline breakdown, rendered views, and log entries. It reads all data from Profiler::summary() and Profiler::events().

### Core Behavior
- Renders tabbed debug panel with request, queries, cache, timeline, views, and log tabs
- Shows query count warning when > 20 queries
- Color-codes query timing (fast/medium/slow)
- Displays cache hit/miss ratio
- Shows timeline breakdown of db/views/other time

### Rendering

#### `render(req): string`
Generates the complete debug toolbar HTML string from profiler data. Returns empty string when APP_DEBUG=false. Builds tabbed panels for request info, DB queries, cache stats, timeline, views, and log entries.
- `$req: request` (required) — Current HTTP request for method/path display in the toolbar header.
- **Returns** `string` — Complete toolbar HTML with inline CSS and JS, or empty string when debug is off.

## `Skim\Events\Event` — event

> Synchronous event bus with priority ordering, one-time listeners, and async dispatch via queue.

`facade` `events` `pubsub` `async` `priority`

**Source:** `src/Events/Event.php` · **Layer:** `events` · **Lifecycle:** `static, listeners registered during boot phase`

`event` is the static event bus for decoupling side effects. Listeners run synchronously in priority order during emit(). Async dispatch via emitAsync() delegates to the queue worker.

### Core Behavior
- Typed event classes dispatch by class name
- String events dispatch by name
- Priority sorting on registration
- One-time listener cleanup after dispatch

### Registration

#### `on(event, listener, priority = 0): void`
Registers a persistent listener for the event. Higher priority runs first
- `$event: string` (required) — Event class-string or string name.
- `$listener: callable` (required) — Callback receiving the event object or payload.
- `$priority: int` (optional) — Higher number runs first. Default 0.
- **Side effect:** Adds listener to static registry

#### `once(event, listener, priority = 0): void`
Registers a one-time listener that is automatically removed after the first dispatch.
- `$event: string` (required) — Event class-string or string name.
- `$listener: callable` (required) — Callback receiving the event object or payload.
- `$priority: int` (optional) — Higher number runs first. Default 0.
- **Side effect:** Adds one-time listener to static registry

### Dispatch

#### `emit(payload, data = null): void`
Dispatches the event to all registered listeners in priority order. For typed events, the object is passed directly. For string events, $data is the payload. All listeners run synchronously.
- `$payload: object` (required) — Event object (typed) or event name (string).
- `$data: mixed` (optional) — Payload for string events. Ignored for typed events.
- **Side effect:** Runs all matching listeners inline
- **Side effect:** Removes once-listeners after execution

#### `emitAsync(payload, data = null): void`
Pushes the event to the queue for async processing in a worker process. Requires skim/queue to be installed.
- `$payload: object` (required) — Event object or event name.
- `$data: mixed` (optional) — Payload for string events.
- **Throws** `\RuntimeException` — When skim/queue is not installed.
- **Side effect:** Pushes job to queue

### Testing Hooks

#### `resetRequest(): void`
Clears the listener registry between requests in worker mode.
- **Side effect:** Empties the static $listeners array

#### `off(event = null): void`
Removes all listeners for a specific event, or all listeners when $event is null.
- `$event: ?string` (optional) — Event name to clear, or null for all listeners.
- **Side effect:** Clears listener registry

#### `listenerCount(event): int`
Returns the number of listeners currently registered for the given event.
- `$event: string` (required) — Event class-string or string name.
- **Returns** `int` — Number of registered listeners.

### Methods

#### `captureBootSnapshot(): void`
Captures the current listener registry as the boot-time snapshot. :capture_boot_snapshot Call once after boot/extensions are registered in worker mode. The snapshot is restored by resetRequest() on every subsequent request so boot-time listeners survive while request-time listeners are dropped.

#### `totalListenerCount(): int`
Returns the total number of listeners across all events. :total_listener_count Used by the leak detector to detect listener accumulation in worker mode.

## `Skim\Ext\CapabilityVocabulary` — CapabilityVocabulary

> Canonical map of recognized extension capability slugs to human-readable descriptions.

`vocabulary` `extension` `static`

**Source:** `src/Ext/CapabilityVocabulary.php` · **Layer:** `ext` · **Lifecycle:** `stateless, all data in class constant`

`CapabilityVocabulary` defines the canonical set of capability slugs that SKIM extensions can declare. It provides a lookup method and a constant map for display or validation purposes.

### Core Behavior
- isKnown() checks slug existence against TERMS constant

### Lookup API

#### `isKnown(capability): bool`
Returns true when the given capability slug is present in the TERMS constant.
- `$capability: string` (required) — Capability slug to test, e.g. 'auth' or 'rate-limiting'.
- **Returns** `bool` — True if the slug is a recognized capability.

## `Skim\Ext\ExtManifest` — ExtManifest

> Generates a JSON manifest snapshot from an extension's runtime metadata.

`extension` `manifest` `json` `generator`

**Source:** `src/Ext/ExtManifest.php` · **Layer:** `ext` · **Lifecycle:** `stateless, one-shot file write`

`ExtManifest` serializes an extension instance's metadata into a pretty-printed JSON file. The generated skim.json allows ExtRegistry to read extension data without class instantiation.

### Core Behavior
- Extracts all public metadata from extension instance and writes JSON

### Generation

#### `generate(ext, outputPath): void`
Serializes extension metadata to a JSON file. Overwrites any existing file at the target path.
- `$ext: extension` (required) — Instantiated extension to extract metadata from.
- `$outputPath: string` (required) — Absolute file path for the JSON output.
- **Side effect:** Writes JSON file to disk

## `Skim\Ext\ExtMigrator` — ExtMigrator

> Runs extension migrations against the shared _migrations table with session-scoped rollback.

`extension` `migration` `database` `transactional`

**Source:** `src/Ext/ExtMigrator.php` · **Layer:** `ext` · **Lifecycle:** `instantiated per-extension install`

`ExtMigrator` applies an extension's migration files inside transactions and tracks them in the shared `_migrations` table. It supports session-scoped rollback for cleanup when installation fails partway through.

### Core Behavior
- Scans migration directory for .php files
- Compares against _migrations table
- Applies pending in sorted order
- Tracks in batch numbers

### Warnings
- ⚠ rollbackSession() only rolls back migrations from the current run() call — not previous sessions

### Migration API

#### `__construct(connection = 'default')`
Sets the configured DB connection name used for all migration operations.
- `$connection: string` (optional) — Configured DB connection name from config/db.php. Default 'default'.

#### `run(extName, migrationsPath): array`
Scans the migrations path, compares against applied migrations, and applies pending ones inside transactions. Returns the list of applied entries.
- `$extName: string` (required) — Extension name. Must be non-empty and cannot contain colons.
- `$migrationsPath: string` (required) — Absolute path to directory containing migration .php files.
- **Returns** `array` — List of applied migration entries with ext_name, filename, tracking_filename, and file keys.
- **Side effect:** Creates _migrations table if missing
- **Side effect:** Executes migration SQL
- **Side effect:** Inserts tracking rows

#### `rollbackSession(appliedThisSession): array`
Rolls back migrations applied during the current install session in reverse order. Each rollback runs inside a transaction.
- `$appliedThisSession: array` (required) — Array of applied entries returned by run().
- **Returns** `array<int,string>` — Tracking filenames that were rolled back.
- ⚠ Only rolls back migrations from the most recent run() call — not previous sessions
- **Side effect:** Executes migration down() SQL
- **Side effect:** Deletes tracking rows from _migrations

### Inspection

#### `appliedThisSession(): array`
Returns the list of migrations applied during the most recent run() call.
- **Returns** `array` — Applied migration entries from the last run().

## `Skim\Ext\ExtRegistry` — ExtRegistry

> Discovers installed SKIM extensions from Composer packages with capability mapping and conflict detection.

`extension` `discovery` `registry` `composer` `capability-map`

**Source:** `src/Ext/ExtRegistry.php` · **Layer:** `ext` · **Lifecycle:** `instantiated per-discovery`

`ExtRegistry` scans vendor/ Composer packages for SKIM extension metadata. It reads extra.skim from composer.json and optional skim.json manifests, builds a capability map, detects conflicts, and caches results for the instance lifetime.

### Core Behavior
- Scans vendor/*/composer.json and vendor/*/*/composer.json
- Reads extra.skim.extension for class name
- Falls back to skim.json manifest
- Builds capability map with conflict detection

### Discovery API

#### `__construct(root)`
Sets the project root directory containing vendor/ for extension scanning.
- `$root: string` (required) — Project root path containing vendor/ directory.

#### `installed(): array`
Scans vendor/ for extension metadata from composer.json extra.skim and skim.json manifests. Results are cached for the instance lifetime.
- **Returns** `array` — Sorted array of extension metadata arrays with name, version, class, capabilities, etc.

#### `all(root = null): array`
Convenience static method that creates a registry with the given or default root and returns installed extensions.
- `$root: ?string` (optional) — Project root, or null for basePath().
- **Returns** `array` — Extension metadata arrays.

### Lookup API

#### `find(name): ?array`
Returns metadata for one extension by package name, or null when not installed.
- `$name: string` (required) — Package name to search for, e.g. 'acme/auth'.
- **Returns** `?array` — Extension metadata array or null.

### Capability API

#### `capabilityMap(): array`
Builds and returns a map of capability name => providing extension name. Detects duplicate providers and declared conflicts as a side effect.
- **Returns** `array<string,string>` — Capability name => extension name map.
- **Side effect:** Populates internal conflicts list

### Dynamic Dispatch

#### `__call(method, args): mixed`
Dispatches has_capability, who_provides, and conflicts dynamically.
- `$method: string` (required) — Method name: has_capability, who_provides, or conflicts.
- `$args: array` (required) — Method arguments.
- **Throws** `\BadMethodCallException` — When method is not recognized.

#### `__callStatic(method, args): mixed`
Static dispatch for has_capability, who_provides, and conflicts. Creates a new registry with basePath() or provided root.
- `$method: string` (required) — Method name.
- `$args: array` (required) — Method arguments.
- **Throws** `\BadMethodCallException` — When method is not recognized.

### Cache Management

#### `refresh(): void`
Clears all cached scan data so a later installed package can be discovered on the next installed() call.
- **Side effect:** Clears installed
- **Side effect:** capabilityMap
- **Side effect:** and conflicts caches

## `Skim\Ext\Extension` — extension

> Abstract base class for SKIM extensions with metadata properties and lifecycle hooks.

`abstract` `extension` `lifecycle`

**Source:** `src/Ext/Extension.php` · **Layer:** `ext` · **Lifecycle:** `instantiated by extensionManager::instance() after discovery`

`extension` is the abstract base class that all SKIM extension packages extend. It defines metadata properties (name, version, capabilities) and two lifecycle hooks (register, boot) that extensionManager calls during app boot.

### Core Behavior
- Declares metadata via public properties
- Contributes config, commands, migrations, env keys, and post-install steps
- Lifecycle hooks receive the app container

### Lifecycle Hooks

#### `register(app): void`
Called during the registration phase. Bind services, register routes, declare config. Runs before boot().
- `$app: app` (required) — The application container instance.

#### `boot(app): void`
Called after all extensions have registered. Start services, warm caches, attach event listeners.
- `$app: app` (required) — The application container instance.

### Metadata Accessors

#### `migrations(): string`
Returns the path to the extension's migrations directory. Empty string means no migrations.
- **Returns** `string` — Absolute path to migrations directory, or empty string.

#### `config(): array`
Returns config key-value pairs that the extension contributes to the app container.
- **Returns** `array` — Config key-value pairs.

#### `commands(): array`
Returns a map of CLI command names to their class strings.
- **Returns** `array` — Command name => class map.

#### `envKeys(): array`
Returns the list of .env variable names this extension requires.
- **Returns** `array` — List of env key strings.

#### `postInstall(): array`
Returns descriptions of post-install CLI steps this extension needs.
- **Returns** `array` — List of step description strings.

### Static Metadata

#### `manifest(): array`
Returns static metadata without instantiation. An empty array signals ExtRegistry to fall back to instance properties. The capabilities key may be a flat list or an associative map.
- **Returns** `array` — Metadata array or empty array for fallback.
- **Note:** Override in subclasses to avoid class instantiation during discovery.

## `Skim\Ext\ExtensionManager` — extensionManager

> Coordinates extension discovery, dependency validation, topological sorting, and lifecycle hooks.

`extension` `lifecycle` `discovery` `dependency-graph`

**Source:** `src/Ext/ExtensionManager.php` · **Layer:** `ext` · **Lifecycle:** `created by discover() during app boot`

`extensionManager` discovers extensions from Composer packages, validates their dependency requirements, topologically sorts them, and invokes register()/boot() hooks in the correct order during app boot.

### Core Behavior
- Discovers via ExtRegistry
- Validates requires against provides+capabilities
- Topological sort ensures dependency order
- Calls register/boot within extension context for profiler

### Discovery

#### `discover(root, app): self`
Scans Composer packages for extensions, validates dependencies, topologically sorts them, and stores metadata in the app container.
- `$root: string` (required) — Project root directory containing vendor/.
- `$app: app` (required) — Application container to store extension metadata.
- **Returns** `self` — Configured extensionManager ready for register()/boot().
- **Throws** `\RuntimeException` — On missing dependency or circular dependency.
- **Side effect:** Stores sys.extensions and sys.extension_conflicts in app container

### Lifecycle Hooks

#### `register(app): void`
Calls register(app) for every extension in dependency-sorted order. Throws immediately if conflicts exist.
- `$app: app` (required) — Application container.
- **Throws** `\RuntimeException` — When extension conflicts are detected.
- **Side effect:** Calls extension register() hooks
- **Side effect:** Mutates app container via extension registrations

#### `boot(app): void`
Calls boot(app) for every extension in dependency-sorted order. Runs after all extensions have been registered.
- `$app: app` (required) — Application container.
- **Side effect:** Calls extension boot() hooks

### Architecture

#### `__construct(extensions)`
Accepts normalized extension metadata arrays from ExtRegistry::installed().
- `$extensions: array` (required) — Extension metadata arrays from ExtRegistry.

#### `validateDependencies(extensions): void`
Throws RuntimeException when a required capability or name is not provided by any installed extension.
- `$extensions: array` (required) — Extension metadata arrays.
- **Throws** `\RuntimeException` — When a required capability is not provided.

#### `topologicalSort(extensions): array`
Returns extensions sorted so dependencies always come before dependents. Throws on circular dependency.
- `$extensions: array` (required) — Extension metadata arrays.
- **Returns** `array` — Topologically sorted extension metadata arrays.
- **Throws** `\RuntimeException` — On circular dependency.

## `Skim\Ext\ExtensionPriority` — ExtensionPriority

> Named integer bands controlling extension registration and boot order.

`constants` `extension` `ordering`

**Source:** `src/Ext/ExtensionPriority.php` · **Layer:** `ext` · **Lifecycle:** `stateless constants`

`ExtensionPriority` defines four named bands that control when extensions register and boot relative to each other. Lower values execute first.

### Core Behavior
- Constants are referenced by ExtRegistry and extensionManager for sort ordering

## `Skim\Helpers\Arr` — arr

> Array utility methods for finding, indexing, plucking, and common collection patterns.

`helper` `array` `stateless` `collections`

**Source:** `src/Helpers/Arr.php` · **Layer:** `helpers` · **Lifecycle:** `stateless — all methods are pure functions`

`arr` provides common array transformations used across controllers, models, and services. It wraps PHP 8.4+ `array_find()` with key=>value shorthand and provides multi-key indexing methods essential for eager-loading relation maps.

### Core Behavior
- find/findAll support both callable predicates and key=>value shorthand
- mapBy/mapCol/mapNested/mapKeys work with arrays and objects
- pluck wraps array_column

### Search

#### `find(criteria, items): mixed`
Finds the first element matching a key=>value pair or callable predicate. Returns null if no match.
- `$criteria: array` (required) — ['col' => 'val'] shorthand or predicate function.
- `$items: array` (required) — Array to search.
- **Returns** `mixed` — First matching element or null.

#### `findAll(criteria, items): array`
Returns all elements matching a key=>value pair or callable. Re-indexes the result.
- `$criteria: array` (required) — ['col' => 'val'] shorthand or predicate function.
- `$items: array` (required) — Array to filter.
- **Returns** `array` — All matching elements, re-indexed.

#### `filterBy(col, val, items): array`
Filters items where column equals value (strict ===). Returns re-indexed array.
- `$col: string` (required) — Column to match.
- `$val: mixed` (required) — Value to match.
- `$items: array` (required) — Array to filter.
- **Returns** `array` — Filtered and re-indexed array.

### Indexing

#### `mapBy(key, items): array`
Re-indexes an array by a column value. Last-write-wins on key collision.
- `$key: string` (required) — Column to use as the new key.
- `$items: array` (required) — Array of arrays or objects.
- **Returns** `array` — Associative array keyed by column value.

#### `mapCol(key, val, items): array`
Maps two columns into key→value pairs.
- `$key: string` (required) — Column for resulting key.
- `$val: string` (required) — Column for resulting value.
- `$items: array` (required) — Array of arrays or objects.
- **Returns** `array` — Key→value associative array.

#### `mapNested(key1, key2, items): array`
Creates a double-key index: result[k1][k2] = item. Last-write-wins at second level.
- `$key1: string` (required) — First-level key column.
- `$key2: string` (required) — Second-level key column.
- `$items: array` (required) — Array of arrays or objects.
- **Returns** `array` — Two-level nested associative array.

#### `mapKeys(key1, key2, items): array`
Creates a triple-key index: result[k1][k2][] = item. Appends at second level (not overwrites).
- `$key1: string` (required) — First-level key column.
- `$key2: string` (required) — Second-level key column.
- `$items: array` (required) — Array of arrays or objects.
- **Returns** `array` — Two-level nested array with appended lists.

### Extraction

#### `pluck(key, items): array`
Extracts a single column into a flat list. Wraps array_column().
- `$key: string` (required) — Column to extract.
- `$items: array` (required) — Array of arrays.
- **Returns** `array` — Flat list of column values.

### Navigation

#### `first(items): mixed`
Returns the first element or null if empty. Wraps PHP 8.5 array_first().
- `$items: array` (required) — Input array.
- **Returns** `mixed` — First element or null.

#### `last(items): mixed`
Returns the last element or null if empty. Wraps PHP 8.5 array_last().
- `$items: array` (required) — Input array.
- **Returns** `mixed` — Last element or null.

### Aggregation

#### `normalize100(values): array`
Normalizes numeric values so they sum to exactly 100. Remainder from rounding is added to the last element.
- `$values: array` (required) — Associative array of numeric values.
- **Returns** `array` — Normalized values summing to 100.

#### `weightedPick(weights): string|int`
Picks a random key weighted by values. Uses random_int() for cryptographic randomness.
- `$weights: array` (required) — Associative array of key => weight.
- **Returns** `string` — The randomly selected key.

### Debug

#### `toString(data, depth = 0): string`
Returns a human-readable string dump of an array. No HTML — safe for CLI and log files.
- `$data: array` (required) — Array to dump.
- `$depth: int` (optional) — Internal recursion depth.
- **Returns** `string` — Formatted string representation.

## `Skim\Helpers\Filter` — filter

> Input validation helper returning typed values or false — never null.

`helper` `validation` `filter` `stateless`

**Source:** `src/Helpers/Filter.php` · **Layer:** `helpers` · **Lifecycle:** `stateless — all methods are pure functions`

`filter` validates and sanitizes user input. Every method returns the typed value on success or `false` on invalid input — never `null`. Use in controllers before passing data to models or queries.

### Core Behavior
- int() uses string comparison to reject floats like 1.5
- bool() accepts common truthy/falsy strings
- array variants filter and re-index results

### Numeric

#### `int(value, min = null, max = null): int|false`
Returns int if $value is a valid integer string. Rejects floats (1.5), non-numeric strings, and values outside [$min, $max].
- `$value: mixed` (required) — Input to validate.
- `$min: ?int` (optional) — Minimum allowed value (inclusive).
- `$max: ?int` (optional) — Maximum allowed value (inclusive).
- **Returns** `int` — Validated integer or false.

#### `intPositive(value): int|false`
Alias for int() with min:1.
- `$value: mixed` (required) — Input to validate.
- **Returns** `int` — Positive integer or false.

#### `intNatural(value): int|false`
Alias for int() with min:0.
- `$value: mixed` (required) — Input to validate.
- **Returns** `int` — Natural number or false.

#### `float(value, min = null, max = null): float|false`
Returns float if numeric, false otherwise. Optional min/max range check.
- `$value: mixed` (required) — Input to validate.
- `$min: ?float` (optional) — Minimum allowed value.
- `$max: ?float` (optional) — Maximum allowed value.
- **Returns** `float` — Validated float or false.

### Boolean

#### `bool(value): bool`
Converts truthy/falsy string representations to bool. Accepts '1','true','yes','on' → true
- `$value: mixed` (required) — Input to convert.
- **Returns** `bool` — Converted boolean value.

### Date & Time

#### `date(value): string|false`
Returns Y-m-d string for valid dates, false otherwise. Uses strtotime() for parsing.
- `$value: mixed` (required) — Date string or timestamp.
- **Returns** `string` — Y-m-d formatted date or false.

#### `time(value): string|false`
Returns H:i:s string for valid time strings, false otherwise.
- `$value: mixed` (required) — Time string.
- **Returns** `string` — H:i:s formatted time or false.

### Network

#### `ip(value): string|false`
Validates IPv4 or IPv6 address using FILTER_VALIDATE_IP.
- `$value: mixed` (required) — IP address string.
- **Returns** `string` — Valid IP string or false.

#### `domain(value): string|false`
Validates a domain name. Returns the host portion only (no scheme, no path).
- `$value: mixed` (required) — Domain string.
- **Returns** `string` — Domain host or false.

### String Patterns

#### `email(value): string|false`
Validates and lowercases an email address using FILTER_VALIDATE_EMAIL.
- `$value: mixed` (required) — Email string.
- **Returns** `string` — Lowercased email or false.

#### `url(value): string|false`
Validates a URL using FILTER_VALIDATE_URL.
- `$value: mixed` (required) — URL string.
- **Returns** `string` — Valid URL or false.

#### `username(value): string|false`
Validates against [a-zA-Z0-9_@.\-]+ pattern.
- `$value: mixed` (required) — Username string.
- **Returns** `string` — Valid username or false.

#### `password(value): string|false`
Validates minimum 8-character password.
- `$value: mixed` (required) — Password string.
- **Returns** `string` — Password if >= 8 chars, false otherwise.

#### `slug(value): string|false`
Validates against [a-z0-9\-]+ pattern.
- `$value: mixed` (required) — Slug string.
- **Returns** `string` — Valid slug or false.

#### `regex(value, pattern): string|false`
Validates a string against a custom PCRE pattern.
- `$value: mixed` (required) — Input string.
- `$pattern: string` (required) — PCRE pattern.
- **Returns** `string` — Value if matches, false otherwise.

### Range & Enum

#### `in(value, allowed): mixed`
Returns the value only if it is in the allowed list. Uses strict comparison.
- `$value: mixed` (required) — Input to check.
- `$allowed: array` (required) — Whitelist of allowed values.
- **Returns** `mixed` — Value if in list, false otherwise.

#### `range(value, min, max): int|float|false`
Returns the value only if it falls within [$min, $max] inclusive.
- `$value: int` (required) — Input to check.
- `$min: int` (required) — Minimum (inclusive).
- `$max: int` (required) — Maximum (inclusive).
- **Returns** `int` — Value if in range, false otherwise.

### Array Variants

#### `arrInt(values, min = null, max = null): array`
Filters an array, keeping only valid ints within optional range. Re-indexes the result.
- `$values: array` (required) — Input values.
- `$min: ?int` (optional) — Minimum allowed value.
- `$max: ?int` (optional) — Maximum allowed value.
- **Returns** `array` — Filtered and re-indexed array of valid ints.

#### `arrIntPositive(values): array`
Filters an array, keeping only positive ints (min:1).
- `$values: array` (required) — Input values.
- **Returns** `array` — Filtered array of positive ints.

#### `arrIn(values, allowed): array`
Filters an array, keeping only values present in $allowed. Re-indexes the result.
- `$values: array` (required) — Input values.
- `$allowed: array` (required) — Whitelist.
- **Returns** `array` — Filtered and re-indexed array.

## `Skim\Helpers\Str` — str

> Static string utility methods for slugs, truncation, UUIDs, random strings, and case conversion.

`helper` `string` `stateless`

**Source:** `src/Helpers/Str.php` · **Layer:** `helpers` · **Lifecycle:** `stateless — all methods are pure functions`

`str` provides common string transformations as pure static methods. Used across controllers, models, and CLI commands for slug generation, text truncation, UUID creation, and case conversion.

### Core Behavior
- slug() lowercases, strips non-alphanumeric, collapses separators
- excerpt() respects word boundaries
- toSnake() handles consecutive capitals

### Slugs & Truncation

#### `slug(text): string`
Converts text to a URL-safe slug by lowercasing, stripping non-alphanumeric characters (Unicode-aware), and collapsing whitespace/dashes into single dashes.
- `$text: string` (required) — Input text to slugify.
- **Returns** `string` — URL-safe slug.

#### `excerpt(text, length = 100, suffix = '...', wordBoundary = true): string`
Truncates text to $length characters. When $wordBoundary is true, backs up to the last space to avoid mid-word cuts. Appends $suffix only when truncation occurs.
- `$text: string` (required) — Input text to truncate.
- `$length: int` (optional) — Maximum character count before truncation. Default 100.
- `$suffix: string` (optional) — Appended when text is truncated. Default '...'.
- `$wordBoundary: bool` (optional) — When true, avoids cutting mid-word. Default true.
- **Returns** `string` — Truncated text with suffix, or original if within limit.

### Generation

#### `random(length = 32): string`
Generates a cryptographically random alphanumeric string using random_int().
- `$length: int` (optional) — Number of characters. Default 32.
- **Returns** `string` — Random alphanumeric string.

#### `uuid(): string`
Generates an RFC 4122 UUID v4 using random_bytes().
- **Returns** `string` — UUID v4 string in standard format.

### Predicates

#### `contains(haystack, needle): bool`
Returns true when $haystack contains $needle. Thin wrapper over str_contains().
- `$haystack: string` (required) — String to search in.
- `$needle: string` (required) — Substring to look for.
- **Returns** `bool` — True if needle is found.

#### `startsWith(str, prefix): bool`
Returns true when $str starts with $prefix.
- `$str: string` (required) — String to test.
- `$prefix: string` (required) — Expected prefix.
- **Returns** `bool` — True if string starts with prefix.

#### `endsWith(str, suffix): bool`
Returns true when $str ends with $suffix.
- `$str: string` (required) — String to test.
- `$suffix: string` (required) — Expected suffix.
- **Returns** `bool` — True if string ends with suffix.

### Case Conversion

#### `toSnake(str): string`
Converts CamelCase or PascalCase to snake_case. Handles consecutive capitals correctly (e.g. 'HTMLParser' → 'html_parser').
- `$str: string` (required) — CamelCase input.
- **Returns** `string` — snake_case output.

#### `toCamel(str): string`
Converts snake_case to camelCase (first letter lowercase).
- `$str: string` (required) — snake_case input.
- **Returns** `string` — camelCase output.

## `Skim\Http\Client` — client

> Zero-dependency HTTP client using PHP native streams with JSON body and test faking.

`http` `client` `streams` `zero-dep` `testable`

**Source:** `src/Http/Client.php` · **Layer:** `http` · **Lifecycle:** `instantiated per-service or per-request`

`client` wraps PHP's native `file_get_contents` + `stream_context_create` for outbound HTTP. It returns immutable `HttpResponse` objects and never throws on non-2xx status codes.

### Core Behavior
- Sends HTTP via PHP streams
- JSON-encodes request bodies
- Merges default and per-request headers
- Supports baseUrl prefixing

### HTTP Methods

#### `get(url, query = [], headers = []): HttpResponse`
Sends a GET request. The $query array is appended as URL query parameters.
- `$url: string` (required) — Target URL or path (prepended with baseUrl if set).
- `$query: array` (optional) — Query params appended as ?key=val URL string.
- `$headers: array` (optional) — Additional headers merged with defaults.
- **Returns** `HttpResponse` — Immutable response value object.

#### `post(url, data = [], headers = []): HttpResponse`
Sends a POST request with JSON-encoded body.
- `$url: string` (required) — Target URL or path.
- `$data: array` (optional) — Request body, JSON-encoded.
- `$headers: array` (optional) — Additional headers merged with defaults.
- **Returns** `HttpResponse` — Immutable response value object.

#### `put(url, data = [], headers = []): HttpResponse`
Sends a PUT request with JSON-encoded body.
- `$url: string` (required) — Target URL or path.
- `$data: array` (optional) — Request body, JSON-encoded.
- `$headers: array` (optional) — Additional headers merged with defaults.
- **Returns** `HttpResponse` — Immutable response value object.

#### `patch(url, data = [], headers = []): HttpResponse`
Sends a PATCH request with JSON-encoded body.
- `$url: string` (required) — Target URL or path.
- `$data: array` (optional) — Request body, JSON-encoded.
- `$headers: array` (optional) — Additional headers merged with defaults.
- **Returns** `HttpResponse` — Immutable response value object.

#### `delete(url, data = [], headers = []): HttpResponse`
Sends a DELETE request with optional JSON body. Body is omitted when $data is empty.
- `$url: string` (required) — Target URL or path.
- `$data: array` (optional) — Optional request body, JSON-encoded when non-empty.
- `$headers: array` (optional) — Additional headers merged with defaults.
- **Returns** `HttpResponse` — Immutable response value object.

### Testing

#### `fake(stubs = []): FakeClient`
Returns a FakeClient that intercepts all HTTP requests, records them, and returns stub responses.
- `$stubs: array` (optional) — Map of 'METHOD URL' => response stub arrays or HttpResponse objects.
- **Returns** `FakeClient` — Test double that records requests and returns stubs.

### Methods

#### `__construct(options)`

## `Skim\Http\FakeClient` — FakeClient

> Test double for HTTP client that records requests and returns stub responses.

`testing` `http` `fake` `stub`

**Source:** `src/Http/FakeClient.php` · **Layer:** `http` · **Lifecycle:** `created via Client::fake()`

`FakeClient` extends `client` to intercept all HTTP requests in tests. It records every request for later assertion and returns configurable stub responses without making real network calls.

### Core Behavior
- Records all requests with method, URL, and body
- Matches stubs by key priority
- Provides assertion methods for test verification

### HTTP Methods

#### `get(url, query = [], headers = []): HttpResponse`
Records a GET request and returns the matching stub response. Query params are not appended to the URL in the fake.
- `$url: string` (required) — Target URL.
- `$query: array` (optional) — Query params (ignored in fake).
- `$headers: array` (optional) — Request headers (recorded but not sent).
- **Returns** `HttpResponse` — Stub response or default 200 empty JSON.

#### `post(url, data = [], headers = []): HttpResponse`
Records a POST request with body data and returns the matching stub response.
- `$url: string` (required) — Target URL.
- `$data: array` (optional) — Request body data (recorded).
- `$headers: array` (optional) — Request headers (recorded but not sent).
- **Returns** `HttpResponse` — Stub response or default 200 empty JSON.

#### `put(url, data = [], headers = []): HttpResponse`
Records a PUT request with body data and returns the matching stub response.
- `$url: string` (required) — Target URL.
- `$data: array` (optional) — Request body data (recorded).
- `$headers: array` (optional) — Request headers (recorded but not sent).
- **Returns** `HttpResponse` — Stub response or default 200 empty JSON.

#### `patch(url, data = [], headers = []): HttpResponse`
Records a PATCH request with body data and returns the matching stub response.
- `$url: string` (required) — Target URL.
- `$data: array` (optional) — Request body data (recorded).
- `$headers: array` (optional) — Request headers (recorded but not sent).
- **Returns** `HttpResponse` — Stub response or default 200 empty JSON.

#### `delete(url, data = [], headers = []): HttpResponse`
Records a DELETE request with optional body data and returns the matching stub response.
- `$url: string` (required) — Target URL.
- `$data: array` (optional) — Request body data (recorded).
- `$headers: array` (optional) — Request headers (recorded but not sent).
- **Returns** `HttpResponse` — Stub response or default 200 empty JSON.

### Assertions

#### `assertSent(method, url): void`
Throws RuntimeException when no recorded request matches the given method and URL substring.
- `$method: string` (required) — HTTP method (case-insensitive).
- `$url: string` (required) — URL substring to match via str_contains.
- **Throws** `\RuntimeException` — When no matching request was recorded.

#### `assertNothingSent(): void`
Throws RuntimeException when any HTTP requests were recorded.
- **Throws** `\RuntimeException` — When any requests were recorded.

### Inspection

#### `recorded(): array`
Returns all recorded requests for custom assertions. Each entry has method, url, and body keys.
- **Returns** `list<array{method:string,url:string,body:mixed}>` — All recorded requests.

### Methods

#### `__construct(stubs)`

## `Skim\Http\HttpResponse` — HttpResponse

> Immutable value object for HTTP responses with status, body, headers, and JSON parsing.

`value-object` `http` `immutable`

**Source:** `src/Http/HttpResponse.php` · **Layer:** `http` · **Lifecycle:** `created by Client::send() or fromStream()`

`HttpResponse` is an immutable value object returned by all `client` HTTP methods. It wraps status code, body, and headers without throwing on non-2xx responses.

### Core Behavior
- Holds status, body, and headers as readonly properties
- Provides ok() and json() convenience methods

### Construction

#### `fromStream(body, meta): static`
Parses PHP stream $http_response_header meta array into status code and headers, returns a new HttpResponse.
- `$body: string` (required) — Raw response body string.
- `$meta: array` (required) — The $http_response_header array from PHP stream context.
- **Returns** `static` — New HttpResponse with parsed status and headers.

### Accessors

#### `json(): array`
Decodes the response body as JSON. Returns an empty array when the body is not valid JSON.
- **Returns** `array` — Decoded JSON body, or empty array on invalid JSON.

#### `ok(): bool`
Returns true when the status code is in the 2xx range.
- **Returns** `bool` — True for 200-299 status codes.

#### `header(name): ?string`
Returns the value of a named response header, or null if the header is not present.
- `$name: string` (required) — Case-sensitive header name to look up.
- **Returns** `?string` — Header value or null if absent.

### Methods

#### `__construct(status, body, headers)`

## `Skim\I18n\I18n` — i18n

> Minimal i18n facade with PHP array files, dot-notation keys, pluralization, and custom loader support.

`facade` `i18n` `translation` `pluralization`

**Source:** `src/I18n/I18n.php` · **Layer:** `i18n` · **Lifecycle:** `static facade, translation files loaded on first access per locale`

`i18n` provides translation lookup using PHP array files organized by locale. It supports dot-notation keys, pipe-based pluralization, :param interpolation, fallback locale, and custom loaders for alternative backends.

### Core Behavior
- Dot-notation key resolution through nested arrays
- Pipe-based two-form pluralization
- :param interpolation
- Fallback locale on miss

### Translation API

#### `t(key, params = []): string`
Translates a dot-notation key using the active locale with fallback. Supports pipe-based pluralization when 'count' is in params, and :param interpolation. Returns the key unchanged on miss.
- `$key: string` (required) — Dot-notation translation key, e.g. 'auth.login.title'.
- `$params: array` (optional) — Interpolation params (:name => value) and pluralization (count => int).
- **Returns** `string` — Translated and interpolated string, or the key itself on miss.

### Configuration

#### `locale(locale): void`
Sets the active locale used by all subsequent t() calls.
- `$locale: string` (required) — Locale code, e.g. 'fr', 'es', 'de'.
- **Side effect:** Mutates static locale state

#### `currentLocale(): string`
Returns the currently active locale string.
- **Returns** `string` — Current locale code.

#### `setPath(path): void`
Sets the base path to the lang/ directory containing locale subdirectories.
- `$path: string` (required) — Absolute path to the lang directory.
- **Side effect:** Mutates static langPath state

### Testing Hooks

#### `resetRequest(): void`
Resets the locale to fallback between requests in worker mode.
- **Side effect:** Mutates static $locale to $fallback value

#### `setLoader(loader): void`
Injects a custom translation loader that bypasses file-based loading. The callable receives (locale, key) and returns string or null.
- `$loader: callable` (required) — Function(string $locale, string $key): ?string.
- **Side effect:** Mutates static loader state

#### `reset(): void`
Clears all state: locale, fallback, loaded files, and custom loader. Use in test tearDown().
- **Side effect:** Clears all static state

## `Skim\Log\FileHandler` — FileHandler

> Rotating file log handler with daily suffix and automatic old-file cleanup.

`handler` `log` `file` `rotating`

**Source:** `src/Log/FileHandler.php` · **Layer:** `log` · **Lifecycle:** `created by Log::resolveHandler()`

`FileHandler` writes log entries to date-suffixed files and automatically prunes files older than the configured retention period. It is the default log backend when no external service is configured.

### Core Behavior
- Filters by minimum log level
- Appends formatted lines to date-suffixed files
- Prunes old files beyond retention window

### Contract Implementation

#### `write(level, message, context): void`
Appends a formatted log line to the date-suffixed file when the level meets the minimum threshold. Triggers rotation after each write.
- `$level: string` (required) — RFC 5424 severity level string.
- `$message: string` (required) — Log message.
- `$context: array` (required) — Arbitrary metadata, JSON-encoded after the message.
- **Side effect:** Appends to log file on disk
- **Side effect:** May delete old log files during rotation

### Methods

#### `__construct(path, level, days)`

## `Skim\Log\Log` — log

> Static log facade with RFC 5424 levels, lazy handler resolution, and profiler integration.

`facade` `log` `profiler` `lazy`

**Source:** `src/Log/Log.php` · **Layer:** `log` · **Lifecycle:** `static facade, handler resolved on first log call`

`log` is the static entry point for all application logging. It resolves the configured handler on first write and records every entry in the profiler for the debug toolbar.

### Core Behavior
- Eight level-specific methods delegate to write()
- write() captures caller file/line via backtrace
- Profiler::log receives every entry

### Log Levels

#### `debug(msg, ctx = []): void`
Logs a message at debug level. Records in profiler and delegates to the active handler.
- `$msg: string` (required) — Log message.
- `$ctx: array` (optional) — Arbitrary context metadata.

#### `info(msg, ctx = []): void`
Logs a message at info level. Records in profiler and delegates to the active handler.
- `$msg: string` (required) — Log message.
- `$ctx: array` (optional) — Arbitrary context metadata.

#### `notice(msg, ctx = []): void`
Logs a message at notice level.
- `$msg: string` (required) — Log message.
- `$ctx: array` (optional) — Arbitrary context metadata.

#### `warning(msg, ctx = []): void`
Logs a message at warning level.
- `$msg: string` (required) — Log message.
- `$ctx: array` (optional) — Arbitrary context metadata.

#### `error(msg, ctx = []): void`
Logs a message at error level. Use for recoverable failures that need attention.
- `$msg: string` (required) — Log message.
- `$ctx: array` (optional) — Arbitrary context metadata.

#### `critical(msg, ctx = []): void`
Logs a message at critical level. Use for application-level failures.
- `$msg: string` (required) — Log message.
- `$ctx: array` (optional) — Arbitrary context metadata.

#### `alert(msg, ctx = []): void`
Logs a message at alert level. Use for conditions requiring immediate action.
- `$msg: string` (required) — Log message.
- `$ctx: array` (optional) — Arbitrary context metadata.

#### `emergency(msg, ctx = []): void`
Logs a message at emergency level. Use for system-wide failures.
- `$msg: string` (required) — Log message.
- `$ctx: array` (optional) — Arbitrary context metadata.

### Testing Hooks

#### `setHandler(handler): void`
Replaces the active handler. Use in tests or to swap in Monolog for production channels.
- `$handler: LogHandler` (required) — Custom handler implementation.
- **Side effect:** Mutates static handler state

#### `reset(): void`
Clears the cached handler. The next log call resolves the handler again from config.
- **Side effect:** Clears static handler state

## `Skim\Log\NullHandler` — NullHandler

> Log handler that discards all entries — used in tests and null-channel config.

`handler` `log` `null-object` `testing`

**Source:** `src/Log/NullHandler.php` · **Layer:** `log` · **Lifecycle:** `stateless, no resources opened`

`NullHandler` implements `LogHandler` by discarding every write. It is the fallback when no file handler is configured and the default in test environments.

### Core Behavior
- Accepts all log levels and discards them silently

### Contract Implementation

#### `write(level, message, context): void`
Accepts the log entry and discards it. No I/O, no exceptions.
- `$level: string` (required) — RFC 5424 severity level string.
- `$message: string` (required) — Log message.
- `$context: array` (required) — Arbitrary metadata.

## `Skim\Middleware\Cors` — cors

> CORS middleware that adds cross-origin headers and handles OPTIONS preflight.

`middleware` `cors` `http` `security`

**Source:** `src/Middleware/Cors.php` · **Layer:** `middleware` · **Lifecycle:** `registered as global middleware`

`cors` adds Access-Control-Allow-* headers to every response and short-circuits OPTIONS preflight requests with a 204. It must run before auth middleware since browsers send preflight without credentials.

### Core Behavior
- Adds Allow-Origin, Allow-Methods, Allow-Headers, Max-Age headers
- Short-circuits OPTIONS with 204 empty response

### Middleware

#### `handle(req, res, next): mixed`
Adds CORS headers to every response. Short-circuits OPTIONS preflight with 204 without calling $next.
- `$req: request` (required) — Current HTTP request.
- `$res: response` (required) — Current HTTP response.
- `$next: callable` (required) — Next middleware or controller.
- **Returns** `mixed` — Response with CORS headers, or 204 for OPTIONS preflight.
- **Side effect:** Adds Access-Control-* headers to response

### Methods

#### `__construct(allowOrigin, allowMethods, allowHeaders, maxAge)`

## `Skim\Middleware\RateLimit` — RateLimit

> Redis-backed sliding window rate limiter with fail-open fallback.

`middleware` `rate-limit` `redis` `fail-open`

**Source:** `src/Middleware/RateLimit.php` · **Layer:** `middleware` · **Lifecycle:** `registered per-route or per-group`

`RateLimit` uses a Redis sorted set sliding window to count requests per IP. When the limit is exceeded, it returns 429. When Redis is unavailable, it fails open and passes all requests through.

### Core Behavior
- Counts requests per IP in Redis sorted set
- Short-circuits with 429 when count exceeds limit
- Adds rate limit headers to responses

### Middleware

#### `handle(req, res, next): mixed`
Counts the request in a Redis sliding window per IP. Returns 429 when the limit is exceeded. Adds X-RateLimit-* headers. Falls open when Redis is unavailable.
- `$req: request` (required) — Current HTTP request (IP extracted for key).
- `$res: response` (required) — Current HTTP response (headers added).
- `$next: callable` (required) — Next middleware or controller.
- **Returns** `mixed` — Response with rate limit headers, or 429 JSON when limit exceeded.
- **Side effect:** Writes to Redis sorted set
- **Side effect:** Adds X-RateLimit-* response headers

### Methods

#### `__construct(limit, window, prefix)`

## `Skim\Middleware\ToolbarMiddleware` — toolbar_middleware

> Injects the debug toolbar HTML into text/html responses when APP_DEBUG is true.

`middleware` `debug` `toolbar` `dev-only`

**Source:** `src/Middleware/ToolbarMiddleware.php` · **Layer:** `middleware` · **Lifecycle:** `registered as global middleware`

`ToolbarMiddleware` appends the SKIM debug toolbar before `</body>` in HTML responses. It only activates when debug mode is enabled and the request is a standard page load (not JSON, AJAX, or hypermedia).

### Core Behavior
- Calls $next first to collect profiler data
- Checks debug flag, request type, and content type
- Injects toolbar HTML before </body>

### Middleware

#### `handle(req, res, next): mixed`
Runs the next middleware/controller, then injects toolbar HTML into the response if all conditions are met: debug enabled, non-API request, text/html content type, and </body> present.
- `$req: request` (required) — Current HTTP request.
- `$res: response` (required) — Current HTTP response.
- `$next: callable` (required) — Next middleware or controller in the pipeline.
- **Returns** `mixed` — The response, possibly with toolbar HTML injected.
- **Side effect:** Modifies response body
- **Side effect:** Adds X-Debug response header

## `Skim\Queue\BaseJob` — BaseJob

> Convenience base class for queue jobs with default retry count, delay, and error logging.

`queue` `job` `abstract` `base-class` `defaults`

**Source:** `src/Queue/BaseJob.php` · **Layer:** `queue` · **Lifecycle:** `instantiated by worker via unserialize, handle() called, failed() called on exhaustion`

`BaseJob` is a convenience abstract class that implements the `job` interface with sensible defaults: 3 retry attempts, 0-second delay, and error_log on permanent failure. Most job classes extend this and override only `handle()`.

### Core Behavior
- Provides default tries() of 3
- Provides default delay() of 0
- failed() writes to error_log with job class name and exception message

### Job Configuration

#### `tries(): int`
Returns the default retry count of 3. Override in subclass for different retry behavior.
- **Returns** `int` — Number of retry attempts before failed() is called.

#### `delay(): int`
Returns the default delay of 0 seconds. Override to delay first execution.
- **Returns** `int` — Delay in seconds before first attempt.

### Failure Handling

#### `failed(e): void`
Logs the error when all retries are exhausted. Default writes to error_log. Override for alerts or dead-letter handling.
- `$e: \Throwable` (required) — The exception that caused the final failure.
- **Note:** Does not rethrow — the worker catches any exception from failed() anyway.

## `Skim\Queue\Queue` — queue

> Redis-backed job queue using LPUSH/BRPOP for O(1) operations and sorted sets for delayed jobs.

`queue` `redis` `static-facade` `job-dispatch`

**Source:** `src/Queue/Queue.php` · **Layer:** `queue` · **Lifecycle:** `static facade, Redis connection resolved lazily on first use from config`

`queue` is the static facade for the Redis-backed job queue. Jobs are serialized and LPUSHed into Redis lists. Workers dequeue with BRPOP for blocking, latency-free operation. Delayed jobs use a sorted set keyed by executeAt timestamp and are promoted to the main list by the worker.

### Core Behavior
- push() LPUSHes to Redis list or ZADDs to delayed sorted set
- pushMany() uses pipeline for atomic batch
- promoteDelayed() scans sorted set for due jobs
- size() returns LLEN count
- flush() DELetes queue key

### Enqueue

#### `push(job, queue = 'default'): void`
Pushes a job to the queue. Jobs with delay > 0 go into a sorted set
- `$job: job` (required) — Job instance to enqueue. Must be serializable.
- `$queue: string` (optional) — Queue name for multi-queue support.
- **Side effect:** Writes to Redis list or sorted set.

#### `pushMany(jobs, queue = 'default'): void`
Pushes multiple jobs atomically via Redis pipeline. All jobs use the same queue. Does not support delayed jobs.
- `$jobs: array` (required) — Array of job instances.
- `$queue: string` (optional) — Queue name for all jobs.
- **Side effect:** Writes multiple entries to Redis list via pipeline.

### Delayed Jobs

#### `promoteDelayed(): int`
Moves due delayed jobs from the sorted set to their target queue. Called by worker on each loop iteration.
- **Returns** `int` — Number of jobs promoted.
- **Side effect:** Moves entries from sorted set to Redis lists.

### Queue Inspection

#### `size(queue = 'default'): int`
Returns the approximate count of pending jobs in a queue via Redis LLEN.
- `$queue: string` (optional) — Queue name.
- **Returns** `int` — Number of pending jobs.

#### `flush(queue = 'default'): void`
Removes all pending jobs from a queue by deleting the Redis key.
- `$queue: string` (optional) — Queue name to flush.
- ⚠ Destroys all pending jobs in the queue — already-processing jobs are not affected
- **Side effect:** Deletes Redis queue key.

### Testing Hooks

#### `setRedis(redis): void`
Injects a mock Redis instance for testing. Bypasses config-based connection.
- `$redis: \Redis` (required) — Mock or fake Redis instance.
- **Side effect:** Replaces static Redis connection.

### Architecture

#### `serializeJob(job, queue): string`
Serializes a job into a JSON payload containing class name, serialized payload, queue, tries, attempts, and timestamp.
- `$job: job` (required) — Job instance to serialize.
- `$queue: string` (required) — Queue name embedded in the payload.
- **Returns** `string` — JSON-encoded payload string.

#### `deserialize(raw): array`
Deserializes a raw Redis JSON payload into a job data array.
- `$raw: string` (required) — JSON string from Redis.
- **Returns** `array` — Decoded job data array.

#### `redis(): \Redis`
Returns the cached Redis connection, creating it lazily from config/cache.php on first use.
- **Note:** Public because worker and QueueCommand access it directly for restart signals and status.

## `Skim\Queue\Worker` — worker

> Long-lived queue worker that polls Redis with BRPOP, executes jobs, and handles retries with exponential back-off.

`queue` `worker` `long-lived` `retry` `signal-handling`

**Source:** `src/Queue/Worker.php` · **Layer:** `queue` · **Lifecycle:** `instantiated by QueueCommand, work() blocks until stopped by signal, maxJobs, or restart signal`

`worker` is the long-lived CLI process that polls Redis for queued jobs using BRPOP. It promotes delayed jobs, unserializes and executes each job, handles retries with exponential back-off (5s, 10s, 15s...), and supports graceful shutdown via SIGTERM/SIGINT and Redis restart signals.

### Core Behavior
- Promotes delayed jobs each iteration
- BRPOP blocks efficiently without spinning
- Retries with exponential back-off (attempts * 5 seconds)
- Calls failed() on retry exhaustion
- Graceful shutdown via SIGTERM/SIGINT
- Redis restart signal checked each loop

### Worker Execution

#### `work(): void`
Runs the main work loop. Blocks until stop() is called, a process signal is received, or maxJobs is reached.

#### `stop(): void`
Signals the worker to stop after the current job completes. Called by signal handlers and restart signal check.

### Job Processing

#### `process(raw): void`
Unserializes a job from raw Redis payload, executes handle(), and manages retries with exponential back-off on failure.
- `$raw: string` (required) — JSON payload from Redis BRPOP.

### Signal Handling

#### `registerSignals(): void`
Registers SIGTERM and SIGINT handlers for graceful shutdown. No-ops when pcntl extension is not available.

#### `checkRestartSignal(): void`
Checks the Redis restart signal timestamp. Stops the worker if the signal is newer than the process start time.

### Methods

#### `__construct(queue, sleep, maxJobs)`

## `Skim\Realtime\Datastar` — datastar

> Datastar v1 SSE driver implementing ElementPatcher, SignalPatcher, and ScriptRunner.

`datastar` `v1` `realtime` `dom-patching` `signals`

**Source:** `src/Realtime/Datastar.php` · **Layer:** `realtime` · **Lifecycle:** `created per-stream by Response::stream() or container resolution`

`datastar` is the official Datastar v1 driver for SKIM. It emits `datastar-patch-elements` and `datastar-patch-signals` events over an injected SSE transport, enabling server-driven UI updates without hand-written JavaScript state.

### Core Behavior
- patch() sends datastar-patch-elements
- remove() sends datastar-patch-elements with mode remove
- signals() sends datastar-patch-signals
- run() appends a script element to body
- viewFragment() renders and patches in one call

### Warnings
- ⚠ run() executes arbitrary JavaScript in the browser — use sparingly and never with user-supplied input

### DOM Operations

#### `patch(html, selector = '', mode = 'morph'): static`
Sends a datastar-patch-elements event with the given HTML fragment, selector, and mode. Each line of HTML is prefixed with 'data: elements' per the v1 protocol.
- `$html: string` (required) — HTML fragment to patch into the DOM.
- `$selector: string` (optional) — CSS selector of target element.
- `$mode: string` (optional) — Patch mode: morph, inner, outer, prepend, append, before, after, replace.
- **Side effect:** Writes SSE event to output buffer via transport

#### `remove(selector): static`
Sends a datastar-patch-elements event with mode remove to delete matching elements.
- `$selector: string` (required) — CSS selector of elements to remove.
- **Side effect:** Writes SSE event to output buffer via transport

### Signal Operations

#### `signals(signals, onlyIfMissing = false): static`
Sends a datastar-patch-signals event merging the given key-value pairs into reactive signals.
- `$signals: array` (required) — Key-value pairs to merge.
- `$onlyIfMissing: bool` (optional) — Only set signals that do not already exist.
- **Side effect:** Writes SSE event to output buffer via transport

### Script Execution

#### `run(js): static`
Appends a <script> element to the body via datastar-patch-elements. The browser evaluates it and Datastar removes it.
- `$js: string` (required) — JavaScript code to evaluate.
- ⚠ Executes arbitrary JavaScript — never pass user input
- **Side effect:** Writes SSE event to output buffer via transport
- **Side effect:** Executes JS in browser

### View Integration

#### `viewFragment(template, data, fragment, selector = ''): static`
Renders a named view fragment and patches it into the DOM in one call.
- `$template: string` (required) — Template path.
- `$data: array` (required) — Template variables.
- `$fragment: string` (required) — Fragment name.
- `$selector: string` (optional) — CSS selector for patch target.
- **Side effect:** Renders view
- **Side effect:** Writes SSE event to output buffer via transport

### Methods

#### `__construct(transport)`

## `Skim\Realtime\Sse` — sse

> Server-Sent Events helper for pushing data over long-lived HTTP connections.

`sse` `streaming` `realtime` `push`

**Source:** `src/Realtime/Sse.php` · **Layer:** `realtime` · **Lifecycle:** `created per-stream by Response::stream() callback`

`sse` provides methods to send events, pings, and close signals over a Server-Sent Events stream. It is passed to the Response::stream() callback and handles SSE wire format and output flushing.

### Core Behavior
- Formats data as SSE wire protocol
- JSON-encodes arrays
- Flushes output buffer after each event

### Stream API

#### `send(data, event = null, id = null): void`
Formats and sends one SSE event. Arrays are JSON-encoded. Named events allow client-side addEventListener. Flushes immediately.
- `$data: mixed` (required) — Array (JSON-encoded) or string payload.
- `$event: ?string` (optional) — Optional event name for client addEventListener.
- `$id: ?string` (optional) — Optional event ID for Last-Event-ID tracking.
- **Side effect:** Writes to output buffer and flushes

### Connection Management

#### `ping(): void`
Sends an SSE comment line (`: ping`) to prevent idle proxy timeouts. Call every 15-30 seconds.
- **Side effect:** Writes to output buffer and flushes

#### `close(): void`
Sends a final event named 'close' to signal stream end to the client.
- **Side effect:** Writes to output buffer and flushes

## `Skim\Session\FileSessionDriver` — FileSessionDriver

> Native PHP file-backed session driver for single-server deployments.

`session` `driver` `file` `native-php`

**Source:** `src/Session/FileSessionDriver.php` · **Layer:** `session` · **Lifecycle:** `instantiated by session facade, start() called once per request`

`FileSessionDriver` wraps PHP's native `session_*` functions with a configured save path and secure cookie parameters. It is the default session driver for single-server setups.

### Core Behavior
- Delegates all read/write to $_SESSION superglobal
- Configures cookie params on every start() call
- regenerate() deletes old session file

### Warnings
- ⚠ flush() destroys all session data irreversibly
- ⚠ Direct $_SESSION access bypasses driver abstraction

### Session API

#### `get(key, default = null): mixed`
Returns session value by key, or $default if absent.
- `$key: string` (required) — Session key to read.
- `$default: mixed` (optional) — Fallback when key is missing.
- **Returns** `mixed` — The stored value or $default.

#### `set(key, value): void`
Stores a value in the session via $_SESSION.
- `$key: string` (required) — Session key to write.
- `$value: mixed` (required) — Payload to persist.

#### `has(key): bool`
Returns true when the key exists in the session.
- `$key: string` (required) — Session key to test.
- **Returns** `bool` — True if key exists.

#### `delete(key): void`
Removes a key from the session.
- `$key: string` (required) — Session key to remove.

#### `id(): string`
Returns the current session ID.
- **Returns** `string` — The active session identifier.

### Lifecycle

#### `start(): void`
Starts or resumes a file-backed session. No-ops when a session is already active. Creates the save_path directory if missing and configures secure cookie params.

#### `regenerate(): void`
Regenerates the session ID and deletes the old session file. Call after login/logout to prevent session fixation.
- **Side effect:** Deletes old session file
- **Side effect:** Generates new Session ID

#### `flush(): void`
Destroys session data and invalidates the session.
- ⚠ Irreversible — all session data for this ID is lost immediately
- **Side effect:** Destroys session file
- **Side effect:** Clears $_SESSION
- **Side effect:** Resets internal ID

### Methods

#### `__construct(path, lifetime)`

## `Skim\Session\RedisSessionDriver` — RedisSessionDriver

> Redis-backed session driver for multi-server and load-balanced deployments.

`session` `driver` `redis` `multi-server`

**Source:** `src/Session/RedisSessionDriver.php` · **Layer:** `session` · **Lifecycle:** `instantiated by session facade, start() called once per request, Redis connection opened lazily`

`RedisSessionDriver` stores each session as a single serialized JSON blob in Redis, keyed by a configurable prefix plus the session ID. It is required for load-balanced deployments where file-based sessions would not be shared across workers.

### Core Behavior
- Session data is stored as a single JSON blob per session ID
- Cookie is sent only for new sessions
- regenerate() migrates data to a new Redis key

### Warnings
- ⚠ flush() deletes session data from Redis irreversibly
- ⚠ Each write re-serializes the full session — avoid storing large payloads

### Session API

#### `get(key, default = null): mixed`
Returns session value by key from the in-memory data array, or $default if absent.
- `$key: string` (required) — Session key to read.
- `$default: mixed` (optional) — Fallback when key is missing.
- **Returns** `mixed` — The stored value or $default.

#### `set(key, value): void`
Stores a value in the in-memory data array and immediately persists the full session to Redis.
- `$key: string` (required) — Session key to write.
- `$value: mixed` (required) — Payload to persist.
- **Side effect:** Re-serializes full session and writes to Redis via SETEX

#### `has(key): bool`
Returns true when the key exists in the in-memory session data.
- `$key: string` (required) — Session key to test.
- **Returns** `bool` — True if key exists.

#### `delete(key): void`
Removes a key from the in-memory data and persists the change to Redis.
- `$key: string` (required) — Session key to remove.
- **Side effect:** Re-serializes full session and writes to Redis via SETEX

#### `id(): string`
Returns the current session ID.
- **Returns** `string` — The active session identifier.

### Lifecycle

#### `start(): void`
Starts or resumes a Redis-backed session. Reads session ID from cookie or generates new one. Loads data from Redis and renews TTL. Sends cookie only for new sessions.

#### `regenerate(): void`
Deletes the old Redis session key, generates a new Session ID, sends a fresh cookie, and persists existing data under the new key.
- **Side effect:** Deletes old Redis key
- **Side effect:** Generates new Session ID
- **Side effect:** Sends Set-Cookie header
- **Side effect:** Writes new Redis key

#### `flush(): void`
Deletes session data from Redis and expires the session cookie.
- ⚠ Irreversible — all session data for this ID is deleted from Redis
- **Side effect:** Deletes Redis key
- **Side effect:** Clears in-memory data
- **Side effect:** Expires cookie

### Methods

#### `__construct(host, port, password, prefix, lifetime)`

## `Skim\Session\Session` — session

> Static session facade with flash message support, lazy driver resolution, and test injection.

`facade` `session` `flash` `driver-backed`

**Source:** `src/Session/Session.php` · **Layer:** `session` · **Lifecycle:** `static facade, driver resolved on first session call`

`session` is the static entry point for session operations. It resolves the configured session driver on first use and adds flash message support on top of the raw driver API.

### Core Behavior
- Flash values use __flash__ prefix internally
- get() consumes flash values on read
- Auto-starts session on any read/write operation

### Warnings
- ⚠ flush() destroys all session data irreversibly

### Read API

#### `get(key, default = null): mixed`
Returns session value by key. Checks for a flash-prefixed key first
- `$key: string` (required) — Session key to read.
- `$default: mixed` (optional) — Fallback when key is missing.
- **Returns** `mixed` — The stored value, flash value, or $default.
- **Side effect:** Consumes (deletes) flash values on read

#### `has(key): bool`
Returns true when the key or its flash variant (__flash__ prefix) exists.
- `$key: string` (required) — Session key to test.
- **Returns** `bool` — True if regular or flash key exists.

#### `id(): string`
Returns the current session ID.
- **Returns** `string` — The active session identifier.

### Write API

#### `set(key, value): void`
Stores a value in the session for the current and future requests.
- `$key: string` (required) — Session key to write.
- `$value: mixed` (required) — Payload to persist.

#### `delete(key): void`
Removes a key from the session. Does not remove the flash variant.
- `$key: string` (required) — Session key to remove.

### Flash Messages

#### `flash(key, value): void`
Stores a flash value that is available on the current read and auto-deleted on the first get() call.
- `$key: string` (required) — Flash key, read back via get() without prefix.
- `$value: mixed` (required) — One-time payload.
- **Side effect:** Writes to session with __flash__ prefix

### Lifecycle

#### `start(): void`
Boots the session driver. Idempotent — safe to call multiple times. Called automatically by get/set/has/delete.

#### `regenerate(): void`
Regenerates the session ID to prevent session fixation. Call after login/logout.

#### `flush(): void`
Destroys session data and resets the facade to unstarted state.
- ⚠ Irreversible — all session data including flash values is lost
- **Side effect:** Destroys session via driver
- **Side effect:** Resets started flag

### Testing Hooks

#### `resetRequest(): void`
Closes the active session and resets driver state between requests in worker mode.
- **Side effect:** Calls session_write_close() if started
- **Side effect:** clears driver and started flag

#### `setDriver(driver): void`
Replaces the active driver instance. Use in tests to bypass config-based resolution.
- `$driver: SessionDriver` (required) — Mock or fake driver for testing.
- **Side effect:** Replaces static driver
- **Side effect:** Resets started flag

#### `reset(): void`
Clears the cached driver and resets to unstarted state. Forces re-resolution from config on next call.
- **Side effect:** Clears static driver and started flag

### Architecture

#### `driver(): SessionDriver`
Returns the cached driver instance, resolving lazily if null.

#### `resolveDriver(): SessionDriver`
Maps config('app.session.driver') to a concrete driver instance. Defaults to FileSessionDriver.

## `Skim\Testing\AuthFake` — AuthFake

> In-memory auth double that always reports authenticated with a given user object.

`testing` `fake` `auth` `double`

**Source:** `src/Testing/AuthFake.php` · **Layer:** `testing` · **Lifecycle:** `instantiated per-test, bound into the container via App::bind()`

`AuthFake` is a minimal test double for the auth service. It always reports the user as authenticated and returns the injected user object directly.

### Core Behavior
- Provides a deterministic authenticated state without database or session dependencies

### Auth API

#### `user(): object`
Returns the injected user object as-is.
- **Returns** `object` — The user object passed to the constructor.

#### `check(): bool`
Always returns true to simulate authenticated state.
- **Returns** `bool` — Always true.

#### `guest(): bool`
Always returns false to simulate authenticated state.
- **Returns** `bool` — Always false.

#### `id(): mixed`
Returns the user's id property, or null if not set.
- **Returns** `mixed` — The user ID or null.

### Methods

#### `__construct(user)`

## `Skim\Testing\HttpClient` — HttpClient

> In-process HTTP test client that dispatches requests through the app without a real server.

`testing` `http` `client` `in-process`

**Source:** `src/Testing/HttpClient.php` · **Layer:** `testing` · **Lifecycle:** `instantiated per-test, wraps an app instance`

`HttpClient` provides a fluent API for testing HTTP endpoints in-process. Each method creates a fresh `PendingRequest`, so configuration does not leak between calls.

### Core Behavior
- Delegates all configuration and dispatch to PendingRequest
- Provides a convenience layer for common test patterns

### Configuration

#### `actingAs(user): PendingRequest`
Creates a pending request authenticated as the given user.
- `$user: object` (required) — User object injected into the auth service.
- **Returns** `PendingRequest` — Configured pending request ready for HTTP method calls.

#### `withHeaders(headers): PendingRequest`
Creates a pending request with custom headers.
- `$headers: array` (required) — Key-value header pairs.
- **Returns** `PendingRequest` — Configured pending request.

#### `withSession(data): PendingRequest`
Creates a pending request with pre-populated session data.
- `$data: array` (required) — Key-value session pairs.
- **Returns** `PendingRequest` — Configured pending request.

#### `followingRedirects(): PendingRequest`
Creates a pending request that automatically follows 3xx redirects.
- **Returns** `PendingRequest` — Configured pending request.

#### `withoutMiddleware(): PendingRequest`
Creates a pending request that skips all middleware during dispatch.
- **Returns** `PendingRequest` — Configured pending request.

### HTTP Methods

#### `get(path, query = []): HttpResponse`
Sends a GET request through the app.
- `$path: string` (required) — Request URI path.
- `$query: array` (optional) — Query string parameters.
- **Returns** `HttpResponse` — Test response with assertion methods.

#### `post(path, post = [], json = []): HttpResponse`
Sends a POST request. When $json is non-empty, sets Content-Type to application/json.
- `$path: string` (required) — Request URI path.
- `$post: array` (optional) — Form-encoded POST data.
- `$json: array` (optional) — JSON body data.
- **Returns** `HttpResponse` — Test response with assertion methods.

#### `put(path, post = []): HttpResponse`
Sends a PUT request with form-encoded body data.
- `$path: string` (required) — Request URI path.
- `$post: array` (optional) — Form-encoded body data.
- **Returns** `HttpResponse` — Test response with assertion methods.

#### `delete(path): HttpResponse`
Sends a DELETE request.
- `$path: string` (required) — Request URI path.
- **Returns** `HttpResponse` — Test response with assertion methods.

### Methods

#### `__construct(app)`

## `Skim\Testing\HttpResponse` — HttpResponse

> Fluent assertion wrapper for HTTP test responses with status, JSON, header, and body assertions.

`testing` `assertions` `http` `fluent`

**Source:** `src/Testing/HttpResponse.php` · **Layer:** `testing` · **Lifecycle:** `returned by PendingRequest/HttpClient HTTP methods, used inline in test cases`

`HttpResponse` wraps a `Skim\Core\Response` and provides fluent assertion methods powered by Pest's `expect()`. All `assert_*` methods return `$this` for chaining.

### Core Behavior
- Wraps core response for test assertions
- Uses Pest expect() for failure reporting
- Provides both assertion and accessor methods

### Status Assertions

#### `assertStatus(code): static`
Asserts the response status code matches the expected value. Throws on mismatch.
- `$code: int` (required) — Expected HTTP status code.
- **Returns** `static` — $this for chaining.

#### `assertOk(): static`
Asserts 200 OK status.
- **Returns** `static` — $this for chaining.

#### `assertCreated(): static`
Asserts 201 Created status.
- **Returns** `static` — $this for chaining.

#### `assertNoContent(): static`
Asserts 204 No Content status.
- **Returns** `static` — $this for chaining.

#### `assertNotFound(): static`
Asserts 404 Not Found status.
- **Returns** `static` — $this for chaining.

#### `assertUnauthorized(): static`
Asserts 401 Unauthorized status.
- **Returns** `static` — $this for chaining.

#### `assertForbidden(): static`
Asserts 403 Forbidden status.
- **Returns** `static` — $this for chaining.

#### `assertUnprocessable(): static`
Asserts 422 Unprocessable Entity status.
- **Returns** `static` — $this for chaining.

#### `assertRedirect(url): static`
Asserts a redirect status and matching Location header.
- `$url: string` (required) — Expected redirect target URL.
- **Returns** `static` — $this for chaining.

### Content Assertions

#### `assertJson(data): static`
Asserts the JSON body contains the expected data as a subset match.
- `$data: array` (required) — Expected key-value pairs (subset match).
- **Returns** `static` — $this for chaining.

#### `assertHeader(key, value): static`
Asserts a response header has the expected value.
- `$key: string` (required) — Header name.
- `$value: string` (required) — Expected header value.
- **Returns** `static` — $this for chaining.

#### `assertContains(text): static`
Asserts the response body contains the given substring.
- `$text: string` (required) — Substring expected in the body.
- **Returns** `static` — $this for chaining.

### Accessors

#### `status(): int`
Returns the HTTP status code.
- **Returns** `int` — The response status code.

#### `json(): array`
Returns the decoded JSON body.
- **Returns** `array` — Decoded JSON response body.

#### `body(): string`
Returns the raw response body.
- **Returns** `string` — Raw response body string.

#### `header(key): ?string`
Returns a specific header value or null.
- `$key: string` (required) — Header name.
- **Returns** `?string` — Header value or null if not set.

#### `isRedirect(): bool`
Returns true for 3xx redirect status codes (301, 302, 303, 307, 308).
- **Returns** `bool` — True if status is a redirect.

### Debug

#### `dump(): static`
Dumps the response body for debugging and returns $this for continued chaining.
- **Returns** `static` — $this for chaining.

#### `dd(): never`
Dumps the response body and halts execution.
- ⚠ Halts PHP execution — use only during debugging

### Methods

#### `__construct(res)`

## `Skim\Testing\PendingRequest` — PendingRequest

> Immutable request builder that dispatches through the app with auth, session, and header injection.

`testing` `http` `immutable` `builder`

**Source:** `src/Testing/PendingRequest.php` · **Layer:** `testing` · **Lifecycle:** `created per-request by HttpClient, cloned on each configuration call, consumed on HTTP method call`

`PendingRequest` is an immutable builder that configures and dispatches in-process HTTP requests. Each configuration method returns a clone, preventing state leakage between test assertions.

### Core Behavior
- Builds a request via RequestFactory
- Clones the app and binds auth/session fakes
- Dispatches through App::dispatch()

### Configuration

#### `actingAs(user): static`
Returns a clone configured to authenticate as the given user via AuthFake.
- `$user: object` (required) — User object bound to AuthService in the cloned container.
- **Returns** `static` — New clone with user configured.

#### `withHeaders(headers): static`
Returns a clone with additional request headers merged into existing ones.
- `$headers: array` (required) — Key-value header pairs.
- **Returns** `static` — New clone with headers merged.

#### `withSession(data): static`
Returns a clone with pre-populated session data bound via SessionFake.
- `$data: array` (required) — Key-value session pairs.
- **Returns** `static` — New clone with session data merged.

#### `withCookies(cookies): static`
Returns a clone with the given cookies.
- `$cookies: array` (required) — Key-value cookie pairs.
- **Returns** `static` — New clone with cookies set.

#### `followingRedirects(): static`
Returns a clone that automatically follows 3xx redirects via recursive get().
- **Returns** `static` — New clone with redirect following enabled.

#### `withoutMiddleware(): static`
Returns a clone that skips all middleware during dispatch.
- **Returns** `static` — New clone with middleware skipping enabled.

### HTTP Methods

#### `get(path, query = []): HttpResponse`
Dispatches a GET request through the app.
- `$path: string` (required) — Request URI path.
- `$query: array` (optional) — Query string parameters.
- **Returns** `HttpResponse` — Test response with assertion methods.

#### `post(path, post = [], json = []): HttpResponse`
Dispatches a POST request. Sets Content-Type to application/json when $json is non-empty.
- `$path: string` (required) — Request URI path.
- `$post: array` (optional) — Form-encoded POST data.
- `$json: array` (optional) — JSON body data.
- **Returns** `HttpResponse` — Test response with assertion methods.

#### `put(path, post = []): HttpResponse`
Dispatches a PUT request with form-encoded body data.
- `$path: string` (required) — Request URI path.
- `$post: array` (optional) — Form-encoded body data.
- **Returns** `HttpResponse` — Test response with assertion methods.

#### `delete(path): HttpResponse`
Dispatches a DELETE request.
- `$path: string` (required) — Request URI path.
- **Returns** `HttpResponse` — Test response with assertion methods.

### Methods

#### `__construct(app)`

## `Skim\Testing\RequestFactory` — RequestFactory

> Factory that builds request objects from raw parameters with proper $_SERVER header mapping.

`testing` `factory` `request` `infrastructure`

**Source:** `src/Testing/RequestFactory.php` · **Layer:** `testing` · **Lifecycle:** `called per-request by PendingRequest::send()`

`RequestFactory` constructs `Skim\Core\Request` instances from raw parameters, handling the mapping from human-readable header names to PHP's `$_SERVER`-style keys.

### Core Behavior
- Maps header names to $_SERVER convention
- Merges REQUEST_METHOD and REQUEST_URI into server array
- Passes all data to request constructor

### Factory

#### `make(method = 'GET', path = '/', query = [], post = [], headers = [], rawBody = '', cookies = [], files = []): request`
Builds a request object from raw parameters. Maps human-readable headers to $_SERVER-style keys with proper HTTP_ prefix handling.
- `$method: string` (optional) — HTTP method, defaults to GET.
- `$path: string` (optional) — Request URI path, defaults to /.
- `$query: array` (optional) — Query string parameters.
- `$post: array` (optional) — Form-encoded body data.
- `$headers: array` (optional) — Human-readable headers auto-mapped to $_SERVER keys.
- `$rawBody: string` (optional) — Raw request body for JSON or other content types.
- `$cookies: array` (optional) — Cookie key-value pairs.
- `$files: array` (optional) — Uploaded file entries.
- **Returns** `request` — Fully configured request object ready for dispatch.

## `Skim\Testing\SessionFake` — SessionFake

> In-memory session double for tests with no cookies, headers, or persistence.

`testing` `fake` `session` `in-memory`

**Source:** `src/Testing/SessionFake.php` · **Layer:** `testing` · **Lifecycle:** `instantiated per-test, bound into the container via App::bind()`

`SessionFake` provides a minimal in-memory session implementation for tests. It stores data in a plain array with no side effects — no cookies, no headers, no Redis.

### Core Behavior
- Plain array storage
- Implements the session read/write/has/flush contract

### Session API

#### `set(key, value): void`
Stores a value in the in-memory session array.
- `$key: string` (required) — Session key.
- `$value: mixed` (required) — Payload to store.

#### `get(key, default = null): mixed`
Returns a value by key, or $default if absent.
- `$key: string` (required) — Session key to read.
- `$default: mixed` (optional) — Fallback when key is missing.
- **Returns** `mixed` — The stored value or $default.

#### `has(key): bool`
Returns true when the key exists in the in-memory data.
- `$key: string` (required) — Session key to test.
- **Returns** `bool` — True if key exists.

#### `flush(): void`
Clears all session data from the in-memory array.

#### `all(): array`
Returns all session data as an associative array.
- **Returns** `array` — Full session data array.

## `Skim\Validation\Result` — result

> Immutable validation result with ok (virtual property), errors(), and validated() accessors.

`validation` `result` `immutable` `value-object` `property-hooks`

**Source:** `src/Validation/Result.php` · **Layer:** `validation` · **Lifecycle:** `created by Validate::check(), consumed by controller in the same request`

`result` is the immutable value object returned by `Validate::check()`. It carries both the error map and the validated data subset, providing the three accessors controllers need: `ok` (virtual property), `errors()`, and `validated()`.

### Core Behavior
- Immutable — all properties are readonly
- errors() returns field-to-messages map
- validated() returns only rule-declared fields
- ok is a virtual property via PHP 8.4+ property hooks

### Result API

#### `errors(): array`
Returns the field-to-messages error map. Shape matches the standard 422 JSON response format.
- **Returns** `array` — Associative array of field name to list of error message strings.

#### `validated(): array`
Returns only the fields declared in validation rules. Undeclared fields are silently dropped.
- **Returns** `array` — Associative array of validated field names to their values.

### Methods

#### `__construct(errors, validated)`

## `Skim\Validation\Validate` — validate

> Zero-dependency validation engine with built-in rules, custom rule registration, and mass-assignment protection.

`validation` `engine` `zero-deps` `mass-assignment-safe`

**Source:** `src/Validation/Validate.php` · **Layer:** `validation` · **Lifecycle:** `instantiated per-validation via make(), check() runs synchronously`

`validate` is SKIM's built-in validation engine. It declares expected data shapes via `make()`, runs rules via `check()`, and returns a `result` with errors and validated data. Undeclared fields are silently dropped from `validated()`.

### Core Behavior
- Built-in rules: required, email, url, int, float, bool, slug, min, max, in, regex, same, nullable
- Custom rules via extend() with fluent return
- Rule objects implementing rule interface
- Inline callables in rule arrays
- Pipe-delimited or array rule syntax
- Typed validated() values via cast()

### Warnings
- ⚠ Unknown rule names silently pass — typos in rule names go undetected

### Validation API

#### `make(rules): static`
Factory that declares the field-to-rules map and returns a validator instance.
- `$rules: array` (required) — Map of field name to rule array or pipe-delimited string.
- **Returns** `static` — Validator instance ready for extend() and check().

#### `check(data): result`
Runs all declared rules against $data. Returns a result where ok is false if any field failed any rule. Only declared fields appear in validated().
- `$data: array` (required) — Input data to validate, typically $req->post().
- **Returns** `result` — Immutable result with errors and validated().

### Custom Rules

#### `extend(name, fn, message = 'Invalid.'): static`
Registers a custom rule on this validator instance. Returns $this for fluent chaining. The callback receives the field value and returns true on pass or false on fail.
- `$name: string` (required) — Rule name used in rule lists.
- `$fn: callable` (required) — Receives value, returns true on pass or false on fail.
- `$message: string` (optional) — Default error message. Use :field as placeholder for field name.

### Methods

#### `__construct(rules)`

### Internal

#### `cast(ruleNames, value): mixed`
Casts validated values to their PHP types based on declared rules. Returns typed value for int, float, bool, email rules
- `$ruleNames: array` (required) — Flat list of rule name strings for the field.
- `$value: mixed` (required) — The raw value from input data.
- **Returns** `mixed` — Typed value based on rule declarations.

## `Skim\View\ComponentCollector` — ComponentCollector

> Closure-based part capture for components with stack-scoped isolation.

`component` `parts` `closures` `isolation` `stack`

**Source:** `src/View/ComponentCollector.php` · **Layer:** `view` · **Lifecycle:** `instantiated per componentWithParts() call, pushed onto static stack during capture`

`ComponentCollector` captures named content blocks inside components via closures and output buffering. Each render gets its own instance

### Core Behavior
- captureMain buffers the closure output as mainPart
- capturePart buffers closure output into namedParts
- Stack enables nested components with independent part resolution

### Warnings
- ⚠ Calling current() when stack is empty returns null — global helpers must handle this

### Capture API

#### `captureMain(render): void`
Buffers the closure output as the main part. Pushes this collector onto the static stack before execution and pops after.
- `$render: callable` (required) — Closure receiving the collector instance.
- **Side effect:** Pushes then pops from static stack
- **Side effect:** Starts and ends output buffering

#### `part(name, render): void`
Alias for capturePart(). Buffers the closure output and stores it under the given part name.
- `$name: string` (required) — Part identifier used by the component template.
- `$render: callable` (required) — Closure that outputs the part content.
- **Side effect:** Starts and ends output buffering

#### `capturePart(name, render): void`
Buffers the closure output and stores it under the given part name.
- `$name: string` (required) — Part identifier used by the component template.
- `$render: callable` (required) — Closure that outputs the part content.
- **Side effect:** Starts and ends output buffering

### Query API

#### `getMain(): string`
Returns the output captured outside any named part() calls.
- **Returns** `string` — Main body HTML.

#### `getPart(name, default = ''): string`
Returns captured HTML for a named part, or $default if absent.
- `$name: string` (required) — Part identifier to retrieve.
- `$default: string` (optional) — Fallback HTML when the part is absent.
- **Returns** `string` — Named part HTML or default.

#### `hasPart(name): bool`
Returns true when the named part was captured.
- `$name: string` (required) — Part identifier to check.
- **Returns** `bool` — True if the part exists.

### Stack Management

#### `current(): ?self`
Returns the top of the static stack, or null when no component is rendering.
- **Returns** `?self` — Active collector or null.

#### `push(collector): void`
Pushes a collector onto the static stack. Used by componentWithParts() to keep the collector active during template rendering.
- `$collector: self` (required) — Collector instance to push.
- **Side effect:** Adds collector to static stack

#### `pop(): void`
Pops the top collector from the static stack. Must be paired with push().
- **Side effect:** Removes top collector from static stack

### Methods

#### `resetRequest(): void`
Clears the static collector stack between requests in worker mode. :resetRequest Under normal flow captureMain() pushes then pops, so the stack is empty between renders. If a component closure throws, the matching pop() never runs and a stale collector lingers in the process — this drops it so the next request starts with an empty stack.

## `Skim\View\ComponentRenderer` — ComponentRenderer

> Isolated component renderer enforcing strict props validation and clean template scope.

`component` `props` `isolation` `profiler`

**Source:** `src/View/ComponentRenderer.php` · **Layer:** `view` · **Lifecycle:** `stateless static class, invoked per component render`

`ComponentRenderer` renders reusable UI components in an isolated scope. It supports both legacy array props and readonly *_props objects, validating the latter to enforce naming conventions.

### Core Behavior
- Dual props: array (legacy) and readonly *_props object (new)
- Strict scope isolation via new Template() with null layout
- Props class naming validation
- Profiler integration for component timing

### Warnings
- ⚠ get_object_vars() only sees public properties — declare DTO props as public readonly

### Rendering API

#### `render(name, props = []): string`
Renders a component template in an isolated scope with validated props. Records timing in profiler.
- `$name: string` (required) — Component name (maps to views/components/{$name}.php).
- `$props: array` (optional) — Props array or *_props readonly object.
- **Returns** `string` — Rendered component HTML.
- **Throws** `ViewException` — If props object is not a *_props class or component file is not found.
- **Side effect:** Records render timing in Profiler::view()

### Validation

#### `validatePropsClass(class): void`
Throws when the class name does not end with '_props'. Enforces the props naming convention.
- `$class: string` (required) — FQCN of the props object.
- **Throws** `ViewException` — When the class name does not end in '_props'.

### Path Resolution

#### `componentsPath(): string`
Resolves the components subdirectory under the active views path by delegating to View::viewsPath().
- **Returns** `string` — Absolute path to the views/components directory.

## `Skim\View\Exceptions\ViewException` — ViewException

> Exception thrown when a template file is not found or a named fragment is missing.

`exception` `view` `error`

**Source:** `src/View/Exceptions/ViewException.php` · **Layer:** `view` · **Lifecycle:** `thrown during View::render() or Template::renderFile()`

`ViewException` is thrown by the view system when a template file cannot be resolved or a requested fragment name does not exist in the rendered output.

### Core Behavior
- Provides a specific exception type for view-layer errors

## `Skim\View\FragmentExtractor` — FragmentExtractor

> State-machine fragment parser replacing regex extraction with tokenized validation.

`fragment` `parser` `state-machine` `validation`

**Source:** `src/View/FragmentExtractor.php` · **Layer:** `view` · **Lifecycle:** `stateless static class, invoked per fragment extraction`

`FragmentExtractor` tokenizes HTML comment markers, validates structural constraints (flat only, balanced pairs), and extracts named fragment content. More robust than the previous regex approach.

### Core Behavior
- Tokenizes <!-- @fragment name --> and <!-- @end --> markers
- Validates flat structure and balanced markers
- Extracts named block by offset arithmetic
- Trims surrounding whitespace

### Warnings
- ⚠ Nested fragments throw ViewException
- ⚠ Unmatched @end throws ViewException
- ⚠ Missing fragment name throws ViewException

### Extraction API

#### `extract(html, name): string`
Tokenizes fragment markers, validates structure, and returns trimmed content for the named fragment.
- `$html: string` (required) — Rendered HTML containing fragment markers.
- `$name: string` (required) — Fragment identifier to extract.
- **Returns** `string` — Trimmed fragment content.
- **Throws** `ViewException` — On nested fragments, unmatched @end, unclosed @fragment, or missing name.

#### `extractByName(html, tokens, name): string`
Locates the named @fragment token and returns the substring between it and the following @end token.
- `$html: string` (required) — Original HTML string.
- `$tokens: array` (required) — Validated token list.
- `$name: string` (required) — Fragment identifier to extract.
- **Returns** `string` — Trimmed fragment content.
- **Throws** `ViewException` — When the named fragment is not found.

### Tokenization

#### `tokenize(html): array`
Scans HTML for fragment markers and returns a list of token arrays with type, name, offset, and length.
- `$html: string` (required) — Rendered HTML string.
- **Returns** `array` — List of token arrays.

### Validation

#### `validateNoNesting(tokens): void`
Validates that fragments are flat (depth <= 1) and all markers are balanced.
- `$tokens: array` (required) — Token list from tokenize().
- **Throws** `ViewException` — On nested fragments, unmatched @end, or unclosed @fragment.

## `Skim\View\Template` — template

> Template context object providing include, layout, and slot system for PHP views.

`template` `layout` `slots` `partials`

**Source:** `src/View/Template.php` · **Layer:** `view` · **Lifecycle:** `instantiated by View::render() per render call, used as $this inside templates`

`template` is the context object available as `$this` inside every PHP view file. It provides the layout system (layout/start/end/slot) and partial inclusion (include). Layout execution runs the child template first to capture slots, then renders the layout.

### Core Behavior
- Layout system: child renders first, captures slots, then layout renders with slot() calls
- Partial inclusion via include() with isolated data context
- Data extracted as local variables via extract()

### Warnings
- ⚠ end() without matching start() throws LogicException
- ⚠ extract() with EXTR_SKIP means data keys cannot override existing local variables

### Template API

#### `include(template, extra = []): string`
Renders a partial template within the current data context. The partial does not inherit the parent's layout.
- `$template: string` (required) — Template path relative to views root, no extension needed.
- `$extra: array` (optional) — Additional data merged for this partial only.
- **Returns** `string` — Rendered partial HTML.

### Layout System

#### `layout(name): void`
Declares the layout to wrap this template. Must be called before any output.
- `$name: string` (required) — Layout template path relative to views root.

#### `start(name): void`
Starts capturing output into a named slot. Must be paired with end().
- `$name: string` (required) — Slot identifier used by the layout to retrieve content.
- **Side effect:** Starts output buffering via ob_start()

#### `end(): void`
Ends the slot capture started by start(). Stores captured output in the named slot.
- **Throws** `\LogicException` — If called without a matching start().
- **Side effect:** Ends output buffering via ob_get_clean()

#### `section(name): void`
Alias for start() — provides a familiar section() API for Laravel/Symfony developers.
- `$name: string` (required) — Slot identifier used by the layout to retrieve content.
- **Side effect:** Starts output buffering via ob_start()

#### `endSection(): void`
Alias for end() — provides a familiar endSection() API for Laravel/Symfony developers.
- **Throws** `\LogicException` — If called without a matching section().
- **Side effect:** Ends output buffering via ob_get_clean()

#### `block(name, default = ''): string`
Returns captured slot content. Returns $default if the slot was never captured. Use hasSection() to check existence.
- `$name: string` (required) — Slot identifier matching a previous start() call.
- `$default: string` (optional) — Default value returned when slot is not captured.
- **Returns** `string` — Captured slot HTML or default value.

#### `hasSection(name): bool`
Checks if a named slot was captured by a previous start()/end() pair.
- `$name: string` (required) — Slot identifier to check.
- **Returns** `bool` — True if the slot exists, false otherwise.

#### `slot(name, default = ''): string`
Backward compatibility alias for block(). Deprecated — use block() instead.
- `$name: string` (required) — Slot identifier matching a previous start() call.
- `$default: string` (optional) — Default value returned when slot is not captured.
- **Returns** `string` — Captured slot HTML or default value.

### Rendering

#### `renderFile(template): string`
Renders a template file, resolves .html/.php extension, extracts data as local variables, and applies the layout system.
- `$template: string` (required) — Template path relative to views root.
- **Returns** `string` — Fully rendered HTML with layout applied.
- **Throws** `ViewException` — If the template file is not found.
- **Side effect:** Uses extract() to create local variables
- **Side effect:** Uses output buffering for rendering

### Methods

#### `__construct(viewsPath, data, defaultLayout, fragmentMode)`

## `Skim\View\View` — view

> Static facade for rendering PHP templates with fragment extraction and profiler integration.

`facade` `view` `fragments` `profiler`

**Source:** `src/View/View.php` · **Layer:** `view` · **Lifecycle:** `static facade, state persists for the current request`

`view` is the static entry point for template rendering. It supports full page rendering, named fragment extraction for htmx/datastar, shared data injection, and configurable layout defaults.

### Core Behavior
- Full page rendering via template context
- Fragment extraction via HTML comment markers
- Shared data injection for cross-cutting concerns
- Profiler integration for render timing

### Warnings
- ⚠ Fragment extraction renders the full template first
- ⚠ then extracts — layout bypass optimization reduces this cost for HTMX requests

### Rendering API

#### `render(template, data = [], fragment = null): string`
Renders a template with shared data merged in. When $fragment is set, bypasses layout and extracts only the named fragment block. Records timing in profiler.
- `$template: string` (required) — Template path relative to views root.
- `$data: array` (optional) — Data merged with shared data for this render.
- `$fragment: ?string` (optional) — Named fragment to extract, or null for full page.
- **Returns** `string` — Rendered HTML or extracted fragment.
- **Throws** `ViewException` — If template file or fragment name is not found.
- **Side effect:** Records render timing in Profiler::view()

#### `renderFragment(template, data, fragment): string`
Shorthand for render() with $fragment set. Renders only the named fragment block.
- `$template: string` (required) — Template path relative to views root.
- `$data: array` (required) — Data for this render.
- `$fragment: string` (required) — Named fragment to extract.
- **Returns** `string` — Extracted fragment HTML.

#### `component(name, props = []): string`
Renders an isolated component with strict props. Delegates to ComponentRenderer::render(). Supports both array props (legacy) and *_props readonly objects (new).
- `$name: string` (required) — Component name (maps to views/components/{$name}.php).
- `$props: array` (optional) — Props array or *_props readonly object.
- **Returns** `string` — Rendered component HTML.
- **Throws** `ViewException` — If props object is not a *_props class or component file is not found.

### Configuration

#### `share(key, value): void`
Injects a key-value pair into every subsequent template render for this request.
- `$key: string` (required) — Shared variable name available in all templates.
- `$value: mixed` (required) — Shared variable value.
- **Side effect:** Mutates static sharedData array

#### `setPath(path): void`
Sets the root directory for template resolution. Called during app boot.
- `$path: string` (required) — Absolute path to the views directory.

#### `setDefaultLayout(name): void`
Sets a default layout applied to every root render that does not call $this->layout(). Pass null to disable.
- `$name: ?string` (required) — Layout template path, or null to disable.

### Testing Hooks

#### `resetRequest(): void`
Clears shared data between requests in worker mode.
- **Side effect:** Empties static $sharedData array

#### `reset(): void`
Clears shared data, views path, and default layout. Use in test tearDown().
- **Side effect:** Clears all static state

### Methods

#### `getShared(key): mixed`
Returns a shared data value, or null if not set. :get_shared

#### `viewsPath(): string`

## `Skim\Websocket\Connection` — connection

> WebSocket connection abstraction with room-based broadcasting and process-scoped room management.

`websocket` `connection` `rooms` `broadcast`

**Source:** `src/Websocket/Connection.php` · **Layer:** `websocket` · **Lifecycle:** `created per WebSocket handshake by the server, destroyed on disconnect`

`connection` wraps an underlying amphp WebSocket connection and adds room-based grouping. Rooms are stored in a static process-scoped array — use Redis pub/sub for multi-process fanout.

### Core Behavior
- Room management via join/leave
- Broadcasting to room members with optional sender exclusion
- JSON auto-encoding for array messages

### Warnings
- ⚠ Rooms are process-local — connections in different PHP workers cannot see each other's rooms
- ⚠ Use Redis pub/sub for multi-process room fanout

### Connection API

#### `id(): string`
Returns the unique connection ID assigned at handshake.
- **Returns** `string` — Unique connection identifier.

#### `send(message): void`
Sends a string or JSON-encoded array to this client. Arrays are auto-encoded. No-ops if the underlying connection lacks sendText().
- `$message: string` (required) — Raw string or array (auto JSON-encoded).
- **Side effect:** Writes to the WebSocket connection

#### `close(code = 1000, reason = ''): void`
Closes this connection with an optional WebSocket close code and reason.
- `$code: int` (optional) — WebSocket close code, default 1000 (normal).
- `$reason: string` (optional) — Human-readable close reason.
- **Side effect:** Terminates the WebSocket connection

### Room Management

#### `join(room): void`
Adds this connection to a named room.
- `$room: string` (required) — Room identifier (e.g., 'chat:general').
- **Side effect:** Mutates the static rooms array

#### `leave(room): void`
Removes this connection from a named room. Cleans up empty room entries.
- `$room: string` (required) — Room identifier to leave.
- **Side effect:** Mutates the static rooms array

#### `roomIds(room): array`
Returns connection IDs of all connections in a room.
- `$room: string` (required) — Room identifier to query.
- **Returns** `array` — Array of connection ID strings.

### Broadcasting

#### `broadcast(room, message, exceptId = null): void`
Sends a message to all connections in a room, optionally excluding one connection by ID.
- `$room: string` (required) — Room to broadcast to.
- `$message: string` (required) — Payload (arrays are JSON-encoded per connection).
- `$exceptId: ?string` (optional) — Connection ID to exclude from broadcast.
- **Side effect:** Sends to multiple WebSocket connections

### Testing Hooks

#### `resetRooms(): void`
Clears the entire room map. Use in test tearDown() to prevent state leakage.
- **Side effect:** Clears the static rooms array

### Methods

#### `__construct(id, rawConn)`

## `Skim\Worker\LeakDetector` — LeakDetector

> Runtime per-request leak detector for worker mode (dev/CI only). :class Snapshots boundary state at begin_request and, after worker_reset has run at end_request, flags hard invariant violations immediately and sustained growth trends over a sliding window. Off unless WORKER_MODE + debug, or explicitly configured. Reports via log/request_trace/profiler — never throws mid-request.

**Source:** `src/Worker/LeakDetector.php`

Runtime per-request leak detector for worker mode (dev/CI only). :class Snapshots boundary state at begin_request and, after worker_reset has run at end_request, flags hard invariant violations immediately and sustained growth trends over a sliding window. Off unless WORKER_MODE + debug, or explicitly configured. Reports via log/request_trace/profiler — never throws mid-request.

### Methods

#### `configure(mode): void`

#### `isActive(): bool`

#### `begin(): void`

#### `check(app): void`
Called at the END of endRequest(), AFTER WorkerReset::apply().

#### `findings(): array`

#### `reset(): void`

## `Skim\Worker\WorkerReset` — WorkerReset

> Per-request reset orchestrator for FrankenPHP worker mode.

`worker` `reset` `lifecycle` `frankenphp`

**Source:** `src/Worker/WorkerReset.php` · **Layer:** `worker` · **Lifecycle:** `static, discovered once at first request, apply() called once per request`

`WorkerReset` discovers every class implementing `resettable` once, then on each request flushes output buffers, rolls back open DB transactions, resets all request-scoped static facades, and clears middleware singleton cache.

### Core Behavior
- Incremental discovery via get_declared_classes() suffix scanning
- OB level restoration preserving test buffers
- Transaction rollback on all pooled connections
- Delegates per-facade reset to resetRequest() on each discovered class

### Warnings
- ⚠ If a facade implements Resettable but is never loaded
- ⚠ it will not be discovered and will not be reset

### Lifecycle

#### `apply(preserveObLevel = 0): void`
Resets all request-scoped state. Safe to call once per request. Flushes output buffers down to $preserveObLevel, rolls back any open DB transactions, resets all discovered resettable facades, and clears profiler/RequestTrace/pipeline caches.
- `$preserveObLevel: int` (optional) — Output buffers at or below this level are left open. Pass ob_get_level() in tests to preserve PHPUnit buffers.
- **Side effect:** Flushes output buffers
- **Side effect:** Rolls back DB transactions
- **Side effect:** Resets all resettable facades
- **Side effect:** Clears profiler and RequestTrace
- **Side effect:** Clears pipeline instance cache

#### `discover(): void`
Incrementally discovers classes implementing resettable. Only inspects classes declared since the previous call, so the cost is amortised to near zero after warmup while still picking up facades autoloaded lazily on later requests.
- **Side effect:** Mutates $classes and $scanned static properties

### Testing

#### `discovered(): array`
Returns the resettable classes discovered so far. Test seam for verifying that expected facades were picked up by discovery.
- **Returns** `list<class-string<resettable>>` — Discovered resettable class names.

