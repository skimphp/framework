# src/view — Agent Contract

## What this module does
Pure PHP template renderer with layout slots and fragment extraction.
Response::view() and Response::fragment() are the primary entry points.

## Critical behaviours
- Always call e() on user-supplied data — skipping is XSS vulnerability
- Fragment syntax: <!-- @fragment name --> ... <!-- @end --> in .php template file
- Response::smartView() auto-selects full vs fragment by HX-Target / datastar-target header
- View::share() injects into ALL templates for this request — use for current_user, app_name
- Layout order: child runs first (capturing slots), then layout renders and calls $this->slot()
- Missing template → view_exception (never silently returns empty string)
- Missing fragment → view_exception (check fragment name spelling exactly)
- Profiler::view() called after every render — appears in toolbar views tab

## Template context ($this inside .php files)
- $this->include('partial') — includes another template with merged data
- $this->layout('layouts/app') — wraps output in layout file
- $this->start('content') / $this->end() — captures a named slot
- $this->slot('content') — inside layout: outputs captured slot

## Common mistakes
- Forgetting e() around output: <?= $user->name ?> → <?= e($user->name) ?>
- Using View::render() directly in controllers instead of Response::view()
- Setting view path after first render call (path is resolved lazily on first render)
