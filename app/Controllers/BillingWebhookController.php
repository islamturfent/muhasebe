<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Controller;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Services\Billing\BillingService;

/**
 * Billing webhook receiver — called by the payment provider. Updates the
 * subscription automatically when payment succeeds / lapses / cancels.
 */
final class BillingWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        // Verify provider signature when enabled (real providers).
        if (config('billing.webhook_secret', '')) {
            $signature = $request->header('X-Webhook-Signature') ?? '';
            $expected = hash_hmac('sha256', (string) json_encode($request->all()), config('billing.webhook_secret'));
            if ($signature !== $expected && config('app.env', 'local') === 'production') {
                return Response::json(['error' => 'invalid_signature'], 401);
            }
        }

        $billing = new BillingService();
        $result = $billing->handleWebhook($request->all());
        return $this->json($result, ($result['handled'] ?? false) ? 200 : 202);
    }
}
