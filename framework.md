# SKIM Framework — Master Build Prompt

## Context

You are building **SKIM** — a modern, clean, fast PHP 8.5+ micro-framework.
SKIM is a spiritual successor to Fat-Free Framework 3.8+ with full **backward-compatible routing and template API**, but completely rewritten internals.

**Core philosophy:**
- Snake_case everywhere — classes, methods, files, namespaces
- Braces on same line: `if () {`
- Full PHP 8.5+ type declarations on every function signature
- Readable code — every file must be understandable without docs
- Zero magic where possible — explicit over implicit
- Composable — install only what you need
- PSR-4 autoloading via Composer
- PSR-11 container (league/container)
- PSR-6/PSR-16 cache interfaces
- Property hooks (PHP 8.4+) for clean model behavior
- Asymmetric visibility (PHP 8.4+) for protected internals

---

## Code comments philosophy

**Goal:** Short, pragmatic developer comments that help someone opening the file **2+ years later** quickly understand the architecture.

**Comment when explaining:**
- **Architectural decisions** — why this approach vs alternatives
- **Lifecycle behavior** — when/how things initialize, cleanup, or invalidate
- **Integration contracts** — what other modules expect from this code
- **Synchronization assumptions** — race conditions, cache coherence, state dependencies
- **Edge cases** — non-obvious guards, fallbacks, or boundary conditions
- **Performance tradeoffs** — why we cache here, why we avoid N+1 there

**Do NOT comment:**
- Obvious code behavior (`// increment counter` above `$i++`)
- What the code does if the name already says it
- Restating the function signature in prose

**Style:**
```php
// Schema cache lives in Redis/File — fetched once on first model access,
// invalidated only by migrations. This prevents DESCRIBE on every request.
protected static function fetch_schema(): array {
    return cache::remember("schema:{$table}", ttl: 3600, fn() => /* ... */);
}

// Property hook auto-normalizes email on write.
// Why: prevents case-sensitive duplicate checks in unique constraints.
public string $email {
    set(string $val) => strtolower(trim($val));
}

// Asymmetric visibility: $id writable only inside model::hydrate().
// External code cannot accidentally overwrite primary keys.
public private(set) int $id;

// Dirty tracking via property hook — knows which columns changed
// without comparing old/new arrays. Enables surgical UPDATEs.
private array $dirty = [];
public string $status {
    set(string $val) {
        if ($val !== $this->status) {
            $this->dirty[] = 'status';
        }
        $this->status = $val;
    }
}
```

**Framework-specific context to include:**
- Why we chose this over F3's approach
- How this integrates with query_gen / cache / events
- What breaks if you bypass this abstraction
- When to use raw SQL vs ORM vs query_gen

---

## Naming convention (strictly enforced)

```php
// Classes — snake_case
class query_builder {}
class user extends model {}

// Methods — snake_case
public function find_by_email(string $email): ?user {}

// Files match class names
// app/models/user.php → class user
// src/db/query_builder.php → class query_builder

// Namespaces — snake_case
namespace skim\db;
namespace skim\helpers;
namespace app\models;

// Braces — same line always
if ($condition) {
    // ...
}

foreach ($items as $item) {
    // ...
}

// Types — always declared
public function get(string $key, mixed $default = null): mixed {}
public function find(int $id): ?static {}
public function all(): array {}

// Property hooks (PHP 8.4+) — getters/setters in property declaration
public string $email {
    set(string $val) => strtolower(trim($val));
}

// Asymmetric visibility (PHP 8.4+) — read-only externally, writable internally
public private(set) int $id;
public private(set) string $created_at;
```

---

## Project structure

```
myapp/
├── app/
│   ├── controllers/
│   ├── models/
│   └── views/
├── config/
│   ├── app.php        ← main config, PHP arrays only (no YAML, no INI)
│   ├── db.php
│   ├── cache.php
│   └── assets.php
├── generated/
│   └── .ide-helper.php   ← auto-generated, never edit manually
├── migrations/
├── public/
│   └── index.php
├── src/                  ← framework source
│   ├── core/
│   ├── db/
│   ├── view/
│   ├── cache/
│   ├── http/
│   ├── helpers/
│   ├── cli/
│   ├── realtime/
│   ├── queue/
│   ├── events/
│   └── validation/
├── composer.json
└── vite.config.js        ← if assets module selected
```

---

## Module 1 — Core (skim/core)

### App container and lifecycle

```php
// public/index.php
// Single entry point — all requests funnel here, Apache/Nginx rewrites to index.php
$app = skim\core\app::instance();
$app->run();

// Container uses three scoped namespaces to prevent accidental overwrites.
// SYS scope: framework internals registered at boot — immutable after that
// APP scope: config loaded from config/*.php — frozen after boot, safe to cache
// USER scope: per-request mutable state, cleared between requests
$app->set('user.name', 'John');           // USER scope — mutable, ok
$app->set('sys.router', $router);         // SYS scope — throws if already set
$app->get('app.debug', default: false);   // APP scope — read-only after boot
```

### Config — PHP arrays only

```php
// config/app.php
// PHP arrays only — no YAML/INI. Reason: IDE autocomplete works, no extra parser,
// configs are just PHP that can reference env() for 12-factor app compliance.
return [
    'name'    => env('APP_NAME', 'SKIM App'),
    'debug'   => env('APP_DEBUG', false),
    'env'     => env('APP_ENV', 'production'),
    'key'     => env('APP_KEY', ''),   // used for signed cookies, CSRF tokens
    'timezone'=> 'UTC',
];

// Dot-notation access across all config files:
config('app.name');
config('db.connections.default.host');  // reads config/db.php → connections → default

// Own env() implementation — avoids vlucas/phpdotenv dependency.
// Reads .env on first call, then caches in memory for the request.
env('DB_HOST', default: 'localhost');
```

### Router

Based on nikic/fast-route — compiles all routes into one regex, orders of magnitude faster than F3.
Backward-compatible with F3 `@param` token syntax where possible.

```php
// Routes registered in public/index.php or a dedicated routes.php file.
// All routes compile into a single regex on first run — no per-route matching loop.
$app->get('/', [home_controller::class, 'index']);
$app->post('/users', [user_controller::class, 'store']);
$app->put('/users/@id', [user_controller::class, 'update']);
$app->delete('/users/@id', [user_controller::class, 'destroy']);

// Groups apply middleware to all enclosed routes before individual route middleware.
// Middleware order: global → group → route (applied as a pipeline, not nested calls).
$app->group('/api', function(router $r) {
    $r->get('/users', [api\user_controller::class, 'index']);
    $r->post('/users', [api\user_controller::class, 'store']);
}, middleware: [auth_middleware::class, json_middleware::class]);

// Named routes allow reverse-generation — safe refactoring without string hunting.
$app->get('/users/@id', [user_controller::class, 'show'])->name('user.show');
route('user.show', ['id' => 5]); // → /users/5

// Type tokens prevent invalid segments from reaching controllers.
// @id:int guards against SQL injection at the routing layer, before controller runs.
// @id       → any string
// @id:int   → digits only
// @slug:str → letters, digits, dashes

// CLI routes share the same controller/DI pattern as HTTP routes.
$app->command('migrate', [migrate_command::class, 'run']);
$app->command('queue:work', [queue_command::class, 'work']);
```

### Request

```php
// request is injected by the container — never instantiate it manually.
// The same request instance is shared across the middleware pipeline and controller.
public function show(request $req, int $id): mixed {

    $req->get('page', default: 1);      // $_GET — never access $_GET directly
    $req->post('email');                 // $_POST — never access $_POST directly
    $req->input('name');                 // GET or POST — order: GET first, then POST
    $req->json();                        // parsed JSON body → array (empty if not JSON)
    $req->file('avatar');                // uploaded file object, null if not uploaded
    $req->header('Authorization');
    $req->ip();                          // respects X-Forwarded-For when behind proxy
    $req->method();                      // always uppercase: GET POST PUT DELETE
    $req->path();                        // /users/5 (no query string)
    $req->url();                         // https://example.com/users/5

    // Detection helpers drive the smart_view() and fragment() response strategy.
    // Controllers can return different payloads without checking headers manually.
    $req->is_htmx();                     // HX-Request header present
    $req->is_datastar();                 // datastar-request header
    $req->is_json();                     // Accept: application/json
    $req->is_ajax();
    $req->is_cli();                      // running via php skim command
}
```

### Response

```php
// Controllers must return a response object — never echo or die directly.
// The framework sends headers + body after all middleware finishes.
return $res->json(['ok' => true]);
return $res->json(['error' => 'not found'], 404);
return $res->view('users/show', ['user' => $user]);
return $res->redirect('/login');
return $res->redirect()->back();           // reads Referer header, falls back to '/'
return $res->status(422)->json($errors);   // status() is fluent, must call json/view after
return $res->stream(function(sse $sse) { /* ... */ });  // disables output buffering
return $res->download('/path/to/file.pdf', 'report.pdf');

// fragment() renders only the named @fragment block from the template.
// Use this for htmx/datastar partial updates — saves full page render cost.
// smart_view() auto-selects full vs fragment based on request headers.
return $res->fragment('users/show', ['user' => $user], 'user-card');
```

### Middleware pipeline

```php
// Global middleware runs on every request, registered before $app->run().
// Order matters: cors must run before auth (preflight OPTIONS must pass).
$app->use(skim\middleware\cors::class);
$app->use(skim\middleware\rate_limit::class, limit: 100, window: 60);

// Middleware pipeline is a chain of callables — each decides to call $next or short-circuit.
// Short-circuiting (returning without $next) stops the chain — controller never runs.
class auth_middleware implements middleware {
    public function handle(request $req, response $res, callable $next): mixed {
        if (!$req->header('Authorization')) {
            // Return without calling $next — controller is never reached
            return $res->json(['error' => 'unauthorized'], 401);
        }
        return $next($req, $res);  // pass to next middleware or controller
    }
}
```

