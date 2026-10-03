<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\DB;
use Muh\Core\Controller;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;
use Muh\Services\PlanLimitsService;
use Muh\Services\Billing\BillingService;
use Muh\Models\Plan;

/**
 * Subscription management (Phase 10).
 */
final class SubscriptionController extends Controller
{
    public function index(Request $request): Response
    {
        Auth::requireCan('subscription.view');
        $plan = PlanLimitsService::currentPlan();
        $usage = PlanLimitsService::usage();
        $features = PlanLimitsService::features();
        $plans = Plan::allActive();

        return $this->view('app.settings.subscription', [
            'layout' => 'layouts.app',
            'plan' => $plan,
            'usage' => $usage,
            'features' => $features,
            'plans' => $plans,
        ]);
    }

    public function subscribe(Request $request): Response
    {
        Auth::requireCan('subscription.update');
        $planCode = $request->input('plan');
        $plan = Plan::findByCode((string) $planCode);
        if (!$plan) {
            Session::flash('error', __('subscription.plan_not_found'));
            return Response::redirect('/app/settings/subscription');
        }

        $billing = new BillingService();
        $billing->subscribe(Auth::tenantId(), (int) $plan['id'], $request->input('cycle', 'monthly'));
        Session::flash('success', __('subscription.subscribed'));
        return Response::redirect('/app/settings/subscription');
    }
}
