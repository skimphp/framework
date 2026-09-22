<?php declare(strict_types=1);

namespace Skim\Ext;

/**
 * Canonical map of capability slugs to human-readable descriptions.
 *
 * Use when validating or displaying capability strings declared by extensions.
 * The TERMS constant is the single source of truth for recognized capability
 * names; unknown capabilities are not rejected but flagged by isKnown().
 *
 * Example:
 *   CapabilityVocabulary::isKnown('auth');        // true
 *   CapabilityVocabulary::TERMS['rate-limiting'];  // 'request rate limiting'
 *
 * #AI:class
 */
final class CapabilityVocabulary {
    public const TERMS = [
        'auth'               => 'user authentication (credentials)',
        'oauth'              => 'third-party OAuth / social login',
        'session-auth'       => 'session-based authentication',
        'token-auth'         => 'API token authentication',
        'csrf'               => 'CSRF protection',
        'rate-limiting'      => 'request rate limiting',
        'email-verification' => 'email verification flow',
        'password-reset'     => 'password reset flow',
        '2fa'                => 'two-factor authentication',
        'queues'             => 'background job queues',
        'broadcasting'       => 'real-time event broadcasting',
        'hypermedia'         => 'hypermedia / SSE UI driver (datastar, htmx, ...)',
        'file-storage'       => 'file upload and storage',
        'search'             => 'full-text search',
        'payments'           => 'payment processing',
        'notifications'      => 'multi-channel notifications',
    ];

    /**
     * Returns true when the capability slug exists in TERMS. #AI:isKnown
     *
     * @param string $capability Slug to test, e.g. 'auth' or 'rate-limiting'.
     */
    public static function isKnown(string $capability): bool {
        return array_key_exists($capability, self::TERMS);
    }
}

#AI:class
#AI symbol: Skim\Ext\CapabilityVocabulary
#AI source_path: src/Ext/CapabilityVocabulary.php
#AI title: CapabilityVocabulary
#AI description: Canonical map of recognized extension capability slugs to human-readable descriptions.
#AI role: capability vocabulary
#AI layer: ext
#AI badges: [vocabulary; extension; static]
#AI intro: `CapabilityVocabulary` defines the canonical set of capability slugs that SKIM extensions can declare. It provides a lookup method and a constant map for display or validation purposes.
#AI lifecycle: stateless, all data in class constant
#AI test_seam: none needed — pure constant lookup
#AI invariants: [TERMS is immutable; isKnown() never throws]
#AI core_behaviors: [isKnown() checks slug existence against TERMS constant]
#AI owns: TERMS constant
#AI entry_points: [isKnown]
#AI config_reads: []
#AI non_goals: [Does not enforce capability validity; Does not resolve capability providers]
#AI side_effects: []
#AI flow: isKnown($slug) -> array_key_exists($slug, TERMS)
#AI section_order: [Lookup API]

#AI:isKnown
#AI group: Lookup API
#AI frequency: low
#AI signature: public static function isKnown(string $capability): bool
#AI contract: Returns true when the given capability slug is present in the TERMS constant.
#AI param_details: [{name: $capability | type: string | required: true | desc: Capability slug to test, e.g. 'auth' or 'rate-limiting'.}]
#AI return_detail: {type: bool | desc: True if the slug is a recognized capability.}