---

## Module 2 — Database (skim/db)

### PDO wrapper + query_gen

Supports: MySQL, PostgreSQL. Optional: MongoDB (skim/db-mongo), ClickHouse (skim/db-clickhouse).

Multiple connections supported. Schema cache on first connect (DESCRIBE / information_schema) — stored in configured cache driver, never re-fetched unless explicitly invalidated.

```php
// config/db.php
return [
    'default' => [
        'driver'   => 'mysql',    // mysql | pgsql
        'host'     => env('DB_HOST', 'localhost'),
        'port'     => env('DB_PORT', 3306),
        'database' => env('DB_NAME', 'myapp'),
        'user'     => env('DB_USER', 'root'),
        'password' => env('DB_PASS', ''),
        'charset'  => 'utf8mb4',
    ],
    'analytics' => [
        'driver'   => 'pgsql',
        'host'     => env('ANALYTICS_DB_HOST', 'localhost'),
        'database' => env('ANALYTICS_DB_NAME', 'analytics'),
        'user'     => env('ANALYTICS_DB_USER', 'analyst'),
        'password' => env('ANALYTICS_DB_PASS', ''),
    ],
];
```

### query_gen

SQL template builder. Lives in `src/db/query_builder.php` — internal class, not part of the public API.
`db.php` uses it privately; external code only calls `db::query/val/row/all`.

File split in `src/db/`:
- `db.php` — connection pool + execution + profiler hooks (~170 lines)
- `query_builder.php` — `build/build_where/build_set/build_values/interpolate` (internal, testable without PDO)
- `null_marker.php` — sentinel for `db::null()` (explicit SQL NULL in `%set%`)
- `model.php` — active record
- `merry_model.php` — ORM with relations

```php
use skim\db\db;

// %placeholders% are query_gen tokens — removed silently if their keys are absent or null.
// This lets you build dynamic queries without string concatenation or conditionals.
$users = db::query('SELECT * FROM users %where% %order_by% %limit% %offset%', [
    'where'    => ['status = :status', 'age >= :min_age'],
    ':status'  => 'active',
    ':min_age' => 18,
    'order_by' => 'created_at DESC',
    'limit'    => 20,
    'offset'   => 0,
]);

// Nested or/and builds grouped clauses: (category = ? AND price <= ?) OR (name LIKE ? OR description LIKE ?)
$products = db::query('SELECT * FROM products %where%', [
    'where' => [
        'and' => [['category = :cat'], ['price <= :max']],
        'or'  => [['name LIKE :search'], ['description LIKE :search']],
    ],
    ':cat'    => 'electronics',
    ':max'    => 500,
    ':search' => '%tablet%',
]);

// null values in %set% are silently skipped — prevents NULL overwrites on partial updates.
// To explicitly set NULL, use: 'avatar' => db::null()
db::query('UPDATE users %set% WHERE id = :id', [
    'set' => ['name' => 'John', 'email' => 'j@j.com', 'avatar' => null],
    ':id' => 5,
]);

db::query('INSERT INTO users %values%', [
    'values' => ['name' => 'John', 'email' => 'j@j.com'],
]);

// debug:true returns interpolated SQL string and does NOT execute the query.
// Use this in development or tests to inspect what gets sent to the database.
$sql = db::query('SELECT * FROM users %where%', [
    'where'   => ['status = :status'],
    ':status' => 'active',
], debug: true);
// → "SELECT * FROM users WHERE status = 'active'"

// connection: parameter routes to a specific config key in config/db.php
db::query('SELECT * FROM reports', [], connection: 'analytics');

// transaction() wraps multiple queries in BEGIN/COMMIT, auto-rollback on any exception.
// Always use for multi-table writes — partial updates leave data in inconsistent state.
db::transaction(function() {
    db::query('UPDATE accounts %set% WHERE id = :id', ['set' => ['balance' => 100], ':id' => 1]);
    db::query('UPDATE accounts %set% WHERE id = :id', ['set' => ['balance' => 200], ':id' => 2]);
});

// Convenience shorthands — thin wrappers around query() for common result shapes:
$count = db::val('SELECT COUNT(*) FROM users %where%', ['where' => ['status = :s'], ':s' => 'active']);
$user  = db::row('SELECT * FROM users WHERE id = :id', [':id' => 5]);
$users = db::all('SELECT * FROM users %where%', ['where' => ['status = :s'], ':s' => 'active']);
```

### Active Record (option 1 — default, no relations)

Schema is fetched once via DESCRIBE (MySQL) or information_schema (PostgreSQL), stored in cache. `@property` hints generated into `generated/.ide-helper.php` via `php skim ide:generate`.

**PHP 8.4+ features used:**
- Property hooks for auto-normalization, computed fields, dirty tracking
- Asymmetric visibility for protecting system fields (id, created_at, etc.)

```php
// Minimal model definition:
class user extends skim\db\model {
    protected static string $table = 'users';

    // Asymmetric visibility — id/timestamps writable only inside hydrate()
    public private(set) int $id;
    public private(set) string $created_at;
    public private(set) string $updated_at;
    
    // Property hook — auto-normalize email on assignment
    // Why: prevents case-sensitive duplicates in unique constraints
    public string $email {
        set(string $val) => strtolower(trim($val));
    }
    
    // Property hook — auto-hash password on assignment
    // Why: never store plaintext passwords, hash transparently
    public string $password {
        set(string $val) => password_hash($val, PASSWORD_BCRYPT);
    }
    
    // Computed property — not stored in DB, calculated on access
    // Why: avoid storing redundant data, always fresh
    public string $full_name {
        get => trim($this->first_name . ' ' . $this->last_name);
    }
    
    // Dirty tracking via property hook — surgical UPDATEs
    // Why: only update changed columns, not all 20 fields like F3
    private array $dirty = [];
    public string $status {
        set(string $val) {
            if ($val !== $this->status) {
                $this->dirty[] = 'status';
            }
            $this->status = $val;
        }
    }

    // Optional — override connection:
    protected static string $connection = 'default';

    // Optional — columns excluded from mass assignment:
    protected static array $guarded = ['id', 'created_at', 'updated_at'];
}

// Usage:
$user = user::find(1);              // by primary key, null if not found
$user = user::find_or_fail(1);      // throws not_found_exception
$user = user::find_by('email', 'j@j.com');

$users = user::where(['status' => 'active'])
    ->where('age >= :age', [':age' => 18])
    ->order('created_at DESC')
    ->limit(20)
    ->offset(0)
    ->all();

$user = user::create(['name' => 'John', 'email' => 'j@j.com']);

$user->name = 'Jane';
$user->save();

user::update_where(['status' => 'inactive'], ['last_login < :date'], [':date' => '2023-01-01']);

$user->delete();
user::delete_where(['status' => 'banned']);

// Counts:
$total = user::count();
$active = user::count(['status' => 'active']);

// Raw query using query_gen syntax, scoped to model:
$users = user::raw('SELECT * FROM users %where% %limit%', [
    'where'   => ['status = :s'],
    ':s'      => 'active',
    'limit'   => 10,
]);

// Pagination:
$page = user::where(['status' => 'active'])->paginate(page: 2, per_page: 20);
// $page->items, $page->total, $page->pages, $page->current
```

### Merry ORM (option 2 — with relations, generated from migrations)

Relations are declared in the model. Code generation (`php skim ide:generate`) reads the schema and produces IDE hints. No annotation magic — explicit arrays.
Extends model, so all PHP 8.4+ features (property hooks, asymmetric visibility, dirty tracking) are inherited — add extra relation declarations on top.

```php
class user extends skim\db\merry_model {
    protected static string $table = 'users';

    // Asymmetric visibility inherited from merry_model base
    // public private(set) int $id; ← already declared in base class

    // Add your own property hooks on top of the base:
    public string $email {
        set(string $val) => strtolower(trim($val));
    }

    protected static array $has_many = [
        'groups'   => [group::class, foreign_key: 'user_id'],
        'posts'    => [post::class, foreign_key: 'author_id'],
        'comments' => [comment::class, foreign_key: 'user_id'],
    ];

    protected static array $belongs_to = [
        'role'    => [role::class, foreign_key: 'role_id'],
        'company' => [company::class, foreign_key: 'company_id'],
    ];

    protected static array $has_one = [
        'profile' => [user_profile::class, foreign_key: 'user_id'],
    ];

    protected static array $many_to_many = [
        'tags' => [tag::class, pivot: 'user_tags', fk: 'user_id', related_fk: 'tag_id'],
    ];
}

// Eager loading — avoids N+1:
$users = user::with('groups', 'role')->where(['status' => 'active'])->all();

foreach ($users as $u) {
    echo $u->role->name;
    foreach ($u->groups as $g) {
        echo $g->name;
    }
}

// Lazy loading:
$user = user::find(1);
$user->load('groups');
$user->groups; // already loaded

// Nested eager loading:
$users = user::with('posts.comments', 'role')->all();

// Creating via relation:
$group = $user->groups()->create(['name' => 'Admins']);

// Attaching many-to-many:
$user->tags()->attach([1, 2, 3]);
$user->tags()->detach([2]);
$user->tags()->sync([1, 3]);      // remove 2, keep 1 and 3
```

### Migrations — SQL-first, no ORM annotations

