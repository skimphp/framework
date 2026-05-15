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
- [ ] `.env.example` — все переменные с docker defaults (DB_HOST=mysql, REDIS_HOST=redis)
- [ ] Verify: `make build && make up && make shell` → bash в контейнере
- [ ] Verify: `make test` → Pest запускается (0 tests, no errors)

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
- [x] `src/core/app.php` — PSR-11, singleton `instance()`, three scopes SYS/APP/USER
- [x] `src/core/config.php` — loads `config/*.php`, dot-notation `config('db.default.host')`
- [x] `src/core/env.php` — parses `.env` once, `env('KEY', default)`, no vlucas/phpdotenv
- [x] `src/core/helpers.php` — global `config()`, `env()`, `route()`, `storage_path()`, `e()`
- [x] `tests/core/config_test.php`
- [x] `tests/core/env_test.php`

### 1.3 Core: router
- [x] `src/core/router.php` — wraps nikic/fast-route, `get/post/put/patch/delete/any`
- [x] `src/core/router.php` — route groups with middleware, named routes + `route()` helper
- [x] `src/core/router.php` — `@param` tokens: `@id:int`, `@slug:str`, `@any`
- [x] `src/core/router.php` — CLI `command()` routes
- [x] `tests/core/router_test.php` — static match, dynamic match, 404, named reverse

### 1.4 Core: request
- [x] `src/core/request.php` — `get/post/input/json/file/header/ip/method/path/url`
- [x] `src/core/request.php` — `is_htmx/is_datastar/is_json/is_ajax/is_cli`
- [x] `src/core/request.php` — injected via container, not instantiated manually
- [x] `tests/core/request_test.php`

### 1.5 Core: response
- [x] `src/core/response.php` — `json/view/redirect/download`
- [x] `src/core/response.php` — `stream(callable)` — disables output buffering, SSE
- [x] `src/core/response.php` — `fragment(template, data, name)` — extracts @fragment block
- [x] `src/core/response.php` — `smart_view()` — auto full vs fragment by request headers
- [x] `src/core/response.php` — `status(int)` fluent, `back()` redirect
- [x] `tests/core/response_test.php`

### 1.6 Core: middleware pipeline
- [x] `src/core/middleware.php` — interface: `handle(request, response, callable $next): mixed`
- [x] `src/core/pipeline.php` — builds chain: global → group → route, short-circuit on return
- [x] `src/middleware/cors.php` — CORS headers, preflight OPTIONS handler
- [x] `src/middleware/rate_limit.php` — Redis-backed sliding window
- [x] `tests/core/middleware_test.php` — execution order, short-circuit

---

## Phase 2 — Database

### 2.1 PDO wrapper + connection manager
- [x] `src/db/db.php` — connection pool, `query/val/row/all/transaction`, profiler hooks
- [x] `src/db/query_builder.php` — internal: `build/build_where/build_set/build_values/interpolate`
- [x] `src/db/null_marker.php` — sentinel for `db::null()` (explicit SQL NULL in `%set%`)
- [x] `tests/db/query_builder_test.php` — %where% removal, %set% null skip, or/and nesting, debug interpolation, key-length sort

### 2.2 Active record model
- [x] `src/db/model.php` — base class, `find/find_or_fail/where/all/count/raw/paginate/save/delete`
- [ ] `src/db/model.php` — schema fetch (DESCRIBE/information_schema → cache)
- [x] `src/db/model.php` — dirty tracking via attribute __set()
- [x] `src/db/model.php` — `save()` uses dirty tracking → UPDATE only changed columns
- [x] `src/db/model.php` — `$guarded` excludes fields from mass assignment
- [x] `tests/db/model_test.php` — find null, find_or_fail throw, save INSERT vs UPDATE, dirty tracking

### 2.3 Migrations
- [x] `src/db/migration.php` — abstract base: `up(): string`, `down(): string`
- [x] `src/db/migrator.php` — tracks batches in `_migrations` table, run/rollback/fresh/status
- [x] `migrations/` — empty dir with `.gitkeep`
- [ ] `tests/db/migrator_test.php` — SQLite :memory:, run/down/idempotent

---

## Phase 2.5 — Dev module (skim/dev)

- [x] `src/dev/profiler.php` — static collector: `db/cache/view/log/summary/events/reset`, no-op when APP_DEBUG=false
- [ ] `src/dev/toolbar.php` — HTML debug bar renderer, tabs: DB/Cache/Session/Cookies/Files/Templates/Logs/Request
- [x] `src/dev/error_page.php` — Throwable handler: exception + code context + stack trace + request dump
- [x] Wire profiler hooks into `src/db/db.php` (already done), `src/cache/cache.php`, `src/view/view.php`
- [ ] Toolbar middleware — appends HTML before `</body>` for text/html responses only
- [ ] `tests/dev/profiler_test.php` — no-op in prod, records correctly in debug, reset() clears buffer

---

## Phase 3 — Cache + View + Helpers

