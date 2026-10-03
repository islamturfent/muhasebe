<?php

declare(strict_types=1);

namespace Muh\Services\Billing;

use Muh\Core\Config;
use Muh\Core\DB;
use Muh\Services\AuditLogService;

/**
 * Orchestrates subscriptions through the configured payment gateway and keeps
 * subscriptions in sync when webhooks arrive.
 */
final class BillingService
{
    private PaymentGateway $gateway;

    public function __construct(?PaymentGateway $gateway = null)
    {
        $this->gateway = $gateway ?? $this->resolveGateway();
    }

    private function resolveGateway(): PaymentGateway
    {
        $driver = Config::get('billing.provider', 'simulated');
        return match ($driver) {
            'stripe' => (new StripePaymentGateway())->configured()
                ? new StripePaymentGateway()
                : new SimulatedGateway(),
            default => new SimulatedGateway(),
        };
    }

    /**
     * Subscribe a tenant to a plan (called when the tenant chooses/pays a plan).
     * @return array [subscription_id, provider info]
     */
    public function subscribe(int $tenantId, int $planId, string $billingCycle = 'monthly'): array
    {
        $tenant = DB::first('SELECT * FROM tenants WHERE id = :id', ['id' => $tenantId]);
        $plan = DB::first('SELECT * FROM plans WHERE id = :id AND is_active = 1', ['id' => $planId]);
        if (!$tenant || !$plan) {
            throw new \Muh\Core\ValidationException(['plan' => __('subscription.plan_not_found')]);
        }

        // Cancel any existing subscription and create a new one.
        $existing = DB::first('SELECT * FROM subscriptions WHERE tenant_id = :t AND status IN (\'active\',\'trial\',\'past_due\') AND deleted_at IS NULL ORDER BY id DESC', ['t' => $tenantId]);
        if ($existing && $existing['provider_subscription_id']) {
            $this->gateway->cancelSubscription($existing['provider_subscription_id']);
        }

        $provider = $this->gateway->createSubscription($tenant, $plan, $billingCycle);

        $now = now();
        $id = (int) DB::insert('subscriptions', [
            'tenant_id' => $tenantId,
            'plan_id' => $planId,
            'status' => $provider['status'] ?? 'active',
            'starts_at' => $now,
            'billing_cycle' => $billingCycle,
            'payment_provider' => Config::get('billing.provider', 'simulated'),
            'provider_customer_id' => $provider['provider_customer_id'] ?? null,
            'provider_subscription_id' => $provider['provider_subscription_id'] ?? null,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        AuditLogService::record('subscription.subscribe', 'subscription', 'subscriptions', (string) $id, null, ['plan' => $plan['code'], 'cycle' => $billingCycle]);
        return ['subscription_id' => $id, 'provider' => $provider];
    }

    public function cancel(int $tenantId): void
    {
        $sub = DB::first('SELECT * FROM subscriptions WHERE tenant_id = :t AND status IN (\'active\',\'trial\',\'past_due\') AND deleted_at IS NULL ORDER BY id DESC', ['t' => $tenantId]);
        if (!$sub) {
            return;
        }
        if ($sub['provider_subscription_id']) {
            $this->gateway->cancelSubscription($sub['provider_subscription_id']);
        }
        DB::update('subscriptions', ['status' => 'cancelled', 'cancelled_at' => now()], 'id = :id', ['id' => $sub['id']]);
        AuditLogService::record('subscription.cancel', 'subscription', 'subscriptions', (string) $sub['id'], $sub, ['status' => 'cancelled']);
    }

    /**
     * Handle a webhook from the payment provider and update the subscription.
     * Returns a small result array for tracing.
     */
    public function handleWebhook(array $payload): array
    {
        $event = $this->gateway->parseWebhook($payload);
        $statusMap = [
            'payment.succeeded' => 'active',
            'invoice.past_due' => 'past_due',
            'subscription.cancelled' => 'cancelled',
            'subscription.expired' => 'expired',
        ];

        $newStatus = $statusMap[$event['event']] ?? null;
        if (!$newStatus) {
            return ['handled' => false, 'event' => $event['event']];
        }

        // Locate the subscription (by provider id, or by tenant slug passed in payload).
        $where = 'deleted_at IS NULL AND status <> :done';
        $params = ['done' => 'cancelled'];
        if ($event['external_id']) {
            $where .= ' AND provider_subscription_id = :ext';
            $params['ext'] = $event['external_id'];
        } elseif ($event['tenant_slug']) {
            $where .= ' AND tenant_id = (SELECT id FROM tenants WHERE slug = :slug)';
            $params['slug'] = $event['tenant_slug'];
        } else {
            return ['handled' => false, 'event' => $event['event'], 'reason' => 'no_identifier'];
        }

        $sub = DB::first('SELECT * FROM subscriptions WHERE ' . $where . ' ORDER BY id DESC', $params);
        if (!$sub) {
            return ['handled' => false, 'event' => $event['event'], 'reason' => 'not_found'];
        }

        DB::update('subscriptions', ['status' => $newStatus, 'updated_at' => now()], 'id = :id', ['id' => $sub['id']]);
        AuditLogService::record('subscription.webhook', 'subscription', 'subscriptions', (string) $sub['id'], $sub['status'], $newStatus, (int) $sub['tenant_id'], (int) $sub['tenant_id']);
        return ['handled' => true, 'event' => $event['event'], 'new_status' => $newStatus];
    }
}
