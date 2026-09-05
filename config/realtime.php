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
     * The driver implements semantic interfaces (ElementPatcher, SignalPatcher, ScriptRunner)
     * and is resolved from the container in Response::stream().
     *
     * Default: Datastar (Datastar v1 protocol)
     *
     * Available drivers (after Phase 0 implementation):
     * - \Skim\Realtime\Datastar::class
     * - Custom drivers via $app->bind() or extensions
     */
    'driver' => \Skim\Realtime\Datastar::class,

    /**
     * Request header mappings for hypermedia library detection.
     *
     * Keys are library identifiers used in Request::isHypermedia($library).
     * Values are the HTTP header names that indicate the library is present.
     *
     * Users can add custom libraries:
     * 'mylib' => 'X-MyLib-Request'
     *
     * Then detect via: $req->isHypermedia('mylib')
     */
    'headers' => [
        'htmx'     => 'HX-Request',
        'datastar' => 'datastar-request',
        'turbo'    => 'Turbo-Request',
    ],
];
