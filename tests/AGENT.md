# tests/ — Agent Contract

## Test framework
Pest PHP (over PHPUnit). snake_case test descriptions. No test classes unless
dataset sharing requires it.

## File structure mirrors src/
tests/
  db/
    query_builder_test.php
    model_test.php
  view/
    view_test.php
  cache/
    cache_test.php
  helpers/
    arr_test.php
    filter_test.php
  validation/
    validate_test.php
  core/
    router_test.php
    request_test.php
    config_test.php
    env_test.php
    middleware_test.php

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
      find() null return, findOrFail() exception, transaction rollback
- view: full render, fragment extraction, missing fragment exception,
        layout slot injection, e() escaping
- cache: set/get/delete, ttl expiry, fallback driver on failure, flush('prefix:')
- helpers: every filter type returns false on invalid input,
           Arr::mapBy key collision behaviour
- validation: required rule, type rules, custom rule registration,
              validated() returns only declared fields
- routing: static route match, dynamic route with type token,
           404 on no match, middleware execution order

## Running tests
./vendor/bin/pest                         # all tests
./vendor/bin/pest tests/db/               # single module
./vendor/bin/pest --filter="where clause" # by description
./vendor/bin/pest --coverage              # with coverage report
