<?php declare(strict_types=1);

namespace Skim\Testing;

use Skim\Core\Response;

/**
 * Fluent assertion wrapper around a response object for HTTP tests. #AI:class
 *
 * Use as the return type of http_client/pending_request HTTP methods.
 * All assert_* methods return $this for chaining. Uses Pest's expect() internally.
 *
 * Example:
 *   $client->post('/api/users', json: ['name' => 'John'])
 *       ->assert_created()
 *       ->assert_json(['name' => 'John']);
 *
 * Testing: This class IS the test assertion layer — use directly in test cases.
 *
 * #AI:class
 */
class HttpResponse {
    public function __construct(private readonly \Skim\Core\Response $res) {}

    /**
     * Asserts the response status code matches the expected value. #AI:assert_status
     *
     * @param int $code Expected HTTP status code.
     */
    public function assert_status(int $code): static {
        expect($this->res->get_status())->toBe($code);
        return $this;
    }

    /**
     * Asserts 200 OK. #AI:assert_ok
     */
    public function assert_ok(): static           { return $this->assert_status(200); }

    /**
     * Asserts 201 Created. #AI:assert_created
     */
    public function assert_created(): static      { return $this->assert_status(201); }

    /**
     * Asserts 204 No Content. #AI:assert_no_content
     */
    public function assert_no_content(): static   { return $this->assert_status(204); }

    /**
     * Asserts 404 Not Found. #AI:assert_not_found
     */
    public function assert_not_found(): static    { return $this->assert_status(404); }

    /**
     * Asserts 401 Unauthorized. #AI:assert_unauthorized
     */
    public function assert_unauthorized(): static { return $this->assert_status(401); }

    /**
     * Asserts 403 Forbidden. #AI:assert_forbidden
     */
    public function assert_forbidden(): static    { return $this->assert_status(403); }

    /**
     * Asserts 422 Unprocessable Entity. #AI:assert_unprocessable
     */
    public function assert_unprocessable(): static { return $this->assert_status(422); }

    /**
     * Asserts a redirect response with the expected Location header. #AI:assert_redirect
     *
     * @param string $url Expected redirect target URL.
     */
    public function assert_redirect(string $url): static {
        expect($this->is_redirect())->toBeTrue();
        expect($this->res->get_header('Location'))->toBe($url);
        return $this;
    }

    /**
     * Asserts the JSON body contains the expected data (subset match). #AI:assert_json
     *
     * @param array $data Expected key-value pairs (subset, not exact match).
     */
    public function assert_json(array $data): static {
        expect($this->res->get_json())->toMatchArray($data);
        return $this;
    }

    /**
     * Asserts a response header has the expected value. #AI:assert_header
     *
     * @param string $key   Header name.
     * @param string $value Expected header value.
     */
    public function assert_header(string $key, string $value): static {
        expect($this->res->get_header($key))->toBe($value);
        return $this;
    }

    /**
     * Asserts the response body contains the given text. #AI:assert_contains
     *
     * @param string $text Substring expected in the response body.
     */
    public function assert_contains(string $text): static {
        expect($this->res->get_body())->toContain($text);
        return $this;
    }

    /**
     * Returns the HTTP status code. #AI:status
     */
    public function status(): int   { return $this->res->get_status(); }

    /**
     * Returns the decoded JSON body. #AI:json
     */
    public function json(): array   { return $this->res->get_json(); }

    /**
     * Returns the raw response body. #AI:body
     */
    public function body(): string  { return $this->res->get_body(); }

    /**
     * Returns a specific header value. #AI:header
     *
     * @param string $key Header name.
     */
    public function header(string $key): ?string { return $this->res->get_header($key); }

    /**
     * Returns true for 3xx redirect status codes. #AI:is_redirect
     */
    public function is_redirect(): bool {
        return in_array($this->res->get_status(), [301, 302, 303, 307, 308], true);
    }

    /**
     * Dumps the response body and returns $this for continued chaining. #AI:dump
     */
    public function dump(): static  { dump($this->res->get_body()); return $this; }

    /**
     * Dumps the response body and halts execution. #AI:dd
     */
    public function dd(): never     { dd($this->res->get_body()); }
}

#AI:class
#AI symbol: Skim\Testing\HttpResponse
#AI source_path: src/testing/http_response.php
#AI title: http_response
#AI description: Fluent assertion wrapper for HTTP test responses with status, JSON, header, and body assertions.
#AI role: test assertion wrapper
#AI layer: testing
#AI badges: [testing; assertions; http; fluent]
#AI intro: `http_response` wraps a `Skim\Core\Response` and provides fluent assertion methods powered by Pest's `expect()`. All `assert_*` methods return `$this` for chaining.
#AI lifecycle: returned by pending_request/http_client HTTP methods, used inline in test cases
#AI fallback: n/a — test-only class
#AI test_seam: instantiate directly or receive from http_client methods
#AI invariants: [assert_* methods throw on failure via Pest expect(); All assert_* methods return $this; is_redirect() covers 301, 302, 303, 307, 308]
#AI core_behaviors: [Wraps core response for test assertions; Uses Pest expect() for failure reporting; Provides both assertion and accessor methods]
#AI notes: assert_json() uses subset matching (toMatchArray), not exact equality.
#AI owns: core response reference
#AI entry_points: [assert_status; assert_ok; assert_created; assert_json; assert_redirect; assert_contains; status; json; body; header]
#AI config_reads: []
#AI non_goals: [Does not make HTTP requests; Does not mock external services]
#AI side_effects: [dump() and dd() produce output; assert_* methods throw on failure]
#AI flow: http_client::get/post/etc() -> http_response -> assert_*() -> Pest expect()
#AI lifecycle_steps: [pending_request::send() -> new http_response($res); -> test calls assert_ok()->assert_json([...])]
#AI section_order: [Status Assertions; Content Assertions; Accessors; Debug; Architecture]
#AI architectural_notes: Thin wrapper over core response — adds test assertion ergonomics without modifying response behavior.

