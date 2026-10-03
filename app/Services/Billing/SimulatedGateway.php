<?php

declare(strict_types=1);

namespace Muh\Services\Billing;

/**
 * Default provider for local/dev: simulates provider behaviour so the full
 * subscription → webhook → auto-activation flow can be exercised without a
 * real PSP. Swap with a real adapter later.
 */
final class SimulatedGateway implements PaymentGateway
{
    public function createSubscription(array $tenant, array $plan, string $billingCycle): array
    {
        return [
            'provider_customer_id' => 'cust_' . $tenant['slug'] . '_' . random_int(1000, 9999),
            'provider_subscription_id' => 'sub_' . md5($tenant['id'] . $plan['code']) . '_' . random_int(100, 999),
            'status' => 'active',
        ];
    }

    public function cancelSubscription(string $providerSubscriptionId): void
    {
        // no-op in simulation
    }

    public function createCheckoutSession(array $tenant, array $plan, string $billingCycle, string $successUrl, string $cancelUrl): string
    {
        // Simulated provider: treat the payment as completed immediately and
        // send the user straight to the success URL.
        return $successUrl;
    }

    public function parseWebhook(array $payload): array
    {
        $event = $payload['event'] ?? $payload['type'] ?? 'unknown';
        return [
            'event' => (string) $event,
            'external_id' => isset($payload['subscription_id']) ? (string) $payload['subscription_id'] : null,
            'tenant_slug' => $payload['tenant_slug'] ?? null,
            'plan_code' => $payload['plan_code'] ?? null,
            'meta' => $payload,
        ];
    }
}
