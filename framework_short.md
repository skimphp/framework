# SKIM Framework — AI Context Guide

## Overview
PHP 8.5+ micro-framework. Zero external dependencies except `nikic/fast-route` (routing) and `pestphp/pest` (testing). Docker-ready with MySQL, PostgreSQL, Redis.

## Architecture

### Entry Points
- **HTTP**: `public/index.php` → `app::instance()` → middleware → router → controller → `response::send()`
- **CLI**: `bin/skim <command>` → command map → `command::handle()` → exit code

### Core Classes

| Class | Namespace | Purpose |
|-------|-----------|---------|
| `app` | `skim\core` | DI container, 3 scopes (sys/app/user), middleware registration, request lifecycle |
| `router` | `skim\core` | Wraps FastRoute, F3-style `@param` tokens, groups, named routes |
| `request` | `skim\core` | Abstraction over superglobals, `request::make()` for tests |
| `response` | `skim\core` | Fluent builder: `json()`, `view()`, `redirect()`, `stream()`, `download()` |
| `middleware` | `skim\core` | Interface: `handle(request, response, callable $next): mixed` |
| `pipeline` | `skim\core` | Onion-model middleware chain |

### Request Lifecycle
```
app::run() → request::from_globals() → router::dispatch() → middleware pipeline → controller → response::send()
```
- Returns `null` on 404, `false` on 405
- Controller handler: `[ControllerClass::class, 'method']` or any callable
- Controller params auto-injected: `request`, `response`, route params (by name), DI-resolved classes

### Routing
```php
$app->router->get('/', [home_controller::class, 'index']);
$app->router->group('/api', function(router $r) {
    $r->get('/users/{id}', [user_controller::class, 'show']);
}, middleware: [auth_middleware::class]);
// Route params: {id} → any, @id:int → digits, @slug:str → alphanumeric+dash
```

### DI Container
```php
$app->bind(ServiceInterface::class, fn($app) => new ConcreteService());
$service = $app->make(ServiceInterface::class);  // cached singleton
// Auto-wiring: classes with no binding are resolved via reflection
```

### Scopes
- `sys.*` — framework internals, immutable after boot
- `app.*` — config values from `config/*.php`
- `user.*` (or bare key) — per-request mutable state

## Database (MySQL + PostgreSQL)

### Raw Queries
```php
db::query('SELECT * FROM users WHERE id = :id', [':id' => 5]);   // rows or rowCount
db::row('SELECT ...');   // single row or null
db::val('SELECT COUNT(*) FROM users');  // single scalar or null
db::all('SELECT ...');   // always array (empty if none)
db::transaction(fn() => { /* auto COMMIT/ROLLBACK */ });
db::null()  // sentinel for SET col = NULL (vs null which skips column)
```

### Query Builder Syntax
SQL templates with `%placeholders%`:
- `%where%` → `WHERE col = :col AND ...` (null-safe, removed if empty)
- `%set%` → `SET col1 = :col1, col2 = :col2` (null values skipped)
- `%values%` → `(col1, col2) VALUES (:col1, :col2)`
- `%order%`, `%limit%`, `%offset%`

### ORM (Active Record)
```php
class User extends model {
    protected static string $table = 'users';
    protected static array $casts = ['age' => 'int', 'is_active' => 'bool'];
    protected static array $guarded = ['id', 'created_at'];
}

User::find(1);              // model|null
User::find_or_fail(1);      // model|not_found_exception
User::find_by('email', $e); // model|null
User::where(['status' => 1])->limit(10)->all();  // query_scope
User::create(['name' => 'John']);  // fill + save
$user->save();   // INSERT if no id, UPDATE dirty columns if id set
$user->delete(); // requires primary key
```

## Cache (Redis / File / Array)
```php
cache::get('key', $default);
cache::set('key', $value, $ttl);
cache::remember('key', $ttl, fn() => expensive());  // get or compute+set
cache::has('key'); cache::delete('key');
cache::flush('prefix:');  // prefix-scope clear
cache::tags(['users'])->flush();  // Redis only
// Fallback: Redis failure → silently switches to file driver
```

## Session (Redis / File)
```php
session::start();
session::get('key', $default);
session::set('key', $value);
session::flash('message', 'Saved!');  // auto-deleted on next read
session::regenerate();  // after login/logout
session::flush();       // destroy
```

## Validation
```php
$result = validate::make([
    'email'    => 'required|email',
    'age'      => 'int|min:18|max:120',
    'name'     => 'required|min_len:2|max_len:100',
    'password' => 'required|same:password_confirm',
])->check($data);

if (!$result->ok()) { $errors = $result->errors(); }
$validated = $result->validated();  // only passed fields
```

