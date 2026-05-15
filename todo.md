# SKIM Framework — Implementation TODO

> **Current focus: Phase 1**
> Convention: snake_case everywhere, braces same line, strict types always, no YAML.
> Write Pest tests alongside each file. Use SQLite :memory: for DB tests, array driver for cache.

---

## Phase 1 — Foundation (start here)

### 1.1 Project scaffold
- [ ] `composer.json` — PSR-4 autoload `skim\\` → `src/`, `app\\` → `app/`, require nikic/fast-route
- [ ] `public/index.php` — single entry point, requires autoload, creates app, runs
- [ ] `config/app.php` — name, debug, env, key, timezone
- [ ] `config/db.php` — default + optional analytics connection
- [ ] `config/cache.php` — driver, redis, file, fallback
- [ ] `.env.example` — all required env vars
- [ ] `AGENT.md` — root agent contract (copy from framework.md spec)

### 1.2 Core: container + config + env
- [ ] `src/core/app.php` — PSR-11, singleton `instance()`, three scopes SYS/APP/USER
- [ ] `src/core/config.php` — loads `config/*.php`, dot-notation `config('db.default.host')`
- [ ] `src/core/env.php` — parses `.env` once, `env('KEY', default)`, no vlucas/phpdotenv
- [ ] `src/core/helpers.php` — global `config()`, `env()`, `route()`, `storage_path()`, `e()`
- [ ] `tests/core/config_test.php`
- [ ] `tests/core/env_test.php`

### 1.3 Core: router
- [ ] `src/core/router.php` — wraps nikic/fast-route, `get/post/put/patch/delete/any`
- [ ] `src/core/router.php` — route groups with middleware, named routes + `route()` helper
- [ ] `src/core/router.php` — `@param` tokens: `@id:int`, `@slug:str`, `@any`
- [ ] `src/core/router.php` — CLI `command()` routes
- [ ] `tests/core/router_test.php` — static match, dynamic match, 404, named reverse

### 1.4 Core: request
- [ ] `src/core/request.php` — `get/post/input/json/file/header/ip/method/path/url`
- [ ] `src/core/request.php` — `is_htmx/is_datastar/is_json/is_ajax/is_cli`
- [ ] `src/core/request.php` — injected via container, not instantiated manually
- [ ] `tests/core/request_test.php`

### 1.5 Core: response
- [ ] `src/core/response.php` — `json/view/redirect/download`
- [ ] `src/core/response.php` — `stream(callable)` — disables output buffering, SSE
- [ ] `src/core/response.php` — `fragment(template, data, name)` — extracts @fragment block
- [ ] `src/core/response.php` — `smart_view()` — auto full vs fragment by request headers
- [ ] `src/core/response.php` — `status(int)` fluent, `back()` redirect
- [ ] `tests/core/response_test.php`

### 1.6 Core: middleware pipeline
- [ ] `src/core/middleware.php` — interface: `handle(request, response, callable $next): mixed`
- [ ] `src/core/pipeline.php` — builds chain: global → group → route, short-circuit on return
- [ ] `src/middleware/cors.php` — CORS headers, preflight OPTIONS handler
- [ ] `src/middleware/rate_limit.php` — Redis-backed sliding window
- [ ] `tests/core/middleware_test.php` — execution order, short-circuit

---

## Phase 2 — Database

### 2.1 PDO wrapper + connection manager
- [x] `src/db/db.php` — connection pool, `query/val/row/all/transaction`, profiler hooks
- [x] `src/db/query_builder.php` — internal: `build/build_where/build_set/build_values/interpolate`
- [x] `src/db/null_marker.php` — sentinel for `db::null()` (explicit SQL NULL in `%set%`)
- [ ] `tests/db/query_builder_test.php` — %where% removal, %set% null skip, or/and nesting, debug interpolation, key-length sort

### 2.2 Active record model
- [ ] `src/db/model.php` — base class, `find/find_or_fail/where/all/count/raw/paginate/save/delete`
- [ ] `src/db/model.php` — schema fetch (DESCRIBE/information_schema → cache)
- [ ] `src/db/model.php` — property hooks: email normalize, password hash, dirty tracking
- [ ] `src/db/model.php` — asymmetric visibility: `public private(set) int $id`
- [ ] `src/db/model.php` — `save()` uses dirty tracking → UPDATE only changed columns
- [ ] `src/db/model.php` — `$guarded` excludes fields from mass assignment
- [ ] `tests/db/model_test.php` — find null, find_or_fail throw, save INSERT vs UPDATE, dirty tracking

### 2.3 Migrations
- [ ] `src/db/migration.php` — abstract base: `up(): string`, `down(): string`
- [ ] `src/db/migrator.php` — tracks batches in `_migrations` table, run/rollback/fresh/status
- [ ] `migrations/` — empty dir with `.gitkeep`
- [ ] `tests/db/migrator_test.php` — SQLite :memory:, run/down/idempotent

---

## Phase 2.5 — Dev module (skim/dev)

- [ ] `src/dev/profiler.php` — static collector: `db/cache/view/log/summary/events/reset`, no-op when APP_DEBUG=false
- [ ] `src/dev/toolbar.php` — HTML debug bar renderer, tabs: DB/Cache/Session/Cookies/Files/Templates/Logs/Request
- [ ] `src/dev/error_page.php` — Throwable handler: exception + code context + stack trace + request dump
- [ ] Wire profiler hooks into `src/db/db.php` (already done), `src/cache/cache.php`, `src/view/view.php`, `src/log/log.php`
- [ ] Toolbar middleware — appends HTML before `</body>` for text/html responses only
- [ ] `tests/dev/profiler_test.php` — no-op in prod, records correctly in debug, reset() clears buffer

