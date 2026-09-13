<?php declare(strict_types=1);

namespace Skim\Http;

/**
 * Immutable value object wrapping an HTTP response from client.
 *
 * Use as the return type for all client HTTP methods. Provides status,
 * body, headers, and convenience accessors. Never throws on non-2xx —
 * the caller decides whether a status code is an error.
 *
 * Example:
 *   $resp = $http->get('https://api.example.com/users');
 *   if ($resp->ok()) {
 *       $users = $resp->json();
 *   }
 *
 * #AI:class
 */
final class HttpResponse {
    public function __construct(
        public readonly int    $status,
        public readonly string $body,
        public readonly array  $headers,
    ) {}

    /**
     * Builds an http_response from PHP stream $http_response_header meta. #AI:fromStream
     *
     * Parses the status line and header lines from the meta array produced
     * by file_get_contents with stream contexts.
     *
     * @param string $body Raw response body.
     * @param array  $meta The $http_response_header array from PHP streams.
     */
    public static function fromStream(string $body, array $meta): static {
        $status  = 0;
        $headers = [];

        foreach ($meta as $line) {
            if (preg_match('#^HTTP/[\d.]+ (\d+)#', $line, $m)) {
                $status = (int) $m[1];
            } elseif (str_contains($line, ':')) {
                [$key, $val] = explode(':', $line, 2);
                $headers[trim($key)] = trim($val);
            }
        }

        return new static($status, $body, $headers);
    }

    /**
     * Decodes the JSON body into an array. #AI:json
     *
     * Returns an empty array when the body is not valid JSON.
     */
    public function json(): array {
        $decoded = json_decode($this->body, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Returns true for 2xx status codes. #AI:ok
     */
    public function ok(): bool {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * Returns a named response header value, or null if absent. #AI:header
     *
     * @param string $name Case-sensitive header name.
     */
    public function header(string $name): ?string {
        return $this->headers[$name] ?? null;
    }
}

#AI:class
#AI symbol: Skim\Http\HttpResponse
#AI source_path: src/Http/HttpResponse.php
#AI title: HttpResponse
#AI description: Immutable value object for HTTP responses with status, body, headers, and JSON parsing.
#AI role: HTTP response value object
#AI layer: http
#AI badges: [value-object; http; immutable]
#AI intro: `HttpResponse` is an immutable value object returned by all `client` HTTP methods. It wraps status code, body, and headers without throwing on non-2xx responses.
#AI lifecycle: created by Client::send() or fromStream(); immutable after construction
#AI test_seam: construct directly with known values
#AI invariants: [Never throws on non-2xx status; json() returns empty array on invalid JSON]
#AI core_behaviors: [Holds status, body, and headers as readonly properties; Provides ok() and json() convenience methods]
#AI owns: response data
#AI entry_points: [fromStream; json; ok; header]
#AI config_reads: []
#AI non_goals: [Does not validate status codes; Does not retry requests; Does not follow redirects]
#AI side_effects: []
#AI flow: Client::send() -> HttpResponse::fromStream() -> immutable value object
#AI section_order: [Construction; Accessors]

#AI:fromStream
#AI group: Construction
#AI frequency: internal
#AI signature: public static function fromStream(string $body, array $meta): static
#AI contract: Parses PHP stream $http_response_header meta array into status code and headers, returns a new HttpResponse.
#AI param_details: [{name: $body | type: string | required: true | desc: Raw response body string.}; {name: $meta | type: array | required: true | desc: The $http_response_header array from PHP stream context.}]
#AI return_detail: {type: static | desc: New HttpResponse with parsed status and headers.}

#AI:json
#AI group: Accessors
#AI frequency: high
#AI signature: public function json(): array
#AI contract: Decodes the response body as JSON. Returns an empty array when the body is not valid JSON.
#AI return_detail: {type: array | desc: Decoded JSON body, or empty array on invalid JSON.}

#AI:ok
#AI group: Accessors
#AI frequency: high
#AI signature: public function ok(): bool
#AI contract: Returns true when the status code is in the 2xx range.
#AI return_detail: {type: bool | desc: True for 200-299 status codes.}

#AI:header
#AI group: Accessors
#AI frequency: medium
#AI signature: public function header(string $name): ?string
#AI contract: Returns the value of a named response header, or null if the header is not present.
#AI param_details: [{name: $name | type: string | required: true | desc: Case-sensitive header name to look up.}]
#AI return_detail: {type: ?string | desc: Header value or null if absent.}
