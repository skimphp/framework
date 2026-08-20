<?php

/**
 * Realtime configuration for hypermedia libraries and SSE/streaming drivers.
 *
 * This config defines:
 * - 'driver': The response driver to use for SSE/streaming (from Phase 0)
 * - 'headers': Request header mappings for hypermedia library detection
 *
 * Users can extend this config to add custom libraries without modifying framework code.
 */

return [
    /**
     * Response driver for SSE/streaming.
     *
     * The driver implements semantic interfaces (element_patcher, signal_patcher, script_runner)
     * and is resolved from the container in response::stream().
     *
     * Default: datastar_v1 (Datastar v1 protocol)
     *
     * Available drivers (after Phase 0 implementation):
     * - \skim\realtime\datastar_v1::class
     * - Custom drivers via $app->bind() or extensions
     */
    'driver' => \skim\realtime\datastar_v1::class,

    /**
     * Request header mappings for hypermedia library detection.
     *
     * Keys are library identifiers used in request::is_hypermedia($library).
     * Values are the HTTP header names that indicate the library is present.
     *
     * Users can add custom libraries:
     * 'mylib' => 'X-MyLib-Request'
     *
     * Then detect via: $req->is_hypermedia('mylib')
     */
    'headers' => [
        'htmx'     => 'HX-Request',
        'datastar' => 'datastar-request',
        'turbo'    => 'Turbo-Request',
    ],
];
