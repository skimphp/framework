# SKIM Framework — Implementation TODO

> **Current focus: Phase 0 → Phase 1**
> Convention: snake_case everywhere, braces same line, strict types always, no YAML.
> Write Pest tests alongside each file. Use SQLite :memory: for DB tests, array driver for cache.
> Package: `skim/framework` | PHP: `^8.5`

---

## Phase 0 — Infrastructure (Docker)

- [x] `docker/Dockerfile` — `php:8.5-cli-alpine` + pdo_mysql/pdo_pgsql/redis/pcov/mbstring/intl/zip/bcmath/pcntl/posix/sockets/opcache + Composer 2 + Node.js
- [x] `docker-compose.yml` — app + mysql:8.0 + postgres:16-alpine + redis:7-alpine, healthchecks, named volumes
- [x] `docker/php.ini` — E_ALL, opcache (validate_timestamps dev mode), pcov → /app/src
- [x] `Makefile` — `up/down/build/shell/test/pest/migrate/migrate-fresh/mysql-cli/pgsql-cli/redis-cli`
- [x] `.dockerignore`
- [x] `.env.example` — все переменные с docker defaults (DB_HOST=mysql, REDIS_HOST=redis)
- [x] Verify: `make build && make up && make shell` → bash в контейнере
- [x] Verify: `make test` → Pest запускается (0 tests, no errors)

---

## Phase 1 — Foundation (start here)

### 1.1 Project scaffold
- [x] `composer.json` — PSR-4 autoload `skim\\` → `src/`, `app\\` → `app/`, require nikic/fast-route
- [x] `public/index.php` — single entry point, requires autoload, creates app, runs
- [x] `config/app.php` — name, debug, env, key, timezone
- [x] `config/db.php` — default + optional analytics connection
- [x] `config/cache.php` — driver, redis, file, fallback
- [x] `.env.example` — all required env vars
- [x] `AGENT.md` — root agent contract (copy from framework.md spec)

### 1.2 Core: container + config + env
- [x] `src/Core/App.php` — PSR-11, singleton `instance()`, three scopes SYS/APP/USER
- [x] `src/Core/Config.php` — loads `config/*.php`, dot-notation `config('db.default.host')`
- [x] `src/Core/Env.php` — parses `.env` once, `env('KEY', default)`, no vlucas/phpdotenv
- [x] `src/Core/helpers.php` — global `config()`, `env()`, `route()`, `storagePath()`, `e()`
- [x] `tests/core/config_test.php`
- [x] `tests/core/env_test.php`

### 1.3 Core: router
- [x] `src/Core/Router.php` — wraps nikic/fast-route, `get/post/put/patch/delete/any`
- [x] `src/Core/Router.php` — route groups with middleware, named routes + `route()` helper
- [x] `src/Core/Router.php` — `@param` tokens: `@id:int`, `@slug:str`, `@any`
- [x] `src/Core/Router.php` — CLI `command()` routes
- [x] `tests/core/router_test.php` — static match, dynamic match, 404, named reverse

### 1.4 Core: request
- [x] `src/Core/Request.php` — `get/post/input/json/file/header/ip/method/path/url`
- [x] `src/Core/Request.php` — `isHtmx/isDatastar/isJson/isAjax/isCli`
- [x] `src/Core/Request.php` — injected via container, not instantiated manually
- [x] `tests/core/request_test.php`

### 1.5 Core: response
- [x] `src/Core/Response.php` — `json/view/redirect/download`
- [x] `src/Core/Response.php` — `stream(callable)` — disables output buffering, SSE
- [x] `src/Core/Response.php` — `fragment(template, data, name)` — extracts @fragment block
- [x] `src/Core/Response.php` — `smartView()` — auto full vs fragment by request headers
- [x] `src/Core/Response.php` — `status(int)` fluent, `back()` redirect
- [x] `tests/core/response_test.php`