#AI:assert_status
#AI group: Status Assertions
#AI frequency: high
#AI signature: public function assert_status(int $code): static
#AI contract: Asserts the response status code matches the expected value. Throws on mismatch.
#AI param_details: [{name: $code | type: int | required: true | desc: Expected HTTP status code.}]
#AI return_detail: {type: static | desc: $this for chaining.}

#AI:assert_ok
#AI group: Status Assertions
#AI frequency: high
#AI signature: public function assert_ok(): static
#AI contract: Asserts 200 OK status.
#AI return_detail: {type: static | desc: $this for chaining.}

#AI:assert_created
#AI group: Status Assertions
#AI frequency: high
#AI signature: public function assert_created(): static
#AI contract: Asserts 201 Created status.
#AI return_detail: {type: static | desc: $this for chaining.}

#AI:assert_no_content
#AI group: Status Assertions
#AI frequency: low
#AI signature: public function assert_no_content(): static
#AI contract: Asserts 204 No Content status.
#AI return_detail: {type: static | desc: $this for chaining.}

#AI:assert_not_found
#AI group: Status Assertions
#AI frequency: medium
#AI signature: public function assert_not_found(): static
#AI contract: Asserts 404 Not Found status.
#AI return_detail: {type: static | desc: $this for chaining.}

#AI:assert_unauthorized
#AI group: Status Assertions
#AI frequency: medium
#AI signature: public function assert_unauthorized(): static
#AI contract: Asserts 401 Unauthorized status.
#AI return_detail: {type: static | desc: $this for chaining.}

#AI:assert_forbidden
#AI group: Status Assertions
#AI frequency: low
#AI signature: public function assert_forbidden(): static
#AI contract: Asserts 403 Forbidden status.
#AI return_detail: {type: static | desc: $this for chaining.}

#AI:assert_unprocessable
#AI group: Status Assertions
#AI frequency: medium
#AI signature: public function assert_unprocessable(): static
#AI contract: Asserts 422 Unprocessable Entity status.
#AI return_detail: {type: static | desc: $this for chaining.}

#AI:assert_redirect
#AI group: Status Assertions
#AI frequency: medium
#AI signature: public function assert_redirect(string $url): static
#AI contract: Asserts a redirect status and matching Location header.
#AI param_details: [{name: $url | type: string | required: true | desc: Expected redirect target URL.}]
#AI return_detail: {type: static | desc: $this for chaining.}

#AI:assert_json
#AI group: Content Assertions
#AI frequency: high
#AI signature: public function assert_json(array $data): static
#AI contract: Asserts the JSON body contains the expected data as a subset match.
#AI param_details: [{name: $data | type: array | required: true | desc: Expected key-value pairs (subset match).}]
#AI return_detail: {type: static | desc: $this for chaining.}

#AI:assert_header
#AI group: Content Assertions
#AI frequency: medium
#AI signature: public function assert_header(string $key, string $value): static
#AI contract: Asserts a response header has the expected value.
#AI param_details: [{name: $key | type: string | required: true | desc: Header name.}; {name: $value | type: string | required: true | desc: Expected header value.}]
#AI return_detail: {type: static | desc: $this for chaining.}

#AI:assert_contains
#AI group: Content Assertions
#AI frequency: medium
#AI signature: public function assert_contains(string $text): static
#AI contract: Asserts the response body contains the given substring.
#AI param_details: [{name: $text | type: string | required: true | desc: Substring expected in the body.}]
#AI return_detail: {type: static | desc: $this for chaining.}

#AI:status
#AI group: Accessors
#AI frequency: medium
#AI signature: public function status(): int
#AI contract: Returns the HTTP status code.
#AI return_detail: {type: int | desc: The response status code.}

#AI:json
#AI group: Accessors
#AI frequency: medium
#AI signature: public function json(): array
#AI contract: Returns the decoded JSON body.
#AI return_detail: {type: array | desc: Decoded JSON response body.}

#AI:body
#AI group: Accessors
#AI frequency: medium
#AI signature: public function body(): string
#AI contract: Returns the raw response body.
#AI return_detail: {type: string | desc: Raw response body string.}

#AI:header
#AI group: Accessors
#AI frequency: medium
#AI signature: public function header(string $key): ?string
#AI contract: Returns a specific header value or null.
#AI param_details: [{name: $key | type: string | required: true | desc: Header name.}]
#AI return_detail: {type: ?string | desc: Header value or null if not set.}

#AI:is_redirect
#AI group: Accessors
#AI frequency: low
#AI signature: public function is_redirect(): bool
#AI contract: Returns true for 3xx redirect status codes (301, 302, 303, 307, 308).
#AI return_detail: {type: bool | desc: True if status is a redirect.}

#AI:dump
#AI group: Debug
#AI frequency: low
#AI signature: public function dump(): static
#AI contract: Dumps the response body for debugging and returns $this for continued chaining.
#AI return_detail: {type: static | desc: $this for chaining.}

#AI:dd
#AI group: Debug
#AI frequency: low
#AI signature: public function dd(): never
#AI contract: Dumps the response body and halts execution.
#AI warnings: [Halts PHP execution — use only during debugging]