```php
// migrations/2024_01_01_create_users_table.php
// SQL-first migrations: raw SQL gives you full control over indexes, charset, collation.
// No fluent builder abstraction — what you write is exactly what runs on the database.
return new class extends skim\db\migration {
    public function up(): string {
        return "
            CREATE TABLE users (
                id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name       VARCHAR(255) NOT NULL,
                email      VARCHAR(255) NOT NULL,
                role_id    INT UNSIGNED DEFAULT NULL,
                status     ENUM('active','inactive','banned') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_email (email),
                INDEX idx_status (status)   -- model dirty tracking uses this for fast lookups
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
    }

    // down() must exactly reverse up() — used by migrate:down and migrate:fresh.
    // Always write down() even if you never plan to rollback — fresh dev environments need it.
    public function down(): string {
        return "DROP TABLE IF EXISTS users";
    }
};

// php skim migrate              → run all pending
// php skim migrate:down         → rollback last batch
// php skim migrate:down --steps=3
// php skim migrate:fresh        → drop all + re-run (dev only, destructive)
// php skim migrate:status       → show applied/pending
// php skim migrate:make name    → generate empty migration file
```

After migration runs: schema cache is invalidated automatically, and `php skim ide:generate` runs in dev mode to refresh `@property` hints in `generated/.ide-helper.php`.

---

## Module 3 — View (skim/view)

Pure PHP templates. No Twig, no Blade. Fast, debuggable, native IDE support.

### Templates

```php
// app/views/users/show.php
// @var hints are the only "magic" in templates — needed for IDE autocomplete.
// No Twig/Blade: reason is debuggable stack traces, no compiled cache to clear,
// and PHP templates are ~3x faster with opcache than a template engine.
<?php /** @var user $user */ ?>
<!DOCTYPE html>
<html>
<head>
    <?= asset('app.css') ?>
</head>
<body>
    <?= $this->include('partials/nav') ?>

    <!-- @fragment user-card -->
    <!-- Fragment boundary — this block can be returned alone via $res->fragment().
         The HTML comment syntax means zero runtime overhead and no DOM changes.
         PhpStorm highlights these if you add "\bfragment\b" pattern to TODO. -->
    <div id="user-card">
        <h1><?= e($user->name) ?></h1>
        <p><?= e($user->email) ?></p>
    </div>
    <!-- @end -->

    <?= asset('app.js') ?>
</body>
</html>

// e() wraps htmlspecialchars with ENT_QUOTES and UTF-8.
// Call it on every user-supplied string — skipping it is an XSS vulnerability.
e($string);
e($string, double_encode: false);  // use when value is already partially encoded
```

### Rendering

```php
return $res->view('users/show', ['user' => $user]);

// fragment() extracts only the @fragment block — does NOT render the full template.
// Use for htmx/datastar partial swaps: smaller response, no layout overhead.
return $res->fragment('users/show', ['user' => $user], 'user-card');

// smart_view() reads HX-Target / datastar-target headers and decides automatically.
// Prefer this in controllers that handle both full-page and partial requests.
return $res->smart_view('users/show', ['user' => $user]);

// Layout system: start/end capture output into named slots.
// The layout renders AFTER the child template, so slots are always populated.
// app/views/layouts/app.php
<?= $this->slot('content') ?>

// app/views/users/show.php
<?php $this->layout('layouts/app') ?>
<?php $this->start('content') ?>
    <h1>Hello</h1>
<?php $this->end() ?>

// view::share() injects data into every template for the current request.
// Register current_user here — not in every controller individually.
view::share('app_name', config('app.name'));
view::share('current_user', auth::user());
```

### Fragment TODO highlighting in PhpStorm

Add to PhpStorm: Settings → Editor → TODO → add pattern `\bfragment\b` with orange color.
Fragments use HTML comment syntax — no changes to HTML structure, zero runtime overhead:

```html
<!-- @fragment fragment-name -->
  ... html ...
<!-- @end -->
```

---

## Module 4 — Cache (skim/cache)

PSR-16 (SimpleCache) interface. Drivers: Redis (primary), File, Array (tests).
APCu intentionally excluded.

```php
// config/cache.php
// APCu excluded: shared-nothing architecture — APCu state is per-process and
// inconsistent under PHP-FPM with multiple workers. Redis is the safe default.
return [
    'driver'   => env('CACHE_DRIVER', 'redis'),   // redis | file | array
    'prefix'   => 'skim_',                         // prevents key collisions when sharing Redis
    'ttl'      => 3600,

    'redis' => [
        'host'     => env('REDIS_HOST', '127.0.0.1'),
        'port'     => env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASS', null),
        'database' => env('REDIS_DB', 0),
    ],

    'file' => [
        'path' => storage_path('cache'),
    ],

    // fallback: if Redis is unreachable, silently switch to file driver.
    // Prevents cache failure from cascading into app failure.
    'fallback' => 'file',
];

// Unified interface — swap driver in config without changing a single line of app code.
cache::set('user:1', $user, ttl: 3600);
cache::get('user:1');
cache::get('user:1', default: fn() => user::find(1));  // returns cached or runs the closure
cache::has('user:1');
cache::delete('user:1');
cache::flush_all();                    // clear entire cache (dangerous in production)
cache::flush('user:');                 // scoped invalidation by prefix — prefer this over flush_all()

// remember() is the standard pattern for expensive queries.
// Key naming convention: 'model:id' or 'model:scope' — makes flush() predictable.
$user = cache::remember('user:1', ttl: 3600, fn() => user::find(1));

// Tags are Redis-only. They group related keys for coordinated invalidation.
// After updating any user data, flush 'users' tag to expire all related entries.
cache::tags(['users', 'profiles'])->set('user:1:profile', $data);
cache::tags(['users'])->flush();  // invalidates every key tagged 'users' across all TTLs
```

---

## Module 5 — Helpers (skim/helpers)

### arr helper

```php
use skim\helpers\arr;

// arr::find() wraps native array_find() (PHP 8.4+) with key=>value shorthand:
arr::find(['status' => 'active'], $users);
// equivalent to: array_find($users, fn($u) => $u['status'] === 'active')

// Find all matches:
arr::find_all(['role' => 'admin'], $users);

// Re-index by a column value:
arr::map_by('id', $users);              // [5 => [...], 6 => [...]]

// Map key → value columns:
arr::map_col(key: 'id', val: 'name', $users);  // [5 => 'John', 6 => 'Jane']

// Pluck single column:
arr::pluck('name', $users);             // ['John', 'Jane']

// Filter by key=>value (returns array, reindexed):
arr::filter_by('status', 'active', $users);

// Nested re-index:
arr::map_nested('user_id', 'type', $rows);
// [user_id => [type => row]]

// Double-key index:
arr::map_keys('user_id', 'tag_id', $rows);
// [user_id => [tag_id => [rows]]]

// Normalize to 100%:
arr::normalize100(['a' => 80, 'b' => 20]);

// Weighted random pick:
arr::weighted_pick(['black' => 80, 'green' => 10, 'blue' => 10]);

// First / last element (null if empty) — wrap native PHP 8.5 functions:
arr::first($items);                     // delegates to array_first()
arr::last($items);                      // delegates to array_last()

// Debug print (inline, no HTML):
arr::to_string($array);
```

### filter helper

```php
use skim\helpers\filter;

// Scalar filters — return typed value or false:
filter::int($value);
filter::int($value, min: 1, max: 100);
filter::int_positive($value);           // > 0
filter::int_natural($value);            // >= 0
filter::float($value);
filter::float($value, min: 0.0, max: 999.99);
filter::bool($value);                   // '1','true','yes','on' → true
filter::date($value);                   // Y-m-d H:i:s or false
filter::time($value);                   // H:i:s or false
filter::ip($value);                     // IPv4 or false
filter::domain($value);
filter::email($value);
filter::url($value);
filter::username($value);               // [a-zA-Z0-9_@.-]+
filter::password($value);
filter::slug($value);                   // [a-z0-9-]+
filter::regex($value, pattern: '/^[a-z]+$/');
filter::in($value, ['a', 'b', 'c']);    // false if not in list

// Array filters:
filter::arr_int([1, 'x', '3', null]);   // [1, 3]
filter::arr_int_positive([0, 1, -1, 2]); // [1, 2]
filter::arr_in(['a','x','b'], ['a','b','c']); // ['a','b']

// Range check (returns value or false):
filter::range($num, min: 1, max: 100);
```

### str helper

```php
use skim\helpers\str;

str::slug('Hello World!');         // hello-world
str::excerpt($text, 100);          // truncate with ...
str::random(32);                   // random alphanumeric string
str::uuid();                       // UUID v4
str::contains($haystack, $needle);
str::starts_with($str, $prefix);
str::ends_with($str, $suffix);
str::to_snake('CamelCase');        // camel_case
str::to_camel('snake_case');       // snakeCase
```

---

## Module 6 — Validation (skim/validation)

Own implementation, no external dependencies.

```php
// validate::make() declares the expected shape of incoming data.
// Only fields listed here will appear in validated() output — extra POST fields are dropped.
$v = validate::make([
    'email'    => ['required', 'email'],
    'age'      => ['required', 'int', 'min:18', 'max:99'],
    'username' => ['required', 'min_len:3', 'max_len:32', 'regex:/^[a-z0-9_]+$/'],
    'role'     => ['required', 'in:admin,user,guest'],
    'website'  => ['url'],                  // optional — field may be absent, validated if present
    'password' => ['required', 'min_len:8'],
    'confirm'  => ['required', 'same:password'],
]);

$result = $v->check($req->post());

if (!$result->ok()) {
    // errors() returns field => [messages] map — matches standard 422 API response shape
    return $res->json(['errors' => $result->errors()], 422);
}

// validated() returns only the declared fields — safe to pass directly to model::create()
// without worrying about mass assignment of unexpected fields.
$clean = $result->validated();

// Custom rules registered globally — available in all validate::make() calls after registration.
validate::rule('phone_ua', function(mixed $val): bool {
    return (bool) preg_match('/^\+380\d{9}$/', (string) $val);
}, message: 'Invalid Ukrainian phone number');

// Reusable rule sets in classes prevent duplicating validation logic across controllers.
// create() and update() often share most rules but differ on 'required' and 'unique'.
class user_rules extends validate {
    public static function create(): array {
        return [
            'name'  => ['required', 'min_len:2', 'max_len:64'],
            'email' => ['required', 'email', 'unique:users,email'],
        ];
    }
}

$result = validate::make(user_rules::create())->check($data);
```

