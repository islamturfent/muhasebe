<?php

return [
    // Payment provider driver: simulated | (future) stripe | iyzico | paytr ...
    'provider' => getenv('BILLING_PROVIDER') ?: 'simulated',

    // Currency used for subscriptions.
    'currency' => 'TRY',

    // Webhook signature secret (used by real providers).
    'webhook_secret' => getenv('BILLING_WEBHOOK_SECRET') ?: 'muh-webhook-secret-change-me',

    // Provider-specific keys (used by real drivers, e.g. stripe).
    'provider_keys' => [
        // Stripe
        'secret_key' => getenv('STRIPE_SECRET_KEY') ?: '',
        'publishable_key' => getenv('STRIPE_PUBLISHABLE_KEY') ?: '',
    ],

    // Trial length for new offices (days).
    'trial_days' => 30,
];
