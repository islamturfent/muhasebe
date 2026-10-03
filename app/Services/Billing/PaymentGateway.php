<?php

declare(strict_types=1);

namespace Muh\Services\Billing;

/**
 * Payment gateway abstraction. Concrete providers (Stripe, iyzico, PayTR, ...)
 * can implement this without touching core logic. Card numbers are never stored
 * by the application.
 */
interface PaymentGateway
{
    /**
     * Create/update a subscription for a tenant against a plan.
     * Returns provider identifiers.
     */
    public function createSubscription(array $tenant, array $plan, string $billingCycle): array;

    /** Cancel a subscription (called on upgrade-downgrade/cancel). */
    public function cancelSubscription(string $providerSubscriptionId): void;

    /**
     * Validate & normalize an incoming webhook payload.
     * Returns a normalized event: ['event' => string, 'external_id' => string|null, 'meta' => array]
     */
    public function parseWebhook(array $payload): array;
}
