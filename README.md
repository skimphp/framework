# SKIM Framework

A modern, fast PHP 8.5+ micro-framework. Zero magic, PSR-style casing (PascalCase classes, camelCase methods), Docker-ready.

---

## Requirements

- PHP 8.5+
- Composer 2
- Docker + Docker Compose (recommended)

---

## Quick start

```bash
# 1. Clone / create project
git clone <repo> myapp && cd myapp

# 2. Copy env and edit if needed
cp .env.example .env

# 3. Start all services (PHP + MySQL + PostgreSQL + Redis)
docker compose up -d --build

# 4. Install dependencies (first time only — vendor is a named volume)
docker compose exec app composer install

# 5. Run migrations
docker compose exec app php bin/skim migrate

# 6. Open in browser
open http://localhost:8080

## Development & testing 
docker compose exec app composer test
```

---

## Project structure

```
myapp/
├── app/
│   ├── controllers/          ← your controllers (namespace app\controllers)
│   ├── models/               ← your models     (namespace app\models)
│   └── views/                ← PHP templates
├── config/
│   ├── app.php               ← name, debug, env, timezone, log
│   ├── db.php                ← database connections
│   └── cache.php             ← cache driver, Redis, file path
├── migrations/               ← SQL migration files
├── public/
│   └── index.php             ← single entry point (point web server here)
├── src/                      ← framework source — don't edit
├── tests/                    ← Pest tests
├── generated/
│   └── .ide-helper.php       ← auto-generated IDE hints (php skim ide:generate)
├── .env                      ← local secrets, never commit
├── .env.example              ← template for new developers
└── routes.php                ← HTTP + CLI route registrations
```

---

## Configuration

### Two sources of config, one priority order

| Source | Used when |
|--------|-----------|
| `.env` file | Local development — easy to edit |
| OS environment variables (Docker / CI / production) | Deployment — no files on server |

**Important:** OS environment variables take priority over `.env`.  
This means values set in `docker-compose.yml` `environment:` block or passed by
Kubernetes/CI will override whatever is in `.env`. The `.env` file is the fallback
for when no OS variable is set — i.e., plain local dev without Docker.

### Compiled Cache (Production)

For maximum performance in production, pre-compile config and env into OPcache-friendly PHP arrays:

```bash
php skim cache:build
```

This generates `storage/cache/{env,config,extensions}.php` — pure `return [...]` files
that bypass all parsing overhead on every request. In development (`APP_DEBUG=true`),
the framework automatically falls back to raw parsing when source files change.

### Config files

All config is plain PHP arrays — no YAML, no INI. IDE autocomplete works out of the box.

```php
// config/app.php
return [
    'name'  => env('APP_NAME', 'SKIM App'),
    'debug' => env('APP_DEBUG', false),
];

// Read anywhere:
config('app.name');
config('db.default.host');
env('DB_HOST', 'localhost');
```

### APP_KEY

32-byte secret for signing cookies and encrypting session data. Generate with:

```bash
php skim install        # fills it automatically
# or manually:
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

---

## Routing

```php
// routes.php
$app->router->get('/', [HomeController::class, 'index']);
$app->router->post('/users', [UserController::class, 'store']);

// Route params
$app->router->get('/users/@id:int', [UserController::class, 'show']);
// @id       → any string
// @id:int   → digits only
// @slug:str → letters, digits, dashes

// Named routes
$app->router->get('/users/@id', [UserController::class, 'show'])->name('user.show');
route('user.show', ['id' => 5]); // → /users/5

// Groups with middleware
$app->router->group('/api', function(Router $r) {
    $r->get('/users', [api\UserController::class, 'index']);
}, middleware: [AuthMiddleware::class]);
```

---

## Database

### Raw queries with query_gen

```php
// %placeholders% are removed silently if their keys are absent — no string concatenation needed
Db::all('SELECT * FROM users %where% %limit%', [
    'where'   => ['status = :status'],
    ':status' => 'active',
    'limit'   => 20,
]);

Db::row('SELECT * FROM users WHERE id = :id', [':id' => 5]);
Db::val('SELECT COUNT(*) FROM users');

