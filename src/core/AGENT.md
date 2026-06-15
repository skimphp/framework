# src/core — Agent Contract

## What this module does
Container (app), config loader, env parser, HTTP router (fast-route wrapper),
request/response abstractions, middleware pipeline.

## app container — critical behaviours
- app::instance() returns the singleton — never construct app directly
- app::test_instance() returns a fresh isolated container for tests
- SYS scope: write-once after boot — throws on duplicate in production
- APP scope: reads config/*.php via config::get — read-only after boot
- USER scope: mutable, per-request

## router — critical behaviours
- @param token syntax: @id → {id:[^/]+}, @id:int → {id:\d+}, @slug:str → {id:[a-zA-Z0-9\-]+}
- dispatch() returns: array (match), null (404), false (405)
- Named routes via route_entry::name() → route('user.show', ['id' => 5])
- Groups stack additively: prefixes concat, middleware merges
- CLI commands dispatched separately via dispatch_command()
- map() is a Slim/Laravel-style alias for add() — accepts a single method
  string or an array; prefer get/post/put/patch/delete for single methods
  and any() for all five

## request — critical behaviours
- from_globals() reads superglobals — only called in app::run(), never elsewhere
- make() is the test factory — pass explicit arrays
- is_htmx() checks HX-Request header
- is_datastar() checks datastar-request header
- json() parses body only when Content-Type is application/json
- route params injected by app::run() via set_route_params()

## response — critical behaviours
- never echo or die in controllers — always return response
- status() is fluent — must chain json/view/redirect after it
- stream() disables output buffering, executes callback immediately, does NOT buffer
- fragment() renders only the named @fragment block — smaller payload than view()
- smart_view() auto-selects full vs fragment by checking HX-Target / datastar-target

## middleware pipeline — critical behaviours
- execution order: global → group → route (first registered = first run)
- short-circuit: return without calling $next — controller never runs
- cors must be first global middleware — OPTIONS preflight must not reach auth

## common mistakes to avoid
- calling app::instance() in tests — use app::test_instance() instead
- accessing $_GET/$_POST directly — always use request methods
- calling response::send() in middleware — return the response object instead
- forgetting to call send() at the end of app::run()
