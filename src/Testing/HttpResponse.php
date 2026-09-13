<?php declare(strict_types=1);

namespace Skim\Testing;

use Skim\Core\Response;

/**
 * Fluent assertion wrapper around a response object for HTTP tests. #AI:class
 *
 * Use as the return type of HttpClient/PendingRequest HTTP methods.
 * All assert_* methods return $this for chaining. Uses Pest's expect() internally.
 *
 * Example:
 *   $client->post('/api/users', json: ['name' => 'John'])
 *       ->assertCreated()
 *       ->assertJson(['name' => 'John']);
 *
 * Testing: This class IS the test assertion layer — use directly in test cases.
 *
 * #AI:class
 */
class HttpResponse {
    public function __construct(private readonly \Skim\Core\Response $res) {}

    /**
     * Asserts the response status code matches the expected value. #AI:assertStatus
     *
     * @param int $code Expected HTTP status code.
     */
    public function assertStatus(int $code): static {
        expect($this->res->getStatus())->toBe($code);
        return $this;
    }

    /**
     * Asserts 200 OK. #AI:assertOk
     */
    public function assertOk(): static           { return $this->assertStatus(200); }

    /**
     * Asserts 201 Created. #AI:assertCreated
     */
    public function assertCreated(): static      { return $this->assertStatus(201); }

    /**
     * Asserts 204 No Content. #AI:assertNoContent
     */
    public function assertNoContent(): static   { return $this->assertStatus(204); }

    /**
     * Asserts 404 Not Found. #AI:assertNotFound
     */
    public function assertNotFound(): static    { return $this->assertStatus(404); }

    /**
     * Asserts 401 Unauthorized. #AI:assertUnauthorized
     */
    public function assertUnauthorized(): static { return $this->assertStatus(401); }

    /**
     * Asserts 403 Forbidden. #AI:assertForbidden
     */
    public function assertForbidden(): static    { return $this->assertStatus(403); }

    /**
     * Asserts 422 Unprocessable Entity. #AI:assertUnprocessable
     */
    public function assertUnprocessable(): static { return $this->assertStatus(422); }

    /**
     * Asserts a redirect response with the expected Location header. #AI:assertRedirect
     *
     * @param string $url Expected redirect target URL.
     */
    public function assertRedirect(string $url): static {
        expect($this->isRedirect())->toBeTrue();
        expect($this->res->getHeader('Location'))->toBe($url);
        return $this;
    }

    /**
     * Asserts the JSON body contains the expected data (subset match). #AI:assertJson
     *
     * @param array $data Expected key-value pairs (subset, not exact match).
     */
    public function assertJson(array $data): static {
        expect($this->res->getJson())->toMatchArray($data);
        return $this;
    }

    /**
     * Asserts a response header has the expected value. #AI:assertHeader
     *
     * @param string $key   Header name.
     * @param string $value Expected header value.
     */
    public function assertHeader(string $key, string $value): static {
        expect($this->res->getHeader($key))->toBe($value);
        return $this;
    }

    /**
     * Asserts the response body contains the given text. #AI:assertContains
     *
     * @param string $text Substring expected in the response body.
     */
    public function assertContains(string $text): static {
        expect($this->res->getBody())->toContain($text);
        return $this;
    }

    /**
     * Returns the HTTP status code. #AI:status
     */
    public function status(): int   { return $this->res->getStatus(); }

    /**
     * Returns the decoded JSON body. #AI:json
     */
    public function json(): array   { return $this->res->getJson(); }

    /**
     * Returns the raw response body. #AI:body
     */
    public function body(): string  { return $this->res->getBody(); }

    /**
     * Returns a specific header value. #AI:header
     *
     * @param string $key Header name.
     */
    public function header(string $key): ?string { return $this->res->getHeader($key); }

    /**
     * Returns true for 3xx redirect status codes. #AI:isRedirect
     */
    public function isRedirect(): bool {
        return in_array($this->res->getStatus(), [301, 302, 303, 307, 308], true);
    }

    /**
     * Dumps the response body and returns $this for continued chaining. #AI:dump
     */
    public function dump(): static  { dump($this->res->getBody()); return $this; }

    /**
     * Dumps the response body and halts execution. #AI:dd
     */
    public function dd(): never     { dd($this->res->getBody()); }
}

