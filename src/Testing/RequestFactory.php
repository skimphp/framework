<?php declare(strict_types=1);

namespace Skim\Testing;

use Skim\Core\Request;

/**
 * Factory that builds request objects from raw parameters for test dispatch. #AI:class
 *
 * Use when PendingRequest needs to construct a request with proper $_SERVER-style
 * header mapping. Handles the HTTP_ prefix convention and Content-Type/Content-Length
 * special cases automatically.
 *
 * Example:
 *   $req = RequestFactory::make('POST', '/api/users', headers: ['Accept' => 'application/json']);
 *
 * Testing: This class IS the test infrastructure — used internally by PendingRequest.
 *
 * #AI:class
 */
class RequestFactory {
    /**
     * Builds a request object from raw parameters. #AI:make
     *
     * Maps human-readable header names to $_SERVER-style keys (HTTP_ prefix,
     * with Content-Type and Content-Length as special cases). All parameters
     * have sensible defaults for GET requests.
     *
     * Example:
     *   $req = RequestFactory::make(
     *       method: 'POST',
     *       path: '/api/users',
     *       json: [],
     *       headers: ['Content-Type' => 'application/json'],
     *       raw_body: '{"name":"John"}',
     *   );
     *
     * @param string $method   HTTP method (GET, POST, PUT, DELETE, etc.).
     * @param string $path     Request URI path.
     * @param array  $query    Query string parameters ($_GET equivalent).
     * @param array  $post     Form-encoded body data ($_POST equivalent).
     * @param array  $headers  Human-readable headers (auto-mapped to $_SERVER keys).
     * @param string $rawBody Raw request body for JSON or other content types.
     * @param array  $cookies  Cookie key-value pairs.
     * @param array  $files    Uploaded file entries ($_FILES equivalent).
     */
    public static function make(
        string $method   = 'GET',
        string $path     = '/',
        array  $query    = [],
        array  $post     = [],
        array  $headers  = [],
        string $rawBody = '',
        array  $cookies  = [],
        array  $files    = [],
    ): \Skim\Core\Request {
        $noPrefix = ['content-type' => 'CONTENT_TYPE', 'content-length' => 'CONTENT_LENGTH'];
        $serverHeaders = [];
        foreach ($headers as $k => $v) {
            $lower = strtolower($k);
            $serverHeaders[$noPrefix[$lower] ?? ('HTTP_' . strtoupper(str_replace('-', '_', $k)))] = $v;
        }
        $server = array_merge(
            ['REQUEST_METHOD' => strtoupper($method), 'REQUEST_URI' => $path],
            $serverHeaders,
        );

        return new \Skim\Core\Request(
            query: $query,
            post: $post,
            server: $server,
            cookies: $cookies,
            files: $files,
            rawBody: $rawBody,
        );
    }
}

#AI:class
#AI symbol: Skim\Testing\RequestFactory
#AI source_path: src/Testing/RequestFactory.php
#AI title: RequestFactory
#AI description: Factory that builds request objects from raw parameters with proper $_SERVER header mapping.
#AI role: test request factory
#AI layer: testing
#AI badges: [testing; factory; request; infrastructure]
#AI intro: `RequestFactory` constructs `Skim\Core\Request` instances from raw parameters, handling the mapping from human-readable header names to PHP's `$_SERVER`-style keys.
#AI lifecycle: called per-request by PendingRequest::send()
#AI fallback: n/a — test-only class
#AI test_seam: static factory, call directly to build custom requests
#AI invariants: [Content-Type and Content-Length map without HTTP_ prefix; All other headers get HTTP_ prefix with uppercased underscores; Method is always uppercased]
#AI core_behaviors: [Maps header names to $_SERVER convention; Merges REQUEST_METHOD and REQUEST_URI into server array; Passes all data to request constructor]
#AI notes: Content-Type and Content-Length are special cases in PHP's $_SERVER and do not use the HTTP_ prefix.
#AI owns: nothing — pure factory
#AI entry_points: [make]
#AI config_reads: []
#AI non_goals: [Does not validate request data; Does not run middleware or routing]
#AI side_effects: []
#AI flow: PendingRequest::send() -> RequestFactory::make() -> new Request(...)
#AI lifecycle_steps: [PendingRequest builds headers/method/path; -> RequestFactory::make(); -> header mapping; -> new Request(query, post, server, cookies, files, rawBody)]
#AI section_order: [Factory; Architecture]
#AI architectural_notes: Isolates the $_SERVER header mapping logic so PendingRequest stays focused on dispatch configuration.

#AI:make
#AI group: Factory
#AI frequency: internal
#AI signature: public static function make(string $method = 'GET', string $path = '/', array $query = [], array $post = [], array $headers = [], string $rawBody = '', array $cookies = [], array $files = []): request
#AI contract: Builds a request object from raw parameters. Maps human-readable headers to $_SERVER-style keys with proper HTTP_ prefix handling.
#AI param_details: [{name: $method | type: string | required: false | desc: HTTP method, defaults to GET.}; {name: $path | type: string | required: false | desc: Request URI path, defaults to /.}; {name: $query | type: array | required: false | desc: Query string parameters.}; {name: $post | type: array | required: false | desc: Form-encoded body data.}; {name: $headers | type: array | required: false | desc: Human-readable headers auto-mapped to $_SERVER keys.}; {name: $rawBody | type: string | required: false | desc: Raw request body for JSON or other content types.}; {name: $cookies | type: array | required: false | desc: Cookie key-value pairs.}; {name: $files | type: array | required: false | desc: Uploaded file entries.}]
#AI return_detail: {type: request | desc: Fully configured request object ready for dispatch.}
