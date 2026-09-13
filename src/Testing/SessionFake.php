<?php declare(strict_types=1);

namespace Skim\Testing;

/**
 * In-memory session double for tests — no cookies, no headers, no persistence. #AI:class
 *
 * Use when a test needs session state without touching PHP's native session
 * or a real Redis connection. Implements the subset of SessionDriver that
 * PendingRequest binds into the container.
 *
 * Example:
 *   $fake = new SessionFake();
 *   $fake->set('user_id', 42);
 *   $app->bind('session', fn() => $fake);
 *
 * Testing: This class IS the test double — instantiate directly, no teardown needed.
 *
 * #AI:class
 */
class SessionFake {
    private array $data = [];

    /**
     * Stores a value in the in-memory session. #AI:set
     *
     * @param string $key   Session key.
     * @param mixed  $value Payload to store.
     */
    public function set(string $key, mixed $value): void { $this->data[$key] = $value; }

    /**
     * Returns a value by key, or $default if absent. #AI:get
     *
     * @param string $key     Session key to read.
     * @param mixed  $default Fallback when key is missing.
     */
    public function get(string $key, mixed $default = null): mixed { return $this->data[$key] ?? $default; }

    /**
     * Returns true when the key exists. #AI:has
     *
     * @param string $key Session key to test.
     */
    public function has(string $key): bool { return isset($this->data[$key]); }

    /**
     * Clears all session data. #AI:flush
     */
    public function flush(): void { $this->data = []; }

    /**
     * Returns all session data as an array. #AI:all
     */
    public function all(): array  { return $this->data; }
}

#AI:class
#AI symbol: Skim\Testing\SessionFake
#AI source_path: src/Testing/SessionFake.php
#AI title: SessionFake
#AI description: In-memory session double for tests with no cookies, headers, or persistence.
#AI role: test double (session)
#AI layer: testing
#AI badges: [testing; fake; session; in-memory]
#AI intro: `SessionFake` provides a minimal in-memory session implementation for tests. It stores data in a plain array with no side effects — no cookies, no headers, no Redis.
#AI lifecycle: instantiated per-test, bound into the container via App::bind()
#AI fallback: n/a — test-only class
#AI test_seam: bind into container via $app->bind('session', fn() => new SessionFake())
#AI invariants: [Data exists only in memory for the lifetime of the object; flush() clears all data; all() returns the full data array]
#AI core_behaviors: [Plain array storage; Implements the session read/write/has/flush contract]
#AI notes: Does not implement SessionDriver interface — it provides only the methods PendingRequest needs. Use Session::setDriver() with a proper driver for full interface compliance.
#AI owns: in-memory data array
#AI entry_points: [set; get; has; flush; all]
#AI config_reads: []
#AI non_goals: [Does not implement SessionDriver interface; Does not handle flash messages; Does not persist data]
#AI side_effects: []
#AI flow: test -> SessionFake::set/get/has/flush -> in-memory array
#AI lifecycle_steps: [new SessionFake(); -> bind to container; -> controller calls Session::get() -> SessionFake::get()]
#AI section_order: [Session API; Architecture]
#AI architectural_notes: Intentionally minimal — only the methods needed by PendingRequest for session injection during tests.

#AI:set
#AI group: Session API
#AI frequency: high
#AI signature: public function set(string $key, mixed $value): void
#AI contract: Stores a value in the in-memory session array.
#AI param_details: [{name: $key | type: string | required: true | desc: Session key.}; {name: $value | type: mixed | required: true | desc: Payload to store.}]

#AI:get
#AI group: Session API
#AI frequency: high
#AI signature: public function get(string $key, mixed $default = null): mixed
#AI contract: Returns a value by key, or $default if absent.
#AI param_details: [{name: $key | type: string | required: true | desc: Session key to read.}; {name: $default | type: mixed | required: false | desc: Fallback when key is missing.}]
#AI return_detail: {type: mixed | desc: The stored value or $default.}

#AI:has
#AI group: Session API
#AI frequency: medium
#AI signature: public function has(string $key): bool
#AI contract: Returns true when the key exists in the in-memory data.
#AI param_details: [{name: $key | type: string | required: true | desc: Session key to test.}]
#AI return_detail: {type: bool | desc: True if key exists.}

#AI:flush
#AI group: Session API
#AI frequency: low
#AI signature: public function flush(): void
#AI contract: Clears all session data from the in-memory array.

#AI:all
#AI group: Session API
#AI frequency: low
#AI signature: public function all(): array
#AI contract: Returns all session data as an associative array.
#AI return_detail: {type: array | desc: Full session data array.}
