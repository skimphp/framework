# src/events — Agent Contract

## What this module does
Synchronous in-process event bus. Listeners run before the response returns.
emitAsync() offloads to the queue for side effects that should not block.

## Critical behaviours
- Event::on() registration is global per-process — register in boot/service providers, not controllers
- Higher priority = runs first (10 > 0 > -5)
- Typed event classes (recommended): `class UserRegistered { public function __construct(public readonly int $user_id) {} }`
  — key = get_class($event), IDE-navigable, refactor-safe
- String events ('user.created'): simple cases, no type safety
- Event::once() auto-removes after first emit — for warmup/boot hooks
- emitAsync() requires skim/queue — throws RuntimeException if not installed
- Event::off() in tests — always reset in beforeEach to prevent cross-test listener leak

## Common mistakes
- Registering listeners inside a controller or route handler — runs on every request, stacks up
- Throwing inside a listener — stops remaining listeners from running; catch inside listeners
- Using emitAsync() for actions that must complete before the response (payment confirmation)