Db::transaction(function() {
    Db::query('UPDATE accounts %set% WHERE id = :id', ['set' => ['balance' => 100], ':id' => 1]);
    Db::query('UPDATE accounts %set% WHERE id = :id', ['set' => ['balance' => 200], ':id' => 2]);
});
```

### Active Record

```php
class User extends \Skim\Db\Model {
    protected static string $table   = 'users';
    protected static array  $guarded = ['id', 'created_at'];
    protected static array  $casts   = ['age' => 'int', 'is_active' => 'bool'];
}

User::find(1);                            // Model|null
User::findOrFail(1);                    // Model|NotFoundException
User::where(['status' => 'active'])->limit(10)->all();
User::create(['name' => 'John', 'email' => 'j@j.com']);

$user->name = 'Jane';
$user->save();    // INSERT if no id, UPDATE only changed columns if id set

$user->delete();
```

### Multiple DB connections

```php
// config/db.php — add as many named connections as needed
'alt' => [
    'driver'   => 'pgsql',
    'host'     => env('ALT_DB_HOST', 'pgsql'),
    // ...
];

// Use the connection name on any query
Db::all('SELECT * FROM reports', [], connection: 'alt');
```

The `alt` connection ships as an example of a secondary database
kept separate from the primary transactional DB — so slow queries cannot
block the main application.

---

## Cache

```php
Cache::set('user:1', $user, 3600);
Cache::get('user:1');
Cache::remember('user:1', 3600, fn() => User::find(1));   // get or compute+cache
Cache::delete('user:1');
Cache::flush('user:');         // remove all keys starting with 'user:'
Cache::tags(['users'])->flush();  // Redis only — tag-scoped invalidation
```

Drivers: `redis` (primary), `file` (fallback), `array` (tests).  
If Redis is unreachable, the cache silently falls back to the file driver.

---

## Views

Pure PHP templates — no Twig, no Blade.

```php
// Controller
return $res->view('users/show', ['user' => $user]);
return $res->fragment('users/show', ['user' => $user], 'user-card');  // partial only
return $res->smartView('users/show', ['user' => $user], $req);        // auto full/partial

// Template: app/views/users/show.php
<h1><?= e($user->name) ?></h1>   <!-- e() = htmlspecialchars, always use it -->

<!-- @fragment user-card -->
<div id="user-card">...</div>
<!-- @end -->
```

---

## Middleware

```php
class AuthMiddleware implements Middleware {
    public function handle(Request $req, Response $res, callable $next): mixed {
        if (!Session::has('user_id')) {
            return $res->status(401)->json(['error' => 'Unauthorized']);
        }
        return $next($req, $res);
    }
}

$app->use(Cors::class);                           // global — every request
$route->middleware([AuthMiddleware::class]);      // per-route
```

Built-in: `cors`, `RateLimit`, `ToolbarMiddleware` (injects debug toolbar in HTML responses when `APP_DEBUG=true`).

---

## CLI commands

```bash
php skim install            # interactive .env wizard
php skim serve              # PHP dev server on :8080
php skim migrate            # run pending migrations
php skim migrate:down       # rollback last batch
php skim migrate:fresh      # drop all + re-run (dev only)
php skim migrate:status     # show applied/pending list
php skim queue:work         # start queue worker
php skim cache:build        # pre-compile env + config for production
php skim cache:clear        # flush cache by prefix
php skim ide:generate       # generate .ide-helper.php from DB schema
php skim list --agent       # compact command list for agents/scripts
```

### Agent-friendly CLI output

Use `--agent` when an LLM agent, shell script, or parser needs concise output:

```bash
php bin/skim list --agent
php bin/skim migrate:status --agent
```

`--agent` implies `--quiet` and `--no-ansi`, disables the interactive help menu,
removes the banner and timing footer, and prints `help` / `list` as tab-separated
rows: `command<TAB>usage<TAB>description`. Unknown commands and uncaught command
errors are reported as one-line tab-separated messages on STDERR.

### Docs commands (dev only — disabled in `production`)

```bash
php skim docs               # full build: extract → llm.md → MDX site
php skim docs --watch       # rebuild on file change
php skim docs --source=DIR --output=DIR  # scan another PHP source dir and write llm.json, llm.md, *.mdx into DIR
php skim docs:extract       # scan @ai.* annotations → llm.json
php skim docs:llm           # llm.json → llm.md (paste to any LLM)
php skim docs:site          # llm.json → MDX files for Starlight docs site
php skim docs:validate      # report unannotated public methods; exit 1 if below threshold
php skim mcp:serve          # start stdio MCP server (requires llm.json)
```

`llm.md` is committed to the repo root — paste it into any LLM when no tooling is available.

Use `--source` and `--output` for isolated documentation builds without overwriting the root docs files:

```bash
php bin/skim docs --source=.agents/skills/better-commenting/test/5 --output=.agents/skills/better-commenting/test/5/res_mdx
```

With `--output`, the generator writes `llm.json`, `llm.md`, and all generated `*.mdx` files directly into that directory.
If multiple extracted classes share the same class name, MDX filenames include the source filename to avoid overwrites.

### Write your own command

```php
class GreetCommand extends Command {
    public function handle(): int {
        $name = $this->arg(0, 'World');
        $this->info("Hello {$name}!");
        return 0;
    }
}