---

## Phase 3 — Cache + View + Helpers

### 3.1 Cache
- [ ] `src/cache/driver.php` — PSR-16 interface
- [ ] `src/cache/redis_driver.php` — Redis, tags support
- [ ] `src/cache/file_driver.php` — filesystem, TTL via file mtime
- [ ] `src/cache/array_driver.php` — in-memory, for tests only
- [ ] `src/cache/cache.php` — static facade, `set/get/has/delete/flush/flush_all/remember/tags`
- [ ] `src/cache/cache.php` — fallback: if Redis fails → file driver, log warning
- [ ] `tests/cache/cache_test.php` — set/get/delete, TTL expiry, fallback, flush('prefix:')

### 3.2 View
- [ ] `src/view/template.php` — template context (`$this` inside .php views), `include/layout/start/end/slot`
- [ ] `src/view/view.php` — `render/render_fragment`, shared data via `view::share()`
- [ ] `src/view/view.php` — fragment extraction: parse `<!-- @fragment name -->...<!-- @end -->`
- [ ] `tests/view/view_test.php` — full render, fragment extraction, missing fragment throws, e() escaping

### 3.3 Helpers
- [ ] `src/helpers/arr.php` — `find/find_all/map_by/map_col/pluck/filter_by/first/last/map_nested/map_keys/normalize100/weighted_pick/to_string`
- [ ] `src/helpers/filter.php` — `int/float/bool/date/time/ip/domain/email/url/username/slug/regex/in` + array variants
- [ ] `src/helpers/str.php` — `slug/excerpt/random/uuid/contains/starts_with/ends_with/to_snake/to_camel`
- [ ] `tests/helpers/arr_test.php`
- [ ] `tests/helpers/filter_test.php` — every type returns false on invalid input

### 3.4 Validation
- [ ] `src/validation/validate.php` — `make/check/ok/errors/validated`
- [ ] `src/validation/rules/` — `required/email/min/max/min_len/max_len/int/float/bool/in/same/regex/unique/url`
- [ ] `src/validation/validate.php` — `validate::rule()` for custom rules
- [ ] `tests/validation/validate_test.php` — required, type rules, custom rule, validated() whitelist

---

## Phase 4 — Events, Session, Realtime, Queue

- [ ] `src/events/event.php` — on/emit/once, priority, typed event classes
- [ ] `src/session/session.php` — Redis + File drivers, `get/set/has/delete/flush/flash/regenerate`
- [ ] `src/realtime/sse.php` — send/ping/close, Content-Type: text/event-stream, ob_end_clean
- [ ] `src/realtime/datastar.php` — `merge/remove/signal/script` SSE helpers
- [ ] `src/queue/job.php` — interface: `handle(): void`, `failed(\Throwable): void`
- [ ] `src/queue/queue.php` — Redis LPUSH/BRPOP, `push(job, delay, tries)`
- [ ] `src/queue/worker.php` — `work()` loop, retry logic, calls `failed()` after exhausted
- [ ] `src/events/event.php` — `emit_async()` integration with queue (optional, requires queue)

---

## Phase 5 — CLI

- [ ] `src/cli/cli.php` — `info/success/warn/error/muted/line` with ANSI colors
- [ ] `src/cli/cli.php` — `progress_bar/table/ask/choice/confirm`
- [ ] `src/cli/command.php` — base class, `$args/$flags`, `handle(): int`
- [ ] `bin/skim` — entry point for `php skim <command>`
- [ ] `src/cli/commands/serve_command.php` — PHP dev server + Vite in parallel via `proc_open`
- [ ] `src/cli/commands/migrate_command.php` — delegates to `migrator`
- [ ] `src/cli/commands/queue_command.php` — `queue:work/status/flush`
- [ ] `src/cli/commands/cache_command.php` — `cache:clear/flush`
- [ ] `src/cli/commands/ide_command.php` — reads schema cache, writes `generated/.ide-helper.php`

---

## Phase 6 — Optional modules (install via `php skim module:add`)

- [ ] `src/http/client.php` — symfony/http-client thin wrapper, `get/post/put/delete/patch`
- [ ] `src/http/fake_client.php` — for tests, record/replay HTTP calls
- [ ] `src/log/log.php` — Monolog PSR-3 wrapper, `debug/info/warning/error`
- [ ] `src/i18n/i18n.php` — symfony/translation, PHP array files, `t()` helper
- [ ] `src/websocket/handler.php` — interface: `on_open/on_message/on_close/on_error`
- [ ] `src/websocket/connection.php` — wraps amphp connection, `id/send/close/room`
- [ ] `src/db/merry_model.php` — extends model, `has_many/belongs_to/has_one/many_to_many`
- [ ] `src/db/merry_model.php` — `with()/load()` eager/lazy loading, `attach/detach/sync`
- [ ] `src/assets/assets.php` — Vite manifest reader, `asset()` helper (dev proxy vs prod hash)

---

## Phase 7 — DX + CI

- [ ] `AGENT.md` — root (copy final spec from framework.md)
- [ ] `src/*/AGENT.md` — one per module
- [ ] `tests/AGENT.md` — testing conventions
- [ ] `generated/` — empty dir with `.gitkeep`
- [ ] `.github/workflows/ci.yml` — PHP 8.4/8.5 matrix, Redis service, coverage gate 80%
- [ ] Interactive installer — `composer create-project`, module wizard, config generation
