<?php declare(strict_types=1);

namespace skim\ext;

final class capability_vocabulary {
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
        'file-storage'       => 'file upload and storage',
        'search'             => 'full-text search',
        'payments'           => 'payment processing',
        'notifications'      => 'multi-channel notifications',
    ];

    public static function is_known(string $capability): bool {
        return array_key_exists($capability, self::TERMS);
    }
}