---

## Module 7 — Events (skim/events)

Synchronous by default — listeners run inline before the response returns.
Use `emit_async()` for side effects that shouldn't delay the response (email, reports, webhooks).

```php
// Register listeners anywhere before $app->run(), typically in a service provider.
// Multiple listeners per event are called in priority order (higher = first).
event::on('user.created', function(user $user): void {
    cache::flush('users:');  // invalidate cache on data change
});

event::on('user.created', [mailer::class, 'send_welcome']);

// Emitting passes the payload directly to each listener in order.
event::emit('user.created', $user);

// emit_async() requires skim/queue. Pushes a job instead of calling listeners inline.
// Safe for slow operations: sending email, generating reports, calling external APIs.
event::emit_async('report.generate', $data);

// Typed event classes are preferred: refactor-safe, IDE completion, explicit contracts.
// String events like 'user.created' are fine for simple cases.
class user_created_event {
    public function __construct(
        public readonly user $user,
        public readonly string $ip,  // capture request context at emit time, not in listener
    ) {}
}

event::on(user_created_event::class, function(user_created_event $e): void {
    log::info("New user {$e->user->email} from {$e->ip}");
});

event::emit(new user_created_event($user, $req->ip()));

// priority: controls listener execution order when order matters (e.g. auth before logging)
event::on('user.created', $handler, priority: 10);

// once() auto-removes listener after first call — use for one-time boot or warmup tasks
event::once('app.booted', function(): void { /* ... */ });
```

---

## Module 8 — Realtime: SSE + Datastar (skim/realtime)

### SSE

```php
// SSE is a long-running HTTP connection — $res->stream() disables output buffering
// and keeps the connection open. PHP-FPM must have execution time limits adjusted.
// nginx: set proxy_read_timeout 3600; to prevent upstream timeout on long streams.
$app->get('/stream', function(request $req, response $res) {
    return $res->stream(function(skim\http\sse $sse): void {

        $sse->send('hello');                           // plain text event
        $sse->send('hello', event: 'notification');   // named event — client listens with addEventListener
        $sse->send(['count' => 42], event: 'update'); // array auto-JSON-encodes

        // ping() sends a comment line (: ping) — keeps connection alive through proxies
        // that close idle connections. Send every 15–30 seconds for stable streams.
        $sse->ping();

        $sse->close();  // explicit close flushes buffer and ends the response
    });
});
```

### Datastar helpers

```php
use skim\realtime\datastar;

// Datastar SSE replaces the full request cycle for UI updates.
// The client connects once; the server pushes HTML/signal patches as they're ready.
// This is how SKIM avoids JSON APIs for frontend state — push HTML fragments directly.
$app->get('/ds-stream', function(response $res) {
    return $res->stream(function(skim\http\sse $sse): void {

        // merge() patches the DOM in-place. The selector must match an existing element.
        $sse->send(datastar::merge('#counter', '<span>42</span>'));

        $sse->send(datastar::remove('#old-item'));

        // signal() updates client-side reactive state (datastar store).
        // Use this to sync server state without re-rendering HTML.
        $sse->send(datastar::signal(['user' => ['name' => 'John', 'online' => true]]));

        $sse->send(datastar::script('console.log("ping")'));

        // Render a PHP fragment server-side and stream it to the DOM.
        // This is the core pattern: DB query → PHP render → DOM patch, no JavaScript needed.
        $html = view::render_fragment('users/card', ['user' => $user], 'user-card');
        $sse->send(datastar::merge('#user-card', $html));
    });
});
```

---

## Module 9 — Queue (skim/queue)

Optional module. Requires Redis. Install: `php skim module:add queue`

Queue decouples slow work (email, thumbnails, reports) from the HTTP response.
The web process pushes a job and returns immediately; a worker picks it up asynchronously.

```php
// Closures are fine for one-off tasks but NOT retryable — closure state isn't serialized.
// If the worker crashes mid-closure, the job is lost. Use job classes for anything important.
queue::push(fn() => mail::send($user, 'welcome'));
queue::push(fn() => thumbnail::generate($upload_id), delay: 30);  // delay in seconds

// Job classes: serialized to Redis as JSON. Only store IDs, not full objects.
// Why IDs only: objects can change between push and execution; fresh DB fetch is safer.
class generate_report_job implements skim\queue\job {
    public function __construct(
        private readonly int $report_id,  // store ID, fetch fresh in handle()
    ) {}

    public function handle(): void {
        $report = report::find($this->report_id);
        $report->generate();
    }

    // failed() is called after all retries are exhausted.
    // Log here — this is the last chance to capture the failure context.
    public function failed(\Throwable $e): void {
        log::error("Report {$this->report_id} failed: " . $e->getMessage());
    }
}

queue::push(new generate_report_job(42));
queue::push(new generate_report_job(42), delay: 60, tries: 3);  // retry up to 3 times

// Worker runs as a persistent process (supervisor/systemd in production):
// php skim queue:work
// php skim queue:work --sleep=3 --tries=3

// Inspect:
// php skim queue:status
// php skim queue:flush
```

---

## Module 10 — CLI (skim/cli)

Supports interactive mode via `proc_open` / native streams — no ob_content buffering issue.

### Colors and output

```php
use skim\cli\cli;

cli::info('Starting migration...');         // blue
cli::success('Done! 42 rows inserted.');    // green
cli::warn('Redis not found, using file.');  // yellow
cli::error('Connection failed.');           // red
cli::muted('debug: query took 12ms');       // gray
cli::line('plain output');

// Progress bar:
$bar = cli::progress(total: 100, label: 'Importing users');
foreach ($items as $item) {
    // do work
    $bar->advance();
}
// [████████████░░░░░░░░] 60% (60/100) Importing users

// Table:
cli::table(
    headers: ['ID', 'Name', 'Email', 'Status'],
    rows: $users
);
// ┌────┬──────────┬───────────────────┬────────┐
// │ ID │ Name     │ Email             │ Status │
// ├────┼──────────┼───────────────────┼────────┤
// │  1 │ John     │ john@example.com  │ active │
// └────┴──────────┴───────────────────┴────────┘

// Interactive input:
$name    = cli::ask('Project name?', default: 'myapp');
$driver  = cli::choice('Database driver?', ['mysql', 'pgsql', 'sqlite']);
$confirm = cli::confirm('Run migrations now?');  // y/N → bool

// Sections:
cli::section('Database');
cli::item('Connecting...', 'ok');     // → Connecting... ✓
cli::item('Migrating...', 'fail');    // → Migrating...  ✗
```

### Custom commands

```php
// app/commands/import_users_command.php
class import_users_command extends skim\cli\command {
    protected string $signature = 'import:users {file} {--dry-run}';
    protected string $description = 'Import users from CSV file';

    public function handle(cli $cli): int {
        $file    = $this->arg('file');
        $dry_run = $this->flag('dry-run');

        if (!file_exists($file)) {
            $cli->error("File not found: {$file}");
            return self::FAIL;
        }

        $rows = csv::read($file);
        $bar  = $cli->progress(total: count($rows), label: 'Importing');

        foreach ($rows as $row) {
            if (!$dry_run) {
                user::create($row);
            }
            $bar->advance();
        }

        $cli->success('Import complete.');
        return self::OK;
    }
}

// Register in config/app.php:
'commands' => [
    import_users_command::class,
],

// Run:
// php skim import:users users.csv
// php skim import:users users.csv --dry-run
```

---

## Module 11 — Dev: Profiler + Toolbar + Error Page (skim/dev)

Active only when `APP_DEBUG=true`. Zero overhead in production — all calls are no-ops.

### File structure

```
src/dev/
  profiler.php    ← collects events (DB, cache, view, logs) per request in memory
  toolbar.php     ← renders HTML debug bar, injected before </body> via response middleware
  error_page.php  ← Throwable handler, renders dev-friendly error page with code context
```

### Profiler

```php
use skim\dev\profiler;

// Each module calls profiler internally — you never call these directly in app code.
// All methods are static no-ops when APP_DEBUG=false — zero cost in production.

// db.php calls after every query — $sql already interpolated (bound values substituted):
profiler::db(
    sql: "SELECT * FROM users WHERE status = 'active'",
    ms: 4.2,
    connection: 'default',
    rows: 12,
);

// cache.php calls on every get/set/delete/remember:
profiler::cache(
    op: 'get',       // get | set | delete | flush | remember
    key: 'user:42',
    hit: true,       // false = cache miss
    ttl: 3600,
    driver: 'redis',
);

// view.php calls after every render:
profiler::view(template: 'users/card', fragment: 'user-card', ms: 1.1);

// log::* calls route through here with file+line from debug_backtrace():
profiler::log(
    level: 'warning',  // PSR-3: debug|info|notice|warning|error|critical|alert|emergency
    message: 'Slow query',
    context: ['ms' => 320],
    file: '/app/controllers/users_controller.php',
    line: 42,
);

// Read collected data (used by toolbar renderer):
profiler::summary();  // ['db' => ['count'=>3,'ms'=>18], 'cache' => ['hits'=>5,'misses'=>1], ...]
profiler::events();   // all raw events
profiler::reset();    // clear buffer — use in tests between requests
```

### Debug Toolbar

Injected as last global middleware. Appends HTML before `</body>` only for `text/html` responses.