// Register in config/app.php:
'commands' => ['greet' => GreetCommand::class]

// Run:
// php skim greet John
```

---

## Testing

```bash
docker compose exec app ./vendor/bin/pest
docker compose exec app ./vendor/bin/pest tests/core/router_test.php
```

Tests use SQLite `:memory:` for DB and `array` driver for cache — no real services needed.

```php
// Request testing without HTTP
$req = Request::make('POST', '/users', headers: ['Content-Type' => 'application/json'], raw_body: '{"name":"John"}');
$res = new Response();

// Fake HTTP client
$http = Client::fake(['GET https://api.example.com/users' => ['status' => 200, 'body' => []]]);
$resp = $http->get('https://api.example.com/users');
$http->assertSent('GET', 'users');
```

---

## Docker services

| Service | Image | Port | Purpose |
|---------|-------|------|---------|
| `app` | PHP 8.5 Alpine | 8080, 5173 | Application + Vite dev server |
| `mysql` | MySQL 8.0 | 3306 | Primary database (`skim_dev`) |
| `pgsql` | PostgreSQL 16 | 5432 | Alternative database (`skim_alt`) |
| `redis` | Redis 7 | — | Cache + Queue + Sessions |

```bash
docker compose up -d          # start all
docker compose exec app bash  # shell inside container
docker compose exec app php skim migrate
make shell                    # alias for exec app bash
make test                     # alias for pest
```

---

## MCP server

SKIM ships a stdio MCP server so tool-capable LLMs (Claude Code, Cursor) can query the framework's `@ai.*` annotations directly.

### Wire into Claude Code

```json
// .claude/mcp_settings.json
{
  "mcpServers": {
    "skim": {
      "command": "php",
      "args": ["skim", "mcp:serve"],
      "cwd": "/path/to/project"
    }
  }
}
```

### Wire into Cursor

```json
// .cursor/mcp.json
{
  "mcpServers": {
    "skim": {
      "command": "php",
      "args": ["skim", "mcp:serve"],
      "cwd": "/path/to/project"
    }
  }
}
```

Or install the npm wrapper for registry-based clients:

```bash
npm install -g @skim/mcp
# then use "command": "skim-mcp" with "env": { "SKIM_ROOT": "/path/to/project" }
```

### Available MCP tools

| Tool | Description |
|------|-------------|
| `skim_class(name)` | Summary, lifecycle, owner, file path |
| `skim_method(class, method)` | Signature, contracts, invariants, non_goals, side_effects, perf, throws |
| `skim_search(query)` | Fuzzy search across class names, methods, and contract text |
| `skim_lifecycle()` | Boot order and request lifecycle |
| `skim_non_goals()` | All `@ai.non_goal` entries grouped by class |

---

## Pre-commit hook (recommended)

Catch annotation regressions before they reach CI:

```bash
# .git/hooks/pre-push
#!/bin/sh
php skim docs:validate
```

```bash
chmod +x .git/hooks/pre-push
```

---

## Key design decisions

- **Conventional PHP casing** — PascalCase classes, camelCase methods, UPPER_SNAKE constants. `HomeController`, not `home_controller`.
- **No template engines** — raw PHP with opcache is ~3x faster than Twig/Blade; real stack traces.
- **Static facades** (`Db::`, `Cache::`, `Log::`) — each has `reset()` and `setDriver()` for test isolation.
- **Lazy connections** — DB and Redis are not opened until the first actual query.
- **Fail-open cache** — Redis failure falls back to file driver silently; app keeps running.
- **PHP arrays for config** — no YAML/INI parser, IDE autocomplete works natively.
- **`strict_types=1` everywhere** — no implicit type coercion.