## Views
PHP templates in `app/views/`. No template engine — raw PHP with `<?= e($var) ?>`.
```php
response::view('users/index', ['users' => $users]);
response::fragment('users/list', ['users' => $users], 'user_row');
view::share('current_user', $user);  // available in all templates
// Fragments: <!-- @fragment name --> ... <!-- @end -->
```

## Events
```php
event::on(UserCreated::class, fn($e) => send_welcome_email($e->user));
event::once('app.booted', fn() => warm_cache());
event::emit(new UserCreated($user));       // synchronous
event::emit_async('user.created', $data);  // via queue worker
```

## Queue (Redis)
```php
queue::push(new SendEmailJob($to, $body));
queue::push_many($jobs);
queue::size(); queue::flush();
// CLI: php skim queue:work [queue] [--sleep=5]
```

## Middleware
```php
class auth_middleware implements middleware {
    public function handle(request $req, response $res, callable $next): mixed {
        if (!session::has('user_id')) return $res->status(401)->json(['error' => 'Unauthorized']);
        return $next($req, $res);
    }
}
$app->use(cors::class);  // global
$route->middleware([auth_middleware::class]);  // per-route
```

Built-in: `cors` (global by default), `toolbar_middleware` (debug toolbar, HTML only), `rate_limit` (Redis sliding window).

## CLI Commands
```php
class my_command extends command {
    public function handle(): int {
        $name = $this->arg(0, 'World');
        $verbose = $this->flag('verbose', false);
        $this->info("Hello {$name}!");
        return 0;
    }
}
// Register: config/app.php 'commands' => ['greet' => my_command::class]
// Run: php skim greet John --verbose
```

Built-in: `serve`, `migrate`, `migrate:down`, `migrate:fresh`, `migrate:status`, `queue:work`, `cache:clear`, `ide:generate`

## Config & Env
- `.env` → `env::load()`, accessed via `env('KEY', $default)` or `env('KEY')`
- `config/*.php` → PHP arrays, accessed via `config('db.default.host')`
- Config can call `env()` for 12-factor compliance

**Env priority (intended):** OS environment variables (Docker / CI / K8s) > `.env` file.
The `.env` file should be the fallback for plain local dev; production injects vars via OS.

**Known limitation:** `env::load()` currently overwrites OS vars with `.env` values — the
priority is inverted. Until fixed, keep `.env` and `docker-compose.yml` `environment:` in
sync. Planned fix: skip keys already present in `getenv()` / `$_ENV` during `env::load()`.
See `cross-check.md §7.1`.

## Testing (Pest)
```php
// tests/Pest.php — global beforeEach resets all singletons
uses()->beforeEach(function() {
    config::reset(); env::reset(); cache::reset(); /* ... */
})->in(__DIR__);

// Request testing
$req = request::make('GET', '/users/5', query: ['page' => 1]);
$res = new response();
// Controller invoked via app::test_instance()
```

## Helpers
`env()`, `config()`, `route('name', ['id' => 5])`, `storage_path('logs/app.log')`, `base_path()`, `e($value)`, `t('auth.login')`, `asset('app.js')`

## Naming Conventions
- **Classes**: `snake_case` (not PascalCase) — `home_controller`, `cors`, `query_builder`
- **Files**: match class name — `home_controller.php`, `query_builder.php`
- **Methods**: `snake_case` — `find_or_fail()`, `to_array()`, `set_route_params()`
- **Namespaces**: `skim\component\subcomponent` for framework, `app\*` for application code

## Docker Stack
- **app**: PHP 8.5 Alpine, ports 8080/5173, volumes mount source + vendor/node_modules
- **mysql**: 8.0, port 3306, db `skim_dev`
- **pgsql**: 16-alpine, port 5432, db `skim_analytics`
- **redis**: 7-alpine, no host port, maxmemory 256mb
- Commands: `docker compose up -d --build`, `docker compose exec app ./vendor/bin/pest`

## Key Design Decisions
- No Twig/Blade — raw PHP templates (faster with opcache, real stack traces)
- Static facades (`db::`, `cache::`, `session::`) — testable via `set_driver()` / `reset()`
- Lazy connections — DB/Redis not opened until first use
- Fail-open: cache/redis failures fall back gracefully, don't crash the app
- `@ai-contract` docblocks — machine-readable behavior contracts for AI agents
- `strict_types=1` on every file
- No YAML/INI configs — PHP arrays only (IDE autocomplete, no parser overhead)
