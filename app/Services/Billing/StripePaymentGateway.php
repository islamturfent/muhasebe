<?php

declare(strict_types=1);

namespace Muh\Services\Billing;

use Muh\Core\Config;

/**
 * Real Stripe payment gateway via the Stripe REST API (no SDK needed).
 *
 * createSubscription creates a Stripe Customer + Subscription using the plan's
 * configured Stripe Price ID (stripe_price_monthly_id / stripe_price_yearly_id).
 * Card details are never stored by the application — Stripe holds them.
 *
 * Enabled by: BILLING_PROVIDER=stripe + STRIPE_SECRET_KEY (+ webhook secret).
 */
final class StripePaymentGateway implements PaymentGateway
{
    private string $secretKey;
    private string $endpoint = 'https://api.stripe.com/v1';

    public function __construct(?string $secretKey = null)
    {
        $this->secretKey = $secretKey ?: (string) Config::get('billing.provider_keys.secret_key', '');
        // Allow overriding the API base URL (e.g. Stripe test mode uses the
        // same endpoint, but a mock/integration-test server can be injected).
        $ep = (string) Config::get('billing.provider_keys.endpoint', '');
        if ($ep !== '') {
            $this->endpoint = rtrim($ep, '/');
        }
    }

    public function configured(): bool
    {
        return $this->secretKey !== '';
    }

    public function createSubscription(array $tenant, array $plan, string $billingCycle): array
    {
        if (!$this->configured()) {
            throw new \RuntimeException('Stripe secret key not configured.');
        }
        $priceId = $billingCycle === 'yearly'
            ? ($plan['stripe_price_yearly_id'] ?? $plan['stripe_price_monthly_id'] ?? null)
            : ($plan['stripe_price_monthly_id'] ?? $plan['stripe_price_yearly_id'] ?? null);

        // Reuse an existing Stripe customer id if the tenant already has one,
        // otherwise create the customer on the provider side.
        $customerId = $tenant['provider_customer_id'] ?? null;
        if (!$customerId) {
            $customer = $this->request('POST', '/customers', [
                'email' => $tenant['email'] ?? '',
                'name' => $tenant['name'] ?? '',
                'metadata[tenant_id]' => (string) $tenant['id'],
            ]);
            $customerId = $customer['id'] ?? null;
        }

        $meta = [
            'metadata[tenant_id]' => (string) $tenant['id'],
            'metadata[plan]' => (string) ($plan['code'] ?? ''),
        ];
        $data = ['customer' => $customerId, 'items[0][price]' => $priceId] + $meta;
        $sub = $this->request('POST', '/subscriptions', $data);

        return [
            'provider_customer_id' => $customerId,
            'provider_subscription_id' => $sub['id'] ?? null,
            'status' => $sub['status'] === 'active' ? 'active' : 'past_due',
        ];
    }

    public function cancelSubscription(string $providerSubscriptionId): void
    {
        if ($providerSubscriptionId === '' || !$this->configured()) {
            return;
        }
        $this->request('DELETE', '/subscriptions/' . rawurlencode($providerSubscriptionId));
    }

    /**
     * Start a hosted Stripe Checkout Session for a subscription payment.
     * Card details / 3DS are handled by Stripe (PCI-compliant) on its domain;
     * the user is redirected back to $successUrl (with ?session_id=...) on
     * completion.
     */
    public function createCheckoutSession(array $tenant, array $plan, string $billingCycle, string $successUrl, string $cancelUrl): string
    {
        if (!$this->configured()) {
            return '';
        }
        $priceId = $billingCycle === 'yearly'
            ? ($plan['stripe_price_yearly_id'] ?? $plan['stripe_price_monthly_id'] ?? null)
            : ($plan['stripe_price_monthly_id'] ?? $plan['stripe_price_yearly_id'] ?? null);
        if (!$priceId) {
            throw new \RuntimeException('Stripe price id not configured for this plan.');
        }

        $form = [
            'mode' => 'subscription',
            'line_items[0][price]' => $priceId,
            'line_items[0][quantity]' => '1',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) ($tenant['id'] ?? ''),
            'customer_email' => (string) ($tenant['email'] ?? ''),
            'subscription_data[metadata][tenant_id]' => (string) ($tenant['id'] ?? ''),
            'subscription_data[metadata][plan]' => (string) ($plan['code'] ?? ''),
        ];
        $session = $this->request('POST', '/checkout/sessions', $form);
        return (string) ($session['url'] ?? '');
    }

    public function parseWebhook(array $payload): array
    {
        $type = (string) ($payload['type'] ?? 'unknown');
        $object = (array) ($payload['data']['object'] ?? []);
        $subscriptionId = (string) ($object['subscription'] ?? ($object['id'] ?? ''));
        $tenantId = (string) ($object['metadata']['tenant_id'] ?? ($payload['metadata']['tenant_id'] ?? ''));

        switch ($type) {
            case 'invoice.paid':
                $event = 'payment_succeeded';
                break;
            case 'invoice.payment_failed':
                $event = 'payment_failed';
                break;
            case 'checkout.session.completed':
                $event = 'subscription_created';
                break;
            case 'customer.subscription.deleted':
                $event = 'subscription_cancelled';
                break;
            default:
                $event = $type;
        }

        return [
            'event' => $event,
            'external_id' => $subscriptionId,
            'tenant_slug' => null,
            'plan_code' => null,
            'meta' => ['tenant_id' => $tenantId, 'raw' => $payload],
        ];
    }

    /**
     * Verify a Stripe webhook signature header (Stripe-Signature) using a
     * timestamp + HMAC-SHA256 over the raw body. Returns bool.
     */
    public function verifyWebhookSignature(string $signatureHeader, string $rawBody, string $webhookSecret): bool
    {
        if ($signatureHeader === '' || $webhookSecret === '') {
            return false;
        }
        // t=12345678,v1=hex[,v1=hex...]
        $parts = [];
        foreach (explode(',', $signatureHeader) as $pair) {
            [$k, $v] = explode('=', $pair, 2);
            $parts[$k] = $v;
        }
        $timestamp = $parts['t'] ?? '';
        $v1 = $parts['v1'] ?? '';
        if ($timestamp === '' || $v1 === '') {
            return false;
        }
        $signed = $timestamp . '.' . $rawBody;
        $expected = hash_hmac('sha256', $signed, $webhookSecret);
        return hash_equals($expected, $v1);
    }

    private function request(string $method, string $path, array $fields = []): array
    {
        $ch = curl_init($this->endpoint . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->secretKey],
            CURLOPT_TIMEOUT => 20,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded) || $code >= 400) {
            throw new \RuntimeException('Stripe API error (' . $code . '): ' . (is_string($raw) ? substr($raw, 0, 300) : ''));
        }
        return $decoded;
    }
}
