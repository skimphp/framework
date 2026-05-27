<?php declare(strict_types=1);

namespace skim\testing;

use skim\core\request;

class request_factory {
    public static function make(
        string $method   = 'GET',
        string $path     = '/',
        array  $query    = [],
        array  $post     = [],
        array  $headers  = [],
        string $raw_body = '',
        array  $cookies  = [],
        array  $files    = [],
    ): request {
        $no_prefix = ['content-type' => 'CONTENT_TYPE', 'content-length' => 'CONTENT_LENGTH'];
        $server_headers = [];
        foreach ($headers as $k => $v) {
            $lower = strtolower($k);
            $server_headers[$no_prefix[$lower] ?? ('HTTP_' . strtoupper(str_replace('-', '_', $k)))] = $v;
        }
        $server = array_merge(
            ['REQUEST_METHOD' => strtoupper($method), 'REQUEST_URI' => $path],
            $server_headers,
        );

        return new request(
            query: $query,
            post: $post,
            server: $server,
            cookies: $cookies,
            files: $files,
            raw_body: $raw_body,
        );
    }
}
