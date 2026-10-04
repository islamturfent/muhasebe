<?php

declare(strict_types=1);

namespace Muh\Middleware;

use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Services\SecurityPolicyService;

/**
 * Rate-limits the payment webhook only when the super admin has enabled the
 * 'rate_limit_webhooks' security policy (otherwise passes through unchanged).
 */
final class WebhookRateLimitMiddleware extends Middleware
{
    public function handle(Request $request): ?Response
    {
        if (!SecurityPolicyService::is('rate_limit_webhooks')) {
            return null;
        }
        return (new RateLimitMiddleware())->handle($request);
    }
}
