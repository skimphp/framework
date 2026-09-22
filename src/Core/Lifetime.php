<?php declare(strict_types=1);

namespace Skim\Core;

/**
 * DI binding lifetime — controls whether make() caches the resolved instance. #AI:enum
 *
 * - singleton: one shared instance per process (the default). Correct for
 *   stateless services. In worker mode it lives for the whole worker, so it
 *   must NOT hold request-specific state.
 * - request: one instance per request; endRequest() drops it so the next
 *   request rebuilds it. Use for services that cache request-scoped data.
 * - transient: a fresh instance on every make(); never cached.
 *
 * #AI:enum
 */
enum Lifetime {
    case Singleton;
    case Request;
    case Transient;
}

#AI:enum
#AI symbol: Skim\Core\Lifetime
#AI source_path: src/Core/Lifetime.php
#AI title: lifetime
#AI description: DI binding lifetime — controls whether make() caches the resolved instance.
#AI role: enum
#AI layer: core
#AI badges: [di; lifetime; enum]
#AI intro: `lifetime` is an enum with three cases that control how the DI container caches resolved services. singleton = process-wide cache; request = cleared at endRequest(); transient = never cached.
#AI lifecycle: used during bind() and make() resolution
#AI test_seam: n/a — pure data enum
#AI invariants: [singleton is the default when no lifetime is specified; request-scoped bindings are cleared by endRequest(); transient bindings skip the resolved cache entirely]
#AI core_behaviors: [Determines caching strategy in App::make(); Drives clearLifetimeMeta() and endRequest() cleanup]
#AI owns: no mutable state
#AI entry_points: [singleton; request; transient]
#AI config_reads: []
#AI non_goals: [Does not validate binding existence; Does not enforce usage — the container interprets the enum]
#AI side_effects: [None — pure value enum]
