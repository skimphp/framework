<?php declare(strict_types=1);

namespace skim\worker;

/**
 * Contract for classes that hold request-scoped static state.
 *
 * In worker mode a single PHP process handles many requests, so any static
 * property that carries request-specific data must be reset between requests.
 * Implement this interface and register the class for auto-discovery by
 * worker_reset::apply().
 *
 * #AI:interface
 */
interface resettable {
    public static function reset_request(): void;
}
