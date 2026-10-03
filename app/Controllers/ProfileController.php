<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\Controller;
use Muh\Core\DB;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;

/**
 * User profile & preferences (name, phone, locale, currency, 2FA status).
 */
final class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = Auth::user();
        $roles = \Muh\Models\User::rolesOf((int) $user['id']);
        $mfaEnabled = !empty($user['two_factor_enabled']) || !empty($user['two_factor_secret']);
        $activeCompany = Session::get('active_company_id');

        return $this->view('app.profile.profile', [
            'layout' => 'layouts.app',
            'user' => $user,
            'roles' => $roles,
            'mfaEnabled' => $mfaEnabled,
            'activeCompanyId' => $activeCompany,
            'errors' => Session::get('_form_errors', []),
        ]);
    }

    public function update(Request $request): Response
    {
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            Session::set('_form_errors', ['name' => __('validation.required')]);
            Session::flash('error', __('profile.invalid'));
            return Response::redirect('/app/profile');
        }
        $locale = in_array($request->input('locale'), ['tr', 'en'], true) ? $request->input('locale') : 'tr';
        $currency = in_array($request->input('currency'), ['TRY', 'USD', 'EUR', 'GBP'], true) ? $request->input('currency') : 'TRY';

        DB::update('users', [
            'name' => $name,
            'phone' => $request->input('phone') ?: null,
            'locale' => $locale,
            'currency' => $currency,
            'updated_at' => now(),
        ], 'id = :id', ['id' => (int) Auth::id()]);

        // Refresh the session user + active locale.
        Auth::loginById((int) Auth::id());
        Session::set('locale', $locale);

        Session::flash('success', __('profile.updated'));
        return Response::redirect('/app/profile');
    }
}