### 1.6 Core: middleware pipeline
- [x] `src/Core/Middleware.php` — interface: `handle(request, response, callable $next): mixed`
- [x] `src/Core/Pipeline.php` — builds chain: global → group → route, short-circuit on return
- [x] `src/Middleware/Cors.php` — CORS headers, preflight OPTIONS handler
- [x] `src/Middleware/RateLimit.php` — Redis-backed sliding window
- [x] `tests/core/middleware_test.php` — execution order, short-circuit

---

## Phase 2 — Database

### 2.1 PDO wrapper + connection manager
- [x] `src/Db/Db.php` — connection pool, `query/val/row/all/transaction`, profiler hooks
- [x] `src/Db/QueryBuilder.php` — internal: `build/buildWhere/buildSet/buildValues/interpolate`
- [x] `src/Db/NullMarker.php` — sentinel for `Db::null()` (explicit SQL NULL in `%set%`)
- [x] `tests/db/query_builder_test.php` — %where% removal, %set% null skip, or/and nesting, debug interpolation, key-length sort

### 2.2 Active record model
- [x] `src/Db/Model.php` — base class, `find/findOrFail/where/all/count/raw/paginate/save/delete`
- [x] `src/Db/Model.php` — schema fetch (DESCRIBE/information_schema → cache)
- [x] `src/Db/Model.php` — dirty tracking via attribute __set()
- [x] `src/Db/Model.php` — `save()` uses dirty tracking → UPDATE only changed columns
- [x] `src/Db/Model.php` — `$guarded` excludes fields from mass assignment
- [x] `tests/db/model_test.php` — find null, findOrFail throw, save INSERT vs UPDATE, dirty tracking

### 2.3 Migrations
- [x] `src/Db/Migration.php` — abstract base: `up(): string`, `down(): string`
- [x] `src/Db/Migrator.php` — tracks batches in `_migrations` table, run/rollback/fresh/status
- [x] `migrations/` — empty dir with `.gitkeep`
- [x] `tests/db/migrator_test.php` — SQLite :memory:, run/down/idempotent

---

## Phase 2.5 — Dev module (skim/dev)

- [x] `src/Dev/Profiler.php` — static collector: `db/cache/view/log/summary/events/reset`, no-op when APP_DEBUG=false
- [x] `src/Dev/Toolbar.php` — HTML debug bar renderer, tabs: DB/Cache/Session/Cookies/Files/Templates/Logs/Request
- [x] `src/Dev/ErrorPage.php` — Throwable handler: exception + code context + stack trace + request dump
- [x] Wire profiler hooks into `src/Db/Db.php` (already done), `src/Cache/Cache.php`, `src/View/View.php`
- [x] Toolbar middleware — appends HTML before `</body>` for text/html responses only
- [x] `tests/dev/profiler_test.php` — no-op in prod, records correctly in debug, reset() clears buffer

---

## Phase 3 — Cache + View + Helpers

### 3.1 Cache
- [x] `src/Cache/Driver.php` — PSR-16 interface
- [x] `src/Cache/RedisDriver.php` — Redis, tags support
- [x] `src/Cache/FileDriver.php` — filesystem, TTL via serialized expiry
- [x] `src/Cache/ArrayDriver.php` — in-memory, for tests only
- [x] `src/Cache/Cache.php` — static facade, `set/get/has/delete/flush/flushAll/remember/tags`
- [x] `src/Cache/Cache.php` — fallback: if Redis fails → file driver
- [x] `tests/cache/cache_test.php` — set/get/delete, TTL expiry, fallback, flush('prefix:')

### 3.2 View
- [x] `src/View/Template.php` — template context (`$this` inside .php views), `include/layout/start/end/slot`
- [x] `src/View/View.php` — `render/renderFragment`, shared data via `View::share()`
- [x] `src/View/View.php` — fragment extraction: parse `<!-- @fragment name -->...<!-- @end -->`
- [x] `tests/view/view_test.php` — full render, fragment extraction, missing fragment throws, e() escaping

