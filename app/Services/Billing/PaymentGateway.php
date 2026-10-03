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
     * Start a hosted checkout (e.g. Stripe Checkout Session) and return the
     * URL the user is redirected to for card payment/3DS. Returns '' if not
     * configured.
     */
    public function createCheckoutSession(array $tenant, array $plan, string $billingCycle, string $successUrl, string $cancelUrl): string;

    /**
     * Validate & normalize an incoming webhook payload.
     * Returns a normalized event: ['event' => string, 'external_id' => string|null, 'meta' => array]
     */
    public function parseWebhook(array $payload): array;
}