```
┌──────────────────────────────────────────────────────────────────────────────────┐
│ SKIM │ 42ms │ DB: 3 queries 18ms │ Cache: 5 hits 1 miss │ View: 2 │ Logs: 4 ⚠   │
└──────────────────────────────────────────────────────────────────────────────────┘
```

Tabs on click:

| Tab | Shows |
|---|---|
| **DB** | Interpolated SQL (bound values visible), time ms, connection, rows. Duplicate queries highlighted red |
| **Cache** | key, driver, op, hit/miss, TTL |
| **Session** | Full `$_SESSION` dump |
| **Cookies** | Name / value / flags |
| **Files** | `get_included_files()` grouped by src / app / vendor |
| **Templates** | All rendered templates and fragments with time |
| **Logs** | All log entries — filter by level and by source file |
| **Request** | Method, URL, headers, GET/POST/JSON body |

### Logs in toolbar

```php
// All log::* calls are captured with file+line context via debug_backtrace()
log::debug('Cache warmed');
log::info('User logged in', ['id' => 5]);
log::warning('Slow query', ['ms' => 320]);
log::error('Payment timeout');
log::critical('DB connection lost');

// Toolbar log panel:
// - Filter by level (click badge to toggle on/off)
// - Filter by source file (click filename to isolate that file's logs)
// - [Track] — pins entry, survives page reloads (stored in session)
// - [Clear] — clears log buffer for that source file
```

PSR-3 severity order: `debug → info → notice → warning → error → critical → alert → emergency`

### Error page

Registered via `set_exception_handler` in `app::run()`. Active only when `APP_DEBUG=true`.

```
╔══════════════════════════════════════════════════════════╗
║  RuntimeException                                        ║
║  No DB connection 'analytics' in config/db.php          ║
╠══════════════════════════════════════════════════════════╣
║  src/db/db.php : 51                                      ║
║                                                          ║
║   49 │  if (!isset(self::$pool[$connection])) {          ║
║   50 │      $cfg = config("db.{$connection}")            ║
║ → 51 │          ?? throw new \RuntimeException(...);     ║
║   52 │  }                                                ║
╠══════════════════════════════════════════════════════════╣
║  Stack trace  │  Request  │  Headers  │  POST  │  Session║
╚══════════════════════════════════════════════════════════╝
```

In production (`APP_DEBUG=false`): generic 500 page, exception logged via `log::critical()`.

---

## Module 12 — Assets / Vite integration (skim/assets)

### Config

```php
// config/assets.php
return [
    'driver'          => 'vite',          // vite | mix | encore | none
    'package_manager' => 'npm',           // npm | yarn | pnpm | bun
    'auto_start'      => true,            // start with php skim serve

    'vite' => [
        'dev_port'  => 5173,
        'config'    => 'vite.config.js',
        'build_dir' => 'public/build',
    ],
];
```

### Template helper

```php
// In PHP templates — auto-detects dev vs production:
<?= asset('app.js') ?>
<?= asset('app.css') ?>

// DEV → injects Vite client + module script
// PROD → reads manifest.json, outputs hashed filenames
```

### Dev server

```bash
# Start PHP + Vite together:
php skim serve
php skim serve --port=9000 --vite-port=5174

# Output:
# ┌────────────────────────────────────────┐
# │  SKIM dev server                       │
# │  PHP   →  http://localhost:8080        │
# │  Vite  →  http://localhost:5173        │
# └────────────────────────────────────────┘
# [skim] GET  /           200  8ms
# [vite] ✓ ready in 340ms
# [skim] GET  /users       200  23ms
# [vite] hmr update /src/app.js
# Ctrl+C → stops both processes cleanly

# Other asset commands:
php skim assets:build        # npm run build
php skim assets:dev          # npm run dev only
php skim assets:install      # npm install
php skim assets:status       # show Vite process status
```

### Generated vite.config.js (if htmx or datastar selected at install)

```js
// vite.config.js — generated by installer
import { defineConfig } from 'vite'

export default defineConfig({
    // [datastar] import datastar from 'https://cdn.jsdelivr.net/npm/@sudoless/datastar@1/dist/es/datastar.js'
    // [htmx]     import htmx from 'htmx.org'
    build: {
        outDir: 'public/build',
        manifest: true,
        rollupOptions: {
            input: 'src/app.js',
        },
    },
    server: {
        port: 5173,
        cors: true,
    },
})
```

---

## Module 12 — HTTP Client (skim/http-client)

Wrapper around symfony/http-client. Thin layer with SKIM conventions.

```php
use skim\http\client;

// GET:
$res = client::get('https://api.example.com/users', [
    'headers' => ['Authorization' => 'Bearer ' . $token],
    'timeout' => 5,
]);

// POST JSON:
$res = client::post('https://api.example.com/users', [
    'json'    => ['name' => 'John', 'email' => 'j@j.com'],
    'headers' => ['X-Api-Key' => $key],
]);

// Response:
$res->status();           // 200
$res->ok();               // true if 2xx
$res->body();             // raw string
$res->json();             // decoded array
$res->header('X-Token');

// Async (symfony/http-client native):
$responses = client::async([
    client::get('https://api1.com/data'),
    client::get('https://api2.com/data'),
]);
foreach ($responses as $r) {
    $r->json();
}
```

---

## Module 13 — Logging (skim/log)

Monolog under the hood. PSR-3 interface.

```php
// config/app.php
'log' => [
    'channel' => env('LOG_CHANNEL', 'file'),  // file | stderr | stack
    'level'   => env('LOG_LEVEL', 'debug'),
    'path'    => storage_path('logs/app.log'),
    'days'    => 14,                           // rotate daily, keep 14
],

// Usage:
log::debug('Query executed', ['sql' => $sql, 'time' => $ms]);
log::info('User logged in', ['user_id' => $user->id]);
log::warn('Slow query detected', ['ms' => 850]);
log::error('Payment failed', ['order_id' => $id, 'reason' => $e->getMessage()]);
log::critical('Database unreachable');

// Context is always an array (second argument):
log::info('Order placed', ['order' => $order->id, 'total' => $order->total]);
```

---

## Module 14 — i18n (skim/i18n)

symfony/translation under the hood. PHP array message files only (no YAML, no XML).

```php
// lang/en/messages.php
return [
    'welcome'        => 'Welcome, :name!',
    'items_count'    => '{0} No items|{1} One item|[2,*] :count items',
    'errors.required'=> 'The :field field is required.',
];

// lang/uk/messages.php
return [
    'welcome' => 'Ласкаво просимо, :name!',
];

// Usage:
t('welcome', ['name' => 'John']);          // Welcome, John!
t('items_count', ['count' => 5]);          // 5 items
t('errors.required', ['field' => 'email']);

// Set locale:
i18n::set_locale('uk');
i18n::locale();   // → 'uk'

// In config/app.php:
'locale'          => env('APP_LOCALE', 'en'),
'fallback_locale' => 'en',
```

---

## Module 15 — WebSocket (skim/websocket)

Based on amphp/websocket-server (Revolt event loop, PHP 8.5 Fibers). Solves F3's ob_content blocking problem.
Architected with Revolt now so that when native async/await lands in a future PHP version, it becomes a drop-in replacement.

```php
// Handler class:
class chat_handler implements skim\ws\handler {
    public function on_open(skim\ws\connection $conn): void {
        cli::info("Client #{$conn->id()} connected");
    }

    public function on_message(skim\ws\connection $conn, string $message): void {
        $data = json_decode($message, true);
        $conn->broadcast(json_encode(['from' => $conn->id(), 'text' => $data['text']]));
    }

    public function on_close(skim\ws\connection $conn): void {
        cli::muted("Client #{$conn->id()} disconnected");
    }

    public function on_error(skim\ws\connection $conn, \Throwable $e): void {
        log::error('WebSocket error', ['msg' => $e->getMessage()]);
    }
}

// Register route:
$app->ws('/chat', new chat_handler());
$app->ws('/notifications', new notifications_handler());

// Per-connection rooms:
$conn->join('room:42');
$conn->leave('room:42');
$conn->to('room:42')->send('new message');

// Run WS server:
// php skim ws:serve
// php skim ws:serve --port=8181
```

---

## Module 16 — Session (skim/session)

```php
// config/app.php
'session' => [
    'driver'   => 'redis',   // redis | file
    'lifetime' => 7200,
    'prefix'   => 'sess_',
],

// Usage:
session::set('user_id', $user->id);
session::get('user_id');
session::get('user_id', default: null);
session::has('user_id');
session::delete('user_id');
session::flush();

// Flash (survives one redirect):
session::flash('success', 'Profile updated!');
session::get_flash('success');

// Regenerate ID (after login):
session::regenerate();
```

---

## IDE Helper generation

Single file `generated/.ide-helper.php` — never edit manually.
Regenerate after any migration: `php skim ide:generate`

```php
// generated/.ide-helper.php (example output)

/**
 * @property int         $id
 * @property string      $name
 * @property string      $email
 * @property string      $status
 * @property string|null $avatar
 * @property string      $created_at
 * @property string      $updated_at
 * @property-read role   $role        (belongs_to)
 * @property-read group[] $groups     (has_many)
 */
class user {}

/**
 * @property int    $id
 * @property string $name
 * @property string $slug
 */
class group {}
```

---

## Interactive Installer

```bash
composer create-project skim/skim myapp
cd myapp

# Wizard runs automatically:

  SKIM Framework Installer v1.0
  ──────────────────────────────

  ? Database driver          › mysql / pgsql / both / skip
  ? ORM style                › active-record / merry(relations) / eloquent / raw-only
  ? Cache driver             › redis / file / both
  ? Queue (redis required)   › yes / no
  ? Realtime SSE/Datastar    › yes / no
  ? WebSocket                › yes / no
  ? Frontend assets          › vite / none
    → If vite: htmx / datastar / none
  ? Auth                     › session / jwt / both / none
  ? Mailer                   › yes / no
  ? i18n                     › yes / no

  Installing selected modules...
  Generating config/...
  Running composer install...
  Done ✓  Run: php skim serve
```

