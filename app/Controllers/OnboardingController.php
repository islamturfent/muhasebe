<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\Controller;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;
use Muh\Core\ValidationException;
use Muh\Services\TenantOnboardingService;

/**
 * Registration onboarding wizard for a new accounting office.
 */
final class OnboardingController extends Controller
{
    public function index(Request $request): Response
    {
        $step = (int) $request->query('step', 1);
        $steps = ['office', 'first_company', 'invite', 'done'];
        $step = max(1, min(count($steps), $step));
        return $this->view('onboarding.index', [
            'layout' => 'layouts.guest',
            'step'   => $step,
            'steps'  => $steps,
            'errors' => Session::get('_form_errors', []),
        ]);
    }

    public function storeCompany(Request $request): Response
    {
        $service = new TenantOnboardingService();
        try {
            $service->createFirstCompany($request->all(), Auth::tenantId());
        } catch (ValidationException $e) {
            Session::set('_form_errors', $e->errors);
            return Response::redirect('/onboarding?step=2');
        }
        Session::flash('success', __('onboarding.company_created'));
        return Response::redirect('/onboarding?step=3');
    }

    public function complete(Request $request): Response
    {
        Session::forget('_form_errors');
        Session::flash('success', __('onboarding.complete'));
        return Response::redirect('/app/dashboard');
    }
}
