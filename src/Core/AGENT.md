# src/core — Agent Contract

## What this module does
Container (app), config loader, env parser, HTTP router (fast-route wrapper),
request/response abstractions, middleware pipeline.

## app container — critical behaviours
- App::instance() returns the singleton — never construct app directly
- App::testInstance() returns a fresh isolated container for tests
- SYS scope: write-once after boot — throws on duplicate in production
- APP scope: reads config/*.php via Config::get — read-only after boot
- USER scope: mutable, per-request
- bind() is singleton by default; use bindRequest()/bindTransient() or pass a
  lifetime; config('app.strict_di')=true makes an explicit lifetime mandatory.

## router — critical behaviours
- @param token syntax: @id → {id:[^/]+}, @id:int → {id:\d+}, @slug:str → {id:[a-zA-Z0-9\-]+}
- dispatch() returns: array (match), null (404), false (405)
- Named routes via RouteEntry::name() → route('user.show', ['id' => 5])
- Groups stack additively: prefixes concat, middleware merges
- CLI commands dispatched separately via dispatchCommand()
- map() is a Slim/Laravel-style alias for add() — accepts a single method
  string or an array; prefer get/post/put/patch/delete for single methods
  and any() for all five

## request — critical behaviours
- fromGlobals() reads superglobals — only called in App::run(), never elsewhere
- make() is the test factory — pass explicit arrays
- isHypermedia() detects hypermedia libraries via config/realtime.php headers
- json() parses body only when Content-Type is application/json
- route params injected by App::run() via setRouteParams()

## response — critical behaviours
- never echo or die in controllers — always return response
- status() is fluent — must chain json/view/redirect after it
- stream() disables output buffering, executes callback immediately, does NOT buffer
- fragment() renders only the named @fragment block — smaller payload than view()
- smartView() auto-selects full vs fragment by checking HX-Target / datastar-target

## middleware pipeline — critical behaviours
- execution order: global → group → route (first registered = first run)
- short-circuit: return without calling $next — controller never runs
- cors must be first global middleware — OPTIONS preflight must not reach auth

## common mistakes to avoid
- calling App::instance() in tests — use App::testInstance() instead
- accessing $_GET/$_POST directly — always use request methods
- calling Response::send() in middleware — return the response object instead
- forgetting to call send() at the end of App::run()
