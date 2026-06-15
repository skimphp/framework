<?php declare(strict_types=1);

namespace skim\core;

/**
 * DI binding lifetime — controls whether make() caches the resolved instance. #AI:enum
 *
 * - singleton: one shared instance per process (the default). Correct for
 *   stateless services. In worker mode it lives for the whole worker, so it
 *   must NOT hold request-specific state.
 * - request: one instance per request; end_request() drops it so the next
 *   request rebuilds it. Use for services that cache request-scoped data.
 * - transient: a fresh instance on every make(); never cached.
 *
 * #AI:enum
 */
enum lifetime {
    case singleton;
    case request;
    case transient;
}