### 3.1 Cache
- [x] `src/cache/driver.php` — PSR-16 interface
- [x] `src/cache/redis_driver.php` — Redis, tags support
- [x] `src/cache/file_driver.php` — filesystem, TTL via serialized expiry
- [x] `src/cache/array_driver.php` — in-memory, for tests only
- [x] `src/cache/cache.php` — static facade, `set/get/has/delete/flush/flush_all/remember/tags`
- [x] `src/cache/cache.php` — fallback: if Redis fails → file driver
- [x] `tests/cache/cache_test.php` — set/get/delete, TTL expiry, fallback, flush('prefix:')

### 3.2 View
- [x] `src/view/template.php` — template context (`$this` inside .php views), `include/layout/start/end/slot`
- [x] `src/view/view.php` — `render/render_fragment`, shared data via `view::share()`
- [x] `src/view/view.php` — fragment extraction: parse `<!-- @fragment name -->...<!-- @end -->`
- [x] `tests/view/view_test.php` — full render, fragment extraction, missing fragment throws, e() escaping

### 3.3 Helpers
- [x] `src/helpers/arr.php` — `find/find_all/map_by/map_col/pluck/filter_by/first/last/map_nested/map_keys/normalize100/weighted_pick/to_string`
- [x] `src/helpers/filter.php` — `int/float/bool/date/time/ip/domain/email/url/username/slug/regex/in` + array variants
- [x] `src/helpers/str.php` — `slug/excerpt/random/uuid/contains/starts_with/ends_with/to_snake/to_camel`
- [x] `tests/helpers/arr_test.php`
- [x] `tests/helpers/filter_test.php` — every type returns false on invalid input

### 3.4 Validation
- [x] `src/validation/validate.php` — `make/check/ok/errors/validated`
- [x] `src/validation/validate.php` — `validate::rule()` for custom rules
- [x] `tests/validation/validate_test.php` — required, type rules, custom rule, validated() whitelist

---

## Phase 4 — Events, Session, Realtime, Queue

- [ ] `src/events/event.php` — on/emit/once, priority, typed event classes
- [ ] `src/session/session.php` — Redis + File drivers, `get/set/has/delete/flush/flash/regenerate`
- [x] `src/realtime/sse.php` — send/ping/close, Content-Type: text/event-stream, ob_end_clean
- [ ] `src/realtime/datastar.php` — `merge/remove/signal/script` SSE helpers
- [ ] `src/queue/job.php` — interface: `handle(): void`, `failed(\Throwable): void`
- [ ] `src/queue/queue.php` — Redis LPUSH/BRPOP, `push(job, delay, tries)`
- [ ] `src/queue/worker.php` — `work()` loop, retry logic, calls `failed()` after exhausted
- [ ] `src/events/event.php` — `emit_async()` integration with queue (optional, requires queue)

---

## Phase 5 — CLI

- [x] `src/cli/cli.php` — `info/success/warn/error/muted/line` with ANSI colors
- [x] `src/cli/cli.php` — `progress_bar/table/ask/choice/confirm`
- [x] `src/cli/command.php` — base class, `$args/$flags`, `handle(): int`
- [x] `bin/skim` — entry point for `php skim <command>`
- [x] `src/cli/commands/serve_command.php` — PHP dev server + Vite in parallel via `proc_open`
- [x] `src/cli/commands/migrate_command.php` — delegates to `migrator`
- [x] `src/cli/commands/queue_command.php` — `queue:work/status/flush`
- [x] `src/cli/commands/cache_command.php` — `cache:clear/flush`
- [x] `src/cli/commands/ide_command.php` — reads schema cache, writes `generated/.ide-helper.php`

---

## Phase 6 — Optional modules (install via `php skim module:add`)

- [x] `src/http/client.php` — native streams HTTP client, `get/post/put/delete/patch`
- [x] `src/http/fake_client.php` — for tests, record/replay HTTP calls
- [x] `src/http/http_response.php` — immutable value object
- [x] `src/log/log.php` — PSR-3 facade, file + null handlers
- [x] `src/i18n/i18n.php` — PHP array files, `t()` helper, pluralization, locale switching
- [x] `src/websocket/handler.php` — interface: `on_open/on_message/on_close/on_error`
- [x] `src/websocket/connection.php` — room support, `send/close/broadcast/join/leave`
- [x] `src/db/merry_model.php` — extends model, `has_many/belongs_to/has_one/many_to_many`
- [x] `src/db/merry_model.php` — `with()/load()` eager/lazy loading, `attach/detach/sync`
- [x] `src/assets/assets.php` — Vite manifest reader, `asset()` helper (dev proxy vs prod hash)

---

## Phase 7 — DX + CI

- [x] `AGENT.md` — root agent contract
- [x] `src/*/AGENT.md` — per-module agent contracts (core, db, cache, view, helpers, events, queue, cli)
- [x] `tests/AGENT.md` — testing conventions
- [x] `generated/` — empty dir with `.gitkeep`
- [x] `.github/workflows/ci.yml` — PHP 8.4/8.5 matrix, Redis service, coverage gate 80%
- [ ] Interactive installer — `composer create-project`, module wizard, config generation