#AI:class
#AI symbol: Skim\Testing\HttpResponse
#AI source_path: src/Testing/HttpResponse.php
#AI title: HttpResponse
#AI description: Fluent assertion wrapper for HTTP test responses with status, JSON, header, and body assertions.
#AI role: test assertion wrapper
#AI layer: testing
#AI badges: [testing; assertions; http; fluent]
#AI intro: `HttpResponse` wraps a `Skim\Core\Response` and provides fluent assertion methods powered by Pest's `expect()`. All `assert_*` methods return `$this` for chaining.
#AI lifecycle: returned by PendingRequest/HttpClient HTTP methods, used inline in test cases
#AI fallback: n/a — test-only class
#AI test_seam: instantiate directly or receive from HttpClient methods
#AI invariants: [assert_* methods throw on failure via Pest expect(); All assert_* methods return $this; isRedirect() covers 301, 302, 303, 307, 308]
#AI core_behaviors: [Wraps core response for test assertions; Uses Pest expect() for failure reporting; Provides both assertion and accessor methods]
#AI notes: assertJson() uses subset matching (toMatchArray), not exact equality.
#AI owns: core response reference
#AI entry_points: [assertStatus; assertOk; assertCreated; assertJson; assertRedirect; assertContains; status; json; body; header]
#AI config_reads: []
#AI non_goals: [Does not make HTTP requests; Does not mock external services]
#AI side_effects: [dump() and dd() produce output; assert_* methods throw on failure]
#AI flow: HttpClient::get/post/etc() -> HttpResponse -> assert_*() -> Pest expect()
#AI lifecycle_steps: [PendingRequest::send() -> new HttpResponse($res); -> test calls assertOk()->assertJson([...])]
#AI section_order: [Status Assertions; Content Assertions; Accessors; Debug; Architecture]
#AI architectural_notes: Thin wrapper over core response — adds test assertion ergonomics without modifying response behavior.

#AI:assertStatus
#AI group: Status Assertions
#AI frequency: high
#AI signature: public function assertStatus(int $code): static
#AI contract: Asserts the response status code matches the expected value. Throws on mismatch.
#AI param_details: [{name: $code | type: int | required: true | desc: Expected HTTP status code.}]
#AI return_detail: {type: static | desc: $this for chaining.}

#AI:assertOk
#AI group: Status Assertions
#AI frequency: high
#AI signature: public function assertOk(): static
#AI contract: Asserts 200 OK status.
#AI return_detail: {type: static | desc: $this for chaining.}

#AI:assertCreated
#AI group: Status Assertions
#AI frequency: high
#AI signature: public function assertCreated(): static
#AI contract: Asserts 201 Created status.
#AI return_detail: {type: static | desc: $this for chaining.}

#AI:assertNoContent
#AI group: Status Assertions
#AI frequency: low
#AI signature: public function assertNoContent(): static
#AI contract: Asserts 204 No Content status.
#AI return_detail: {type: static | desc: $this for chaining.}

#AI:assertNotFound
#AI group: Status Assertions
#AI frequency: medium
#AI signature: public function assertNotFound(): static
#AI contract: Asserts 404 Not Found status.
#AI return_detail: {type: static | desc: $this for chaining.}

#AI:assertUnauthorized
#AI group: Status Assertions
#AI frequency: medium
#AI signature: public function assertUnauthorized(): static
#AI contract: Asserts 401 Unauthorized status.
#AI return_detail: {type: static | desc: $this for chaining.}

#AI:assertForbidden
#AI group: Status Assertions
#AI frequency: low
#AI signature: public function assertForbidden(): static
#AI contract: Asserts 403 Forbidden status.
#AI return_detail: {type: static | desc: $this for chaining.}

#AI:assertUnprocessable
#AI group: Status Assertions
#AI frequency: medium
#AI signature: public function assertUnprocessable(): static
#AI contract: Asserts 422 Unprocessable Entity status.
#AI return_detail: {type: static | desc: $this for chaining.}

#AI:assertRedirect
#AI group: Status Assertions
#AI frequency: medium
#AI signature: public function assertRedirect(string $url): static
#AI contract: Asserts a redirect status and matching Location header.
#AI param_details: [{name: $url | type: string | required: true | desc: Expected redirect target URL.}]
#AI return_detail: {type: static | desc: $this for chaining.}

#AI:assertJson
#AI group: Content Assertions
#AI frequency: high
#AI signature: public function assertJson(array $data): static
#AI contract: Asserts the JSON body contains the expected data as a subset match.
#AI param_details: [{name: $data | type: array | required: true | desc: Expected key-value pairs (subset match).}]
#AI return_detail: {type: static | desc: $this for chaining.}

#AI:assertHeader
#AI group: Content Assertions
#AI frequency: medium
#AI signature: public function assertHeader(string $key, string $value): static
#AI contract: Asserts a response header has the expected value.
#AI param_details: [{name: $key | type: string | required: true | desc: Header name.}; {name: $value | type: string | required: true | desc: Expected header value.}]
#AI return_detail: {type: static | desc: $this for chaining.}

#AI:assertContains
#AI group: Content Assertions
#AI frequency: medium
#AI signature: public function assertContains(string $text): static
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

#AI:isRedirect
#AI group: Accessors
#AI frequency: low
#AI signature: public function isRedirect(): bool
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
