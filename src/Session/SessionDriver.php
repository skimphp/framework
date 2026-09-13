<?php declare(strict_types=1);

namespace Skim\Session;

/**
 * Contract for session storage backends. #AI:class
 *
 * Implement this interface to add a custom session driver (e.g., database, DynamoDB).
 * The session facade resolves one driver per request via config('app.session.driver').
 *
 * Example:
 *   class DbSessionDriver implements SessionDriver { ... }
 *   Session::setDriver(new DbSessionDriver(...));
 *
 * Testing: Use SessionFake which satisfies this interface in-memory.
 *
 * #AI:class
 */
interface SessionDriver {
    /**
     * Starts or resumes the session. #AI:start
     */
    public function start(): void;

    /**
     * Returns session value by key, or $default if absent. #AI:get
     *
     * @param string $key     Session key to read.
     * @param mixed  $default Fallback when key is missing.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Stores a value in the session. #AI:set
     *
     * @param string $key   Session key to write.
     * @param mixed  $value Payload to persist.
     */
    public function set(string $key, mixed $value): void;

    /**
     * Returns true when the key exists. #AI:has
     *
     * @param string $key Session key to test.
     */
    public function has(string $key): bool;

    /**
     * Removes a key from the session. #AI:delete
     *
     * @param string $key Session key to remove.
     */
    public function delete(string $key): void;

    /**
     * Regenerates the session ID to prevent fixation. #AI:regenerate
     */
    public function regenerate(): void;

    /**
     * Destroys all session data. #AI:flush
     */
    public function flush(): void;

    /**
     * Returns the current session ID. #AI:id
     */
    public function id(): string;
}

#AI:class
#AI symbol: Skim\Session\SessionDriver
#AI source_path: src/Session/SessionDriver.php
#AI title: SessionDriver
#AI description: Interface contract for session storage backends.
#AI role: session driver interface
#AI layer: session
#AI badges: [interface; session; contract]
#AI intro: `SessionDriver` defines the contract that all session backends must implement. The session facade resolves one concrete driver per request based on config.
#AI lifecycle: one implementation resolved per request by session facade
#AI fallback: n/a — interface only
#AI test_seam: SessionFake implements this interface for tests
#AI invariants: [start() must be idempotent; get() returns $default on missing key; flush() destroys all session data]
#AI core_behaviors: [Defines the minimal API for session read/write/lifecycle operations]
#AI notes: Implement this interface to add custom session backends (database, DynamoDB, etc.).
#AI owns: nothing — contract only
#AI entry_points: [start; get; set; has; delete; regenerate; flush; id]
#AI config_reads: []
#AI non_goals: [Does not prescribe storage mechanism; Does not handle encryption or serialization format]
#AI side_effects: []
#AI flow: session facade -> SessionDriver implementation -> backend storage
#AI lifecycle_steps: [Session::start() -> Driver::start(); Session::get() -> Driver::get(); etc.]
#AI section_order: [Session API; Lifecycle; Architecture]
#AI architectural_notes: Kept minimal — only the operations the session facade needs. Custom drivers may add internal methods.

#AI:start
#AI group: Lifecycle
#AI frequency: internal
#AI signature: public function start(): void
#AI contract: Starts or resumes the session. Must be idempotent.

#AI:get
#AI group: Session API
#AI frequency: high
#AI signature: public function get(string $key, mixed $default = null): mixed
#AI contract: Returns session value by key, or $default if absent.
#AI param_details: [{name: $key | type: string | required: true | desc: Session key to read.}; {name: $default | type: mixed | required: false | desc: Fallback when key is missing.}]
#AI return_detail: {type: mixed | desc: The stored value or $default.}

#AI:set
#AI group: Session API
#AI frequency: high
#AI signature: public function set(string $key, mixed $value): void
#AI contract: Stores a value in the session.
#AI param_details: [{name: $key | type: string | required: true | desc: Session key to write.}; {name: $value | type: mixed | required: true | desc: Payload to persist.}]

#AI:has
#AI group: Session API
#AI frequency: medium
#AI signature: public function has(string $key): bool
#AI contract: Returns true when the key exists in the session.
#AI param_details: [{name: $key | type: string | required: true | desc: Session key to test.}]
#AI return_detail: {type: bool | desc: True if key exists.}

#AI:delete
#AI group: Session API
#AI frequency: medium
#AI signature: public function delete(string $key): void
#AI contract: Removes a key from the session.
#AI param_details: [{name: $key | type: string | required: true | desc: Session key to remove.}]

#AI:regenerate
#AI group: Lifecycle
#AI frequency: low
#AI signature: public function regenerate(): void
#AI contract: Regenerates the session ID to prevent session fixation attacks.

#AI:flush
#AI group: Lifecycle
#AI frequency: low
#AI signature: public function flush(): void
#AI contract: Destroys all session data for the current session.

#AI:id
#AI group: Session API
#AI frequency: low
#AI signature: public function id(): string
#AI contract: Returns the current session ID.
#AI return_detail: {type: string | desc: The active session identifier.}