### 3.3 Helpers
- [x] `src/Helpers/Arr.php` — `find/findAll/mapBy/mapCol/pluck/filterBy/first/last/mapNested/mapKeys/normalize100/weightedPick/toString`
- [x] `src/Helpers/Filter.php` — `int/float/bool/date/time/ip/domain/email/url/username/slug/regex/in` + array variants
- [x] `src/Helpers/Str.php` — `slug/excerpt/random/uuid/contains/startsWith/endsWith/toSnake/toCamel`
- [x] `tests/helpers/arr_test.php`
- [x] `tests/helpers/filter_test.php` — every type returns false on invalid input

### 3.4 Validation
- [x] `src/Validation/Validate.php` — `make/check/ok/errors/validated`
- [x] `src/Validation/Validate.php` — `Validate::rule()` for custom rules
- [x] `tests/validation/validate_test.php` — required, type rules, custom rule, validated() whitelist

---

## Phase 4 — Events, Session, Realtime, Queue

- [x] `src/Events/Event.php` — on/emit/once, priority, typed event classes
- [x] `src/Session/Session.php` — Redis + File drivers, `get/set/has/delete/flush/flash/regenerate`
- [x] `src/Realtime/Sse.php` — send/ping/close, Content-Type: text/event-stream, ob_end_clean
- [x] `src/Realtime/Datastar.php` — `merge/remove/signal/script` SSE helpers
- [x] `src/Queue/Job.php` — interface: `handle(): void`, `failed(\Throwable): void`
- [x] `src/Queue/Queue.php` — Redis LPUSH/BRPOP, `push(job, delay, tries)`
- [x] `src/Queue/Worker.php` — `work()` loop, retry logic, calls `failed()` after exhausted
- [x] `src/Events/Event.php` — `emitAsync()` integration with queue (optional, requires queue)

---

## Phase 5 — CLI

- [x] `src/Cli/Cli.php` — `info/success/warn/error/muted/line` with ANSI colors
- [x] `src/Cli/Cli.php` — `progressBar/table/ask/choice/confirm`
- [x] `src/Cli/Command.php` — base class, `$args/$flags`, `handle(): int`
- [x] `bin/skim` — entry point for `php skim <command>`
- [x] `src/cli/commands/ServeCommand.php` — PHP dev server + Vite in parallel via `proc_open`
- [x] `src/cli/commands/MigrateCommand.php` — delegates to `migrator`
- [x] `src/cli/commands/QueueCommand.php` — `queue:work/status/flush`
- [x] `src/cli/commands/CacheCommand.php` — `cache:clear/flush`
- [x] `src/cli/commands/IdeCommand.php` — reads schema cache, writes `generated/.ide-helper.php`

---

## Phase 6 — Optional modules (install via `php skim module:add`)

- [x] `src/Http/Client.php` — native streams HTTP client, `get/post/put/delete/patch`
- [x] `src/Http/FakeClient.php` — for tests, record/replay HTTP calls
- [x] `src/Http/HttpResponse.php` — immutable value object
- [x] `src/Log/Log.php` — PSR-3 facade, file + null handlers
- [x] `src/i18n/i18n.php` — PHP array files, `t()` helper, pluralization, locale switching
- [x] `src/Websocket/Handler.php` — interface: `onOpen/onMessage/onClose/onError`
- [x] `src/Websocket/Connection.php` — room support, `send/close/broadcast/join/leave`
- [x] `src/Db/MerryModel.php` — extends Model, `hasMany/belongsTo/hasOne/manyToMany`
- [x] `src/Db/MerryModel.php` — `with()/load()` eager/lazy loading, `attach/detach/sync`
- [x] `src/Assets/Assets.php` — Vite manifest reader, `asset()` helper (dev proxy vs prod hash)

---

## Phase 7 — DX + CI

- [x] `AGENT.md` — root agent contract
- [x] `src/*/AGENT.md` — per-module agent contracts (core, db, cache, view, helpers, events, queue, cli)
- [x] `tests/AGENT.md` — testing conventions
- [x] `generated/` — empty dir with `.gitkeep`
- [x] `.github/workflows/ci.yml` — PHP 8.4/8.5 matrix, Redis service, coverage gate 80%
- [x] Interactive installer — `composer create-project`, module wizard, config generation
