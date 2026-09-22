<?php declare(strict_types=1);

namespace Skim\Ext;

/**
 * Named priority bands for extension registration and boot ordering.
 *
 * Use when declaring extension priority in composer.json extra.skim.priority.
 * Lower numbers run first. Extensions without an explicit priority default
 * to USER (100).
 *
 * Example:
 *   // composer.json extra.skim: { "priority": 10 }
 *   // or in code: ExtensionPriority::CORE
 *
 * #AI:class
 */
final class ExtensionPriority {
    public const CORE = 10;
    public const OFFICIAL = 50;
    public const PAID = 80;
    public const USER = 100;
}

#AI:class
#AI symbol: Skim\Ext\ExtensionPriority
#AI source_path: src/Ext/ExtensionPriority.php
#AI title: ExtensionPriority
#AI description: Named integer bands controlling extension registration and boot order.
#AI role: priority constants
#AI layer: ext
#AI badges: [constants; extension; ordering]
#AI intro: `ExtensionPriority` defines four named bands that control when extensions register and boot relative to each other. Lower values execute first.
#AI lifecycle: stateless constants
#AI test_seam: none needed
#AI invariants: [CORE < OFFICIAL < PAID < USER; lower number = earlier execution]
#AI core_behaviors: [Constants are referenced by ExtRegistry and extensionManager for sort ordering]
#AI owns: priority band constants
#AI entry_points: []
#AI config_reads: []
#AI non_goals: [Does not enforce priority values; Does not validate extension ordering]
#AI side_effects: []
#AI section_order: [Constants]
