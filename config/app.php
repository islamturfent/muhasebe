<?php

declare(strict_types=1);

return [
    'name'        => 'Hesap360',
    'env'         => getenv('APP_ENV') ?: 'local',
    'debug'       => (getenv('APP_DEBUG') ?: 'true') === 'true',
    'maintenance' => (getenv('APP_MAINTENANCE') ?: 'false') === 'true',
    'url'         => getenv('APP_URL') ?: 'http://localhost/muh',
    'https'       => (getenv('APP_HTTPS') ?: 'false') === 'true', // true behind HTTPS (production)
    'timezone'    => 'Europe/Istanbul',
    'locale'      => 'tr',
    'fallback_locale' => 'tr',
    'supported_locales' => ['tr', 'en'],
    'key'         => getenv('APP_KEY') ?: 'base64:MUH-dummy-key-change-me-0123456789abcdef',
    'version'     => '0.1.0',

    // Default currency for Turkey-focused accounting.
    'default_currency' => 'TRY',
    'default_country'  => 'TR',

    'session' => [
        'lifetime' => 480,      // minutes — default (may be overridden per-platform by the super admin panel)
        'name'     => 'muh_session',
        'secure'   => (getenv('SESSION_SECURE') ?: 'false') === 'true', // true behind HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ],

    'security' => [
        'password_min_length' => 8,
        'login_max_attempts'  => 5,
        'login_lockout_minutes' => 15,
        'mfa_enabled'         => false,
        'mfa_issuer'          => 'MUH Accounting',
        'rate_limit'          => [
            'enabled'   => true,
            'max'       => 120,        // requests per window
            'window'    => 60,         // seconds
        ],
    ],

    'mail' => [
        'enabled' => (getenv('MAIL_ENABLED') ?: 'false') === 'true',
        'from'    => getenv('MAIL_FROM') ?: 'Hesap360 <no-reply@muh.local>',
        'host'        => getenv('MAIL_HOST') ?: '',
        'port'        => (int) (getenv('MAIL_PORT') ?: 587),
        'username'    => getenv('MAIL_USERNAME') ?: '',
        'password'    => getenv('MAIL_PASSWORD') ?: '',
        'encryption'  => getenv('MAIL_ENCRYPTION') ?: 'tls', // tls|ssl|none
    ],

    'filesystem' => [
        'documents' => dirname(__DIR__) . '/storage/documents',
        'logs'      => dirname(__DIR__) . '/storage/logs',
    ],
];