Add modules later:

```bash
php skim module:add queue
php skim module:add websocket
php skim module:list            # show installed/available
```

---

## Built-in CLI commands summary

```bash
php skim serve [--port=8080] [--vite-port=5173]

php skim migrate
php skim migrate:down [--steps=1]
php skim migrate:fresh
php skim migrate:status
php skim migrate:make create_orders_table

php skim ide:generate           # regenerate generated/.ide-helper.php

php skim queue:work [--sleep=3] [--tries=3]
php skim queue:status
php skim queue:flush

php skim ws:serve [--port=8181]

php skim assets:build
php skim assets:install
php skim assets:status

php skim module:add {name}
php skim module:list

php skim cache:clear
php skim cache:status
```

---

## Module 17 — Agentic-first documentation

SKIM is designed to be worked on **by humans and LLMs equally**. Documentation lives in two places: PHPDoc inside code (method-level contracts) and `AGENT.md` files inside each module directory (module-level contracts). Together they give an LLM working on any file enough context without reading the entire codebase.

### AGENT.md hierarchy

```
skim/
├── AGENT.md                  ← root: architecture, global rules, module map
├── src/
│   ├── core/
│   │   └── AGENT.md          ← routing, request, response, container contracts
│   ├── db/
│   │   └── AGENT.md          ← query_gen, active record, merry orm contracts
│   ├── view/
│   │   └── AGENT.md          ← templates, fragments, layouts contracts
│   ├── cache/
│   │   └── AGENT.md          ← drivers, fallback, tag behaviour contracts
│   ├── helpers/
│   │   └── AGENT.md          ← arr, filter, str contracts
│   ├── validation/
│   │   └── AGENT.md          ← rules engine, custom rules contracts
│   ├── events/
│   │   └── AGENT.md
│   ├── queue/
│   │   └── AGENT.md
│   ├── realtime/
│   │   └── AGENT.md
│   ├── cli/
│   │   └── AGENT.md
│   ├── http/
│   │   └── AGENT.md
│   └── websocket/
│       └── AGENT.md
└── tests/
    └── AGENT.md              ← how to write tests, what to cover, what to mock
```

**Rule for LLMs:** when working on any file in `src/X/`, always read `src/X/AGENT.md` first, then the root `AGENT.md`. Never skip this step.

---

### Root AGENT.md structure

