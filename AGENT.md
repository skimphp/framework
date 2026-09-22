# SKIM Framework — Agent Root

## Read this first
This is SKIM — a PHP 8.5+ micro-framework. Standard PHP naming:
PascalCase for class-likes, camelCase for methods/properties/variables.
Braces same line. Types always declared. No YAML. No Guzzle. No Twig.
Property hooks and asymmetric visibility are used throughout — read PHP 8.4/8.5 sections first.

## Module map — which AGENT.md to read for what task
| Task                                    | Read                    |
|-----------------------------------------|-------------------------|
| routing, request, response, middleware  | src/Core/AGENT.md       |
| SQL queries, PDO, active record, ORM    | src/Db/AGENT.md         |
| PHP templates, fragments, layouts       | src/View/AGENT.md       |
| redis, file cache, cache tags           | src/Cache/AGENT.md      |
| arr, filter, str helpers                | src/Helpers/AGENT.md    |
| form validation, rules                  | src/Validation/AGENT.md |
| events, listeners                       | src/Events/AGENT.md     |
| background jobs, workers                | src/Queue/AGENT.md      |
| SSE, datastar, htmx streaming           | src/Realtime/AGENT.md   |
| CLI commands, progress, interactive     | src/Cli/AGENT.md        |
| extension discovery, install, lifecycle | src/Ext/AGENT.md        |
| HTTP client, external API calls         | src/Http/AGENT.md       |
| WebSocket handlers, rooms               | src/Websocket/AGENT.md  |
| FrankenPHP worker mode, leak detection  | src/Worker/AGENT.md     |
| writing or running tests                | tests/AGENT.md          |

## Naming rules (post-migration)
| Symbol      | Convention       |
|-------------|------------------|
| Namespace   | PascalCase       |
| Class       | PascalCase       |
| Interface   | PascalCase       |
| Trait       | PascalCase       |
| Enum        | PascalCase       |
| Enum case   | PascalCase       |
| Method      | camelCase        |
| Property    | camelCase        |
| Variable    | camelCase        |
| Constant    | UPPER_SNAKE_CASE |

snake_case survives ONLY in external contracts — never rename:
DB tables/columns, env vars, config keys, URLs, view paths, validation DSL, cache keys.

## Global rules (enforced in every module)
- filenames match class names — PSR-4 (`src/Db/QueryBuilder.php`)
- braces same line: `if ($x) {` — never `if ($x)\n{`
- types always declared — no untyped signatures ever
- config is PHP arrays only — no YAML, no INI
- null means "not found / not set" — never throw when null is valid
- throw named exceptions, never generic \Exception
- every public method has a PHPDoc @ai-contract block
- every new feature needs a Pest test before merge
- use property hooks for model normalization/computed fields — not custom setters
- use asymmetric visibility for system fields (id, createdAt) — not $guarded alone
- use pipe |> for multi-step data transformations — not nested calls
- mark fluent builder methods with #[\NoDiscard] — caller must capture the return

## Running the application

### PHP-FPM / dev server (docker-compose.yml)
```bash
docker compose up app        # http://localhost:8080 — built-in PHP server with 8 workers
```
Entry point: `public/index.php` — one process per request.

### FrankenPHP worker mode (docker-compose.frankenphp.yml)
```bash
docker compose -f docker-compose.yml -f docker-compose.frankenphp.yml up frankenphp
# http://localhost:8081 — single long-lived process, boot once, handle many requests
```
Entry point: `public/worker.php` — calls `frankenphp_handle_request()` in a loop.
Requires the `skim_framework_default` network created by the base compose file.

### CLI / one-off commands
```bash
docker compose exec app php bin/skim list --agent  # list with commands (--agent mode without less graphics)
docker compose exec app php bin/skim migrate:up --agent    # example command
```

### Tests
```bash
docker compose exec app ./vendor/bin/pest                         # all tests
docker compose exec app ./vendor/bin/pest tests/db/               # single module
docker compose exec app ./vendor/bin/pest --filter="where clause" # by description
docker compose exec app ./vendor/bin/pest --coverage              # with coverage
```

## What NOT to do
- never add a dependency without updating composer.json AND the relevant AGENT.md
- never bypass Cache::remember() — always cache expensive queries
- never write raw SQL with user input interpolated — always :named params
- never add a new public method without a PHPDoc @ai-contract block
- never silently swallow exceptions — log then rethrow or return typed error
