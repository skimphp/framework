<?php declare(strict_types=1);

namespace Skim\Testing;

/**
 * In-memory auth double for tests that require an authenticated user. #AI:class
 *
 * Use when a controller or middleware checks auth state during testing.
 * Always returns check()=true and guest()=false. The user object is passed
 * through as-is — no database lookup.
 *
 * Example:
 *   $fake = new AuthFake(User::find(1));
 *   $app->bind('auth', fn() => $fake);
 *
 * Testing: This class IS the test double — no setup/teardown needed.
 *
 * #AI:class
 */
class AuthFake {
    public function __construct(private readonly object $user) {}

    /**
     * Returns the injected user object. #AI:user
     */
    public function user(): object  { return $this->user; }

    /**
     * Always returns true — simulates authenticated state. #AI:check
     */
    public function check(): bool   { return true; }

    /**
     * Always returns false — simulates authenticated state. #AI:guest
     */
    public function guest(): bool   { return false; }

    /**
     * Returns the user's id property or null. #AI:id
     */
    public function id(): mixed     { return $this->user->id ?? null; }
}

#AI:class
#AI symbol: Skim\Testing\AuthFake
#AI source_path: src/Testing/AuthFake.php
#AI title: AuthFake
#AI description: In-memory auth double that always reports authenticated with a given user object.
#AI role: test double (auth)
#AI layer: testing
#AI badges: [testing; fake; auth; double]
#AI intro: `AuthFake` is a minimal test double for the auth service. It always reports the user as authenticated and returns the injected user object directly.
#AI lifecycle: instantiated per-test, bound into the container via App::bind()
#AI fallback: n/a — test-only class
#AI test_seam: bind into container via $app->bind('auth', fn() => new AuthFake($user))
#AI invariants: [check() always returns true; guest() always returns false; user() returns the injected object]
#AI core_behaviors: [Provides a deterministic authenticated state without database or session dependencies]
#AI notes: The user object must have an `id` property for id() to return non-null.
#AI owns: injected user object reference
#AI entry_points: [user; check; guest; id]
#AI config_reads: []
#AI non_goals: [Does not validate credentials; Does not interact with session or database]
#AI side_effects: []
#AI flow: test -> AuthFake::check() -> true; AuthFake::user() -> injected object
#AI lifecycle_steps: [new AuthFake($user); -> bind to container; -> controller calls Auth::check() -> true]
#AI section_order: [Auth API; Architecture]
#AI architectural_notes: Intentionally minimal — only the methods the auth middleware and controllers call.

#AI:user
#AI group: Auth API
#AI frequency: high
#AI signature: public function user(): object
#AI contract: Returns the injected user object as-is.
#AI return_detail: {type: object | desc: The user object passed to the constructor.}

#AI:check
#AI group: Auth API
#AI frequency: high
#AI signature: public function check(): bool
#AI contract: Always returns true to simulate authenticated state.
#AI return_detail: {type: bool | desc: Always true.}

#AI:guest
#AI group: Auth API
#AI frequency: medium
#AI signature: public function guest(): bool
#AI contract: Always returns false to simulate authenticated state.
#AI return_detail: {type: bool | desc: Always false.}

#AI:id
#AI group: Auth API
#AI frequency: medium
#AI signature: public function id(): mixed
#AI contract: Returns the user's id property, or null if not set.
#AI return_detail: {type: mixed | desc: The user ID or null.}