```markdown
# SKIM Framework — Agent Root

## Read this first
This is SKIM — a PHP 8.5+ micro-framework. Snake_case everywhere.
Braces same line. Types always declared. No YAML. No Guzzle. No Twig.
Property hooks and asymmetric visibility are used throughout — read PHP 8.4/8.5 sections first.

## Module map — which AGENT.md to read for what task
| Task                                    | Read                    |
|-----------------------------------------|-------------------------|
| routing, request, response, middleware  | src/core/AGENT.md       |
| SQL queries, PDO, active record, ORM    | src/db/AGENT.md         |
| PHP templates, fragments, layouts       | src/view/AGENT.md       |
| redis, file cache, cache tags           | src/cache/AGENT.md      |
| arr, filter, str helpers                | src/helpers/AGENT.md    |
| form validation, rules                  | src/validation/AGENT.md |
| events, listeners                       | src/events/AGENT.md     |
| background jobs, workers                | src/queue/AGENT.md      |
| SSE, datastar, htmx streaming           | src/realtime/AGENT.md   |
| CLI commands, progress, interactive     | src/cli/AGENT.md        |
| HTTP client, external API calls         | src/http/AGENT.md       |
| WebSocket handlers, rooms               | src/websocket/AGENT.md  |
| writing or running tests                | tests/AGENT.md          |

## Global rules (enforced in every module)
- snake_case: classes, methods, properties, filenames, namespace segments
- braces same line: `if ($x) {` — never `if ($x)\n{`
- types always declared — no untyped signatures ever
- config is PHP arrays only — no YAML, no INI
- null means "not found / not set" — never throw when null is valid
- throw named exceptions, never generic \Exception
- every public method has a PHPDoc @ai-contract block
- every new feature needs a Pest test before merge
- use property hooks for model normalization/computed fields — not custom setters
- use asymmetric visibility for system fields (id, created_at) — not $guarded alone
- use pipe |> for multi-step data transformations — not nested calls
- mark fluent builder methods with #[\NoDiscard] — caller must capture the return

## What NOT to do
- never add a dependency without updating composer.json AND the relevant AGENT.md
- never bypass cache::remember() — always cache expensive queries
- never write raw SQL with user input interpolated — always :named params
- never add a new public method without a PHPDoc @ai-contract block
- never silently swallow exceptions — log then rethrow or return typed error
```

---

### Module AGENT.md structure — example: src/db/AGENT.md

```markdown
# src/db — Agent Contract

## What this module does
PDO wrapper, query_gen, active record (model), merry ORM (merry_model).
Supports MySQL and PostgreSQL. Schema cached on first connect.

## query_gen — critical behaviours LLM must know
- %where% with empty array or all-null conditions → removed silently, query runs
- %where% never present with populated conditions → WHERE injected automatically
- null values in %set% → that column is SKIPPED, not set to NULL
- to explicitly set NULL use: 'set' => ['col' => db::null()]
- debug:true → returns interpolated SQL string, does NOT execute
- :named params always — never interpolate user input into SQL string
- limit/offset accept both 'limit' and ':limit' key formats
- or/and nesting in %where% → generates (a AND b) OR (c AND d) grouping

## active record — critical behaviours
- find($id) returns null if not found — never throws
- find_or_fail($id) throws not_found_exception — use in controllers
- schema is fetched once via DESCRIBE, stored in cache driver
- invalidate schema cache after migrations: cache::flush('schema:')
- $guarded columns are never mass-assigned even if present in input array
- save() runs INSERT if no primary key, UPDATE if primary key set
- save() with dirty tracking: UPDATE only changed columns, not all columns
- property hooks run on every assignment — hooks fire even inside hydrate()
- id/created_at/updated_at use asymmetric visibility — writable only inside model
- computed properties (get-only hooks) are never included in INSERT/UPDATE

## merry ORM — critical behaviours
- with('relation') eager loads — always prefer over lazy on list pages
- with('posts.comments') loads nested — dot notation for depth
- attach/detach/sync only available on many_to_many relations
- relations defined in static arrays, not annotations

## common mistakes to avoid
- calling all() without limit on large tables → always paginate
- forgetting to call db::transaction() when doing multi-table writes
- using find() result without null check when not using find_or_fail()

## dependencies
- PDO (PHP built-in)
- No external ORM packages in active-record mode
- Eloquent (illuminate/database) only if installed via module:add eloquent
```

---

### PHPDoc @ai-contract — method level

Every public method gets a structured PHPDoc block. The `@ai-contract` tag is the machine-readable part — precise, terse, no prose.

```php
/**
 * Execute a query_gen template against the database.
 *
 * @ai-contract input   $sql string with %placeholders%, $params array
 * @ai-contract returns array of rows (empty array if no results, never null)
 * @ai-contract returns string if debug:true (query NOT executed)
 * @ai-contract side-effect executes SQL against configured PDO connection
 * @ai-contract throws   db_exception on PDO error (never swallows)
 * @ai-contract null-safe %where% silently removed if conditions resolve empty
 * @ai-contract null-safe null values in %set% are silently skipped
 */
public static function query(string $sql, array $params = [], bool $debug = false, string $connection = 'default'): array|string {}

/**
 * Find a model record by primary key.
 *
 * @ai-contract returns static instance if found, null if not found
 * @ai-contract never throws for missing record — use find_or_fail() for that
 * @ai-contract result cached in request scope — repeated calls don't hit DB
 * @ai-contract input $id is always cast to int internally
 */
public static function find(int $id): ?static {}

/**
 * Render a view template, optionally returning only a named fragment.
 *
 * @ai-contract returns full rendered HTML string by default
 * @ai-contract if $fragment set: returns only content between matching
 *              <!-- @fragment {name} --> and <!-- @end --> tags
 * @ai-contract throws view_exception if template file not found
 * @ai-contract throws view_exception if $fragment name not found in template
 * @ai-contract $data keys become local variables inside template
 * @ai-contract always call e() on user-supplied data inside templates
 */
public static function render(string $template, array $data = [], ?string $fragment = null): string {}
```

---

### tests/AGENT.md — contract for writing tests

```markdown
# tests/ — Agent Contract

## Test framework
Pest PHP (over PHPUnit). snake_case test descriptions. No test classes unless
dataset sharing requires it.

## File structure mirrors src/
tests/
  db/
    query_gen_test.php       ← mirrors src/db/query_gen.php
    model_test.php
    merry_model_test.php
  view/
    render_test.php
    fragment_test.php
  cache/
    redis_driver_test.php
    file_driver_test.php
  helpers/
    arr_test.php
    filter_test.php
  validation/
    rules_test.php
  core/
    router_test.php
    request_test.php

## Rules for writing tests
- one test per behaviour, not one test per method
- test description is a plain English sentence: 'where clause removed when params empty'
- never test implementation details — test observable behaviour only
- use in-memory array cache driver for all tests (never real Redis)
- use SQLite :memory: for DB tests — no real MySQL required
- mock external HTTP calls with a fake client — never real network in tests
- each test is fully independent — no shared mutable state between tests
- group related tests with describe() blocks

## What must be tested (minimum coverage per module)
- db: query_gen placeholder removal, %set% null skip, %where% or/and nesting,
      find() null return, find_or_fail() exception, transaction rollback
- view: full render, fragment extraction, missing fragment exception,
        layout slot injection, e() escaping
- cache: set/get/delete, ttl expiry, fallback driver on failure, flush('prefix:')
- helpers: every filter type returns false on invalid input,
           arr::map_by key collision behaviour
- validation: required rule, type rules, custom rule registration,
              validated() returns only declared fields
- routing: static route match, dynamic route with type token,
           404 on no match, middleware execution order

## Running tests
php skim test                  # all tests
php skim test --module=db      # single module
php skim test --filter="where clause"  # by description
php skim test --coverage       # with coverage report
```

---

## Module 18 — Testing (Pest)

### Setup

Pest is the only test runner. PHPUnit is a peer dependency under the hood — never write PHPUnit-style test classes unless absolutely necessary.

```bash
# Run all tests:
php skim test

# Module only:
php skim test --module=db
php skim test --module=view

# Filter by description:
php skim test --filter="null values in set"

# Coverage (requires Xdebug or PCOV):
php skim test --coverage
php skim test --coverage --min=80   # fail if below 80%
```

### Test examples — db module

```php
// tests/db/query_gen_test.php

describe('query_gen %where%', function() {

    test('removed when conditions array is empty', function() {
        $sql = db::query('SELECT * FROM users %where%', ['where' => []], debug: true);
        expect($sql)->not->toContain('WHERE');
    });

    test('removed when all condition values are null', function() {
        $sql = db::query('SELECT * FROM users %where%', [
            'where'   => ['status = :status'],
            ':status' => null,
        ], debug: true);
        expect($sql)->not->toContain('WHERE');
    });

    test('injected when conditions are present', function() {
        $sql = db::query('SELECT * FROM users %where%', [
            'where'   => ['status = :status'],
            ':status' => 'active',
        ], debug: true);
        expect($sql)->toContain('WHERE status = \'active\'');
    });

    test('or/and nesting generates correct grouping', function() {
        $sql = db::query('SELECT * FROM products %where%', [
            'where' => [
                'and' => [['category = :cat']],
                'or'  => [['name LIKE :s'], ['description LIKE :s']],
            ],
            ':cat' => 'electronics',
            ':s'   => '%phone%',
        ], debug: true);
        expect($sql)
            ->toContain('category =')
            ->toContain('name LIKE')
            ->toContain('OR');
    });

});

describe('query_gen %set%', function() {

    test('null values are skipped silently', function() {
        $sql = db::query('UPDATE users %set% WHERE id = :id', [
            'set' => ['name' => 'John', 'avatar' => null],
            ':id' => 1,
        ], debug: true);
        expect($sql)
            ->toContain('name =')
            ->not->toContain('avatar');
    });

});

describe('model::find()', function() {

    test('returns model instance when record exists', function() {
        $user = user::find(1);
        expect($user)->toBeInstanceOf(user::class);
    });

    test('returns null when record does not exist', function() {
        $user = user::find(999999);
        expect($user)->toBeNull();
    });

    test('find_or_fail throws not_found_exception for missing record', function() {
        expect(fn() => user::find_or_fail(999999))
            ->toThrow(skim\db\exceptions\not_found_exception::class);
    });

});
```

### Test examples — view fragments

```php
// tests/view/fragment_test.php

describe('view fragments', function() {

    test('renders full template when no fragment specified', function() {
        $html = view::render('fixtures/with_fragment', ['name' => 'John']);
        expect($html)
            ->toContain('<html>')
            ->toContain('John');
    });

    test('returns only fragment content when fragment name given', function() {
        $html = view::render('fixtures/with_fragment', ['name' => 'John'], fragment: 'user-card');
        expect($html)
            ->toContain('John')
            ->not->toContain('<html>');
    });

    test('throws view_exception when fragment name not found', function() {
        expect(fn() => view::render('fixtures/with_fragment', [], fragment: 'nonexistent'))
            ->toThrow(skim\view\exceptions\view_exception::class);
    });

});
```

### Test examples — helpers

```php
// tests/helpers/filter_test.php

describe('filter::int()', function() {

    test('returns int for valid numeric string', function() {
        expect(filter::int('42'))->toBe(42);
    });

    test('returns false for non-numeric string', function() {
        expect(filter::int('abc'))->toBeFalse();
    });

    test('returns false when below min', function() {
        expect(filter::int('5', min: 10))->toBeFalse();
    });

    test('returns false when above max', function() {
        expect(filter::int('150', max: 100))->toBeFalse();
    });

    test('returns value when within min/max range', function() {
        expect(filter::int('50', min: 1, max: 100))->toBe(50);
    });

});

describe('filter::arr_int()', function() {

    test('filters out non-numeric values', function() {
        expect(filter::arr_int([1, 'x', '3', null, 0]))->toBe([1, 3, 0]);
    });

});

describe('arr::map_by()', function() {

    test('re-indexes array by column value', function() {
        $input = [['id' => 5, 'name' => 'John'], ['id' => 6, 'name' => 'Jane']];
        $result = arr::map_by('id', $input);
        expect($result)->toHaveKey(5)->toHaveKey(6);
        expect($result[5]['name'])->toBe('John');
    });

});
```

### Test helpers and fixtures

```php
// tests/helpers.php — shared test utilities loaded by Pest

// In-memory SQLite DB for all DB tests:
function test_db(): skim\db\connection {
    return db::connect([
        'driver'   => 'sqlite',
        'database' => ':memory:',
    ]);
}

// Array cache for all cache tests (no real Redis):
function test_cache(): skim\cache\cache {
    return cache::driver('array');
}

// Fake HTTP client — no real network:
function fake_http(array $responses = []): skim\http\fake_client {
    return new skim\http\fake_client($responses);
}

// Create a minimal app instance for controller tests:
function test_app(): skim\core\app {
    return skim\core\app::test_instance([
        'cache'  => ['driver' => 'array'],
        'db'     => ['driver' => 'sqlite', 'database' => ':memory:'],
    ]);
}
```

---

## Module 19 — CI/CD (GitHub Actions + local)

### Local: php skim test

`php skim test` is the gate before every commit. Claude Code must run it after every code change and not proceed until all tests pass.

```bash
php skim test                        # full suite
php skim test --module=db            # one module
php skim test --coverage --min=80    # with coverage gate
```

### GitHub Actions — generated at install

```yaml
# .github/workflows/ci.yml — generated by installer

name: CI

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      redis:
        image: redis:7-alpine
        ports: ['6379:6379']

    strategy:
      matrix:
        php: ['8.4', '8.5']

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP ${{ matrix.php }}
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: pdo, pdo_sqlite, pdo_mysql, redis, pcov
          coverage: pcov

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Run tests
        run: php skim test --coverage --min=80

      - name: Upload coverage
        uses: codecov/codecov-action@v4
        if: matrix.php == '8.5'
        with:
          files: ./coverage.xml
```

### Workflow when working with Claude

When writing a new feature with Claude Code, the required sequence is:

```
1. Claude writes the feature code
2. Claude writes Pest tests for that feature
3. php skim test runs automatically
4. If any test fails → Claude fixes code (not tests) and retries
5. All tests green → feature is ready to commit
6. git push → GitHub Actions runs full matrix (PHP 8.4/8.5)
```

This sequence is encoded in `AGENT.md` root so Claude Code follows it automatically:

```markdown
## Workflow — required sequence for every new feature
1. Write feature code in src/X/
2. Write Pest tests in tests/X/ mirroring the src structure
3. Run: php skim test --module=X
4. Fix code until tests pass — never modify tests to make them pass
5. Run: php skim test (full suite) to confirm no regressions
6. Only then commit
```

---

## PHP 8.1 — 8.5 features — use them

### PHP 8.1 (released November 2021) — baseline, use everywhere

**Enums** — type-safe sets of values, replaces string/int constants:
```php
// Before 8.1: const STATUS_ACTIVE = 'active' — just a string, no type safety
enum user_status: string {
    case Active   = 'active';
    case Inactive = 'inactive';
    case Banned   = 'banned';
}

// Usage in model — IDE knows the valid values, typos are compile errors:
public user_status $status;
$user->status = user_status::Active;
$user->status = 'active';  // Error: string is not user_status

// Backed enums can convert to/from string for DB storage:
user_status::from('active');          // → user_status::Active
user_status::tryFrom('unknown');      // → null (safe, no exception)
$user->status->value;                 // → 'active' (for DB INSERT/UPDATE)
```

**`readonly` properties** — set once, never mutate:
```php
// Ideal for IDs and timestamps that must not change after hydration:
class event {
    public function __construct(
        public readonly int    $id,
        public readonly string $occurred_at,
    ) {}
}
// PHP 8.2 adds readonly classes — see below
```

**Fibers** — cooperative multitasking (foundation of WebSocket and SSE in SKIM):
```php
// Fibers let you pause and resume execution — no OS threads needed.
// SKIM's WebSocket and SSE modules use amphp/revolt which is built on Fibers.
// You don't create Fibers directly; the event loop manages them internally.
$fiber = new Fiber(function(): void {
    $value = Fiber::suspend('paused');  // pause, yield control back to caller
    echo "resumed with: {$value}";
});

$yielded = $fiber->start();    // → 'paused'
$fiber->resume('hello');       // → 'resumed with: hello'
```

**`never` return type** — function that always throws or exits:
```php
// Documents intent: caller knows control never returns here
public function not_found(): never {
    throw new not_found_exception();
}

public function abort(int $code): never {
    http_response_code($code);
    exit;
}
```

**Intersection types** — value must satisfy multiple interfaces at once:
```php
// Require both interfaces, not just one:
public function process(Stringable&JsonSerializable $payload): void {}
```

**`array_is_list()`** — distinguish sequential `[0,1,2]` from associative `['a'=>1]`:
```php
// Used in query_gen to detect flat vs nested where conditions:
if (array_is_list($where)) {
    // ['status = :s', 'age > :a'] — flat list of SQL conditions
} else {
    // ['and' => [...], 'or' => [...]] — nested and/or structure
}
```

**First-class callable syntax** — reference a function as a closure without wrapping:
```php
// Before 8.1:
$fn = fn($x) => strtolower($x);
arr::map($items, fn($x) => strtolower($x));

// After 8.1: cleaner, composable, referenceable
$fn = strtolower(...);
arr::map($items, strtolower(...));

// Also works for methods:
$fn = $user->get_name(...);
$fn = user::find(...);

// Combines with PHP 8.5 pipe operator:
$slug = $title |> trim(...) |> strtolower(...);
```

---

### PHP 8.2 (released December 2022) — use everywhere

**`readonly` classes** — every property becomes readonly automatically. Use for immutable value objects, DTOs, config:
```php
// Before 8.2: had to mark each property readonly individually
readonly class db_config {
    public function __construct(
        public string $host,
        public string $database,
        public int    $port = 3306,
    ) {}
}

// Can't mutate — safe to pass anywhere:
$cfg = new db_config(host: 'localhost', database: 'myapp');
$cfg->host = 'other';  // Error: Cannot modify readonly property

// Clone With (PHP 8.5) makes readonly classes even more useful — see below
```

**`null`, `true`, `false` as standalone types** — for methods with known exact return values:
```php
// Before 8.2: had to use bool
public function is_cli(): true|false {}  // redundant, just use bool

// Useful as return type for functions that never return (throw/exit):
public function abort(int $code): never {
    throw new http_exception($code);
}

// Or for factory methods that always succeed vs always null:
public static function from_env(): static|false {}
```

**Disjunctive Normal Form (DNF) types** — combine union and intersection types:
```php
// Intersection (&) AND union (|) in one type expression
public function paginate(Countable&ArrayAccess|array $items): pagination {}

// Useful in SKIM for accepting both objects and arrays in helpers:
public static function map_by(string $key, Arrayable&Countable|array $items): array {}
```

**`#[\SensitiveParameter]`** — hide sensitive values from stack traces:
```php
// Passwords and tokens are masked as *** in error logs automatically
public function connect(
    string $host,
    string $user,
    #[\SensitiveParameter] string $password,  // never appears in exception traces
): PDO {}
```

**Constants in traits** — share constants without inheritance:
```php
trait has_timestamps {
    // Shared across all models that use this trait
    const string CREATED_AT = 'created_at';
    const string UPDATED_AT = 'updated_at';
}
```

---

### PHP 8.3 (released November 2023) — use everywhere

**Typed class constants** — type-safe constants, no more accidental string/int confusion:
```php
class model {
    // Before 8.3: const TABLE = 'users' — no type guarantee
    const string TABLE    = '';    // subclass must override with a string
    const string PK       = 'id';
    const int    PER_PAGE = 20;
}

class user extends model {
    const string TABLE = 'users';  // enforced to be a string
}
```

**`#[Override]`** — declare that a method intentionally overrides a parent:
```php
class user extends model {
    // PHP will throw if model no longer has find() — catches stale overrides
    #[Override]
    public static function find(int $id): ?static {
        // custom user-specific find logic
    }
}
```

**`json_validate()`** — validate JSON without decoding it (zero memory overhead):
```php
// Before 8.3: had to decode and check for null
$body = $req->body();
if (!json_validate($body)) {
    return $res->json(['error' => 'invalid JSON'], 400);
}
$data = json_decode($body, true);
```

**Dynamic class constant fetch** — use variable to access constant:
```php
$model_class = user::class;
$table = $model_class::TABLE;  // dynamic, works with any model class
```

---

### PHP 8.4 (released November 2024) — use everywhere

**Property hooks** — the biggest feature. Use for:
- Auto-normalization: `public string $email { set(string $v) => strtolower(trim($v)); }`
- Computed fields: `public string $full_name { get => $this->first . ' ' . $this->last; }`
- Auto-hashing: `public string $password { set(string $v) => password_hash($v, PASSWORD_BCRYPT); }`
- Dirty tracking: track which model properties changed for surgical UPDATEs
- Format conversion: `public string $phone { get => '+' . $this->phone_raw; }`

**Asymmetric visibility** — protect system fields:
```php
public private(set) int $id;              // read anywhere, write only in class
public private(set) string $created_at;   // prevents accidental overwrites
public protected(set) bool $exists;       // subclasses can write
```

**array_find() / array_find_key()** — native functions:
```php
// Our arr::find() becomes thin wrapper or deprecated
$admin = array_find($users, fn($u) => $u->role === 'admin');
```

**#[\Deprecated] attribute** — mark F3 compat layer:
```php
#[\Deprecated('Use $app->get() instead', since: '1.0')]
public function route(string $pattern, callable $handler): void {}
```

### PHP 8.5 (released November 20, 2025) — use everywhere

**Pipe operator `|>`** — CONFIRMED, landed in 8.5. Query chains now read top-to-bottom:
```php
// Before: nested calls, read inside-out
$result = cache::remember('active_users', 3600, fn() =>
    arr::map_by('id',
        user::where(['status' => 'active'])->order('created_at DESC')->limit(50)->all()
    )
);

// After: pipeline reads as a sequence of steps
$result = user::where(['status' => 'active'])
    |> fn($q) => $q->order('created_at DESC')->limit(50)->all()
    |> fn($rows) => arr::map_by('id', $rows)
    |> fn($map) => cache::remember('active_users', 3600, fn() => $map);

// Note: right side must be a callable — use fn() or first-class callable syntax
$slug = $title
    |> trim(...)
    |> strtolower(...)
    |> fn($s) => str_replace(' ', '-', $s);
```

**Clone With** — modify properties during object cloning. Killer for readonly value objects:
```php
// Useful for immutable config or request objects in SKIM
readonly class db_config {
    public function __construct(
        public string $host,
        public int    $port = 3306,
        public string $database = '',
    ) {}

    // Before clone with: boilerplate spreading
    public function with_database(string $db): self {
        return clone($this, ['database' => $db]);
    }
}

$base   = new db_config(host: 'localhost');
$analytics = clone($base, ['database' => 'analytics', 'port' => 5432]);
```

**`#[\NoDiscard]`** — warn when return value is silently ignored. Enforces correct usage of fluent APIs:
```php
// Query builder methods return new instance — easy to forget and lose the result
#[\NoDiscard]
public function where(array|string $conditions, array $params = []): static {}

#[\NoDiscard]
public function limit(int $n): static {}

// Calling without capturing will now emit a warning:
user::where(['status' => 'active']);  // Warning: return value not used
$query = user::where(['status' => 'active']);  // correct

// Intentionally discard with (void) cast:
(void) cache::remember('key', 3600, fn() => warmup_data());
```

**`array_first()` / `array_last()`** — native shortcuts, no more `reset()` / `end()` hacks:
```php
$first = array_first($items);   // null if empty
$last  = array_last($items);    // null if empty

// Our arr helper wraps these:
arr::first($items);  // delegates to array_first()
arr::last($items);   // delegates to array_last()
```

**Static properties + asymmetric visibility** (extends PHP 8.4 to static context):
```php
// In model base class: protect static schema cache from external writes
public static private(set) array $schema_cache = [];
```

**async/await** — NOT in PHP 8.5. Still amphp/revolt-based (Fibers under the hood).
Build SSE handlers with Revolt now so that native async will be a drop-in when it lands.

**Generics** — NOT in PHP 8.5. Use PHPDoc `@return collection<user>` for IDE hints.

---

## Key constraints and rules for code generation

1. **PHP 8.5+ only.** Use property hooks, asymmetric visibility, enums, readonly properties, fibers, named arguments, intersection types where appropriate.
2. **Snake_case everywhere.** Classes, methods, properties, file names, namespace segments.
3. **Braces on same line** — `if ($x) {` never `if ($x)\n{`
4. **Always declare types.** No untyped function signatures.
5. **No YAML, no INI.** Config is PHP arrays only.
6. **No APCu.** Cache drivers: Redis, File, Array.
7. **No Guzzle.** HTTP client: symfony/http-client with thin SKIM wrapper.
8. **No Twig, no Blade.** Views: native PHP templates only.
9. **query_gen `%placeholder%` syntax preserved** for raw queries.
10. **Schema cache** — fetched once, stored in configured cache driver, invalidated by migrations.
11. **IDE helper** — single `generated/.ide-helper.php`, regenerated by `php skim ide:generate`.
12. **Fragment comments** `<!-- @fragment name -->` use HTML comment syntax, no structural changes.
13. **Each module installable independently** via `php skim module:add`.
14. **Backward-compatible routing syntax** with Fat-Free Framework 3.8+ where feasible.
15. **Readable code is a hard requirement.** Every class max ~200 lines. Extract when larger. Name variables for what they represent, not how they're used.
16. **Every public method has a PHPDoc `@ai-contract` block.** No exceptions.
17. **Every new feature ships with Pest tests.** Tests live in `tests/` mirroring `src/` structure.
18. **AGENT.md files are part of the codebase.** Update the relevant `AGENT.md` whenever behaviour changes. Outdated docs are bugs.
19. **Tests use in-memory drivers only.** SQLite `:memory:` for DB, array driver for cache, fake client for HTTP. No real external services in tests.
20. **Fix code to make tests pass — never modify tests to make them pass.** If a test seems wrong, raise it explicitly before changing it.
21. **Comments explain architecture, not code.** Assume reader opens file 2 years later — explain *why* decisions were made, how modules integrate, what breaks if bypassed, and edge cases. Never comment obvious behavior.