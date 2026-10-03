<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\Controller;
use Muh\Core\DB;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;
use Muh\Core\Validator;
use Muh\Core\ValidationException;
use Muh\Services\AuditLogService;
use Muh\Services\TenantOnboardingService;

final class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        return $this->view('auth.login', ['layout' => 'layouts.guest']);
    }

    public function showRegister(Request $request): Response
    {
        return $this->view('auth.register', ['layout' => 'layouts.guest', 'request' => $request]);
    }

    public function login(Request $request): Response
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');
        $remember = (bool) $request->input('remember');

        $locale = $request->input('locale') ?: Session::get('locale');

        // Persist chosen language even on failure.
        if ($locale) {
            Session::set('locale', $locale);
        }

        $user = Auth::attempt($email, $password);

        AuditLogService::record(
            $user ? 'auth.login.success' : 'auth.login.failed',
            'auth',
            'users',
            $user ? (string) $user['id'] : null
        );

        if (!$user) {
            Session::flash('error', __('auth.login_failed'));
            return Response::redirect('/login');
        }

        // Persist the user's preferred locale.
        if (!empty($user['locale'])) {
            Session::set('locale', $user['locale']);
        }

        Session::set('login_user_id', (int) $user['id']);
        Session::forget('_fresh');

        // System administrators go straight to the super-admin panel.
        if (!empty($user['is_system_admin'])) {
            return Response::redirect('/admin');
        }
        $url = $request->input('redirect') ?? '/app/dashboard';
        return Response::redirect($url);
    }

    public function register(Request $request): Response
    {
        $service = new TenantOnboardingService();
        try {
            $result = $service->register($request->all(), $request->ip());
        } catch (ValidationException $e) {
            Session::set('_form_errors', $e->errors);
            Session::flash('error', __('auth.registration_failed'));
            return Response::redirect('/register');
        }

        if (!empty($request->input('locale'))) {
            Session::set('locale', $request->input('locale'));
        }

        // Auto-login the new owner and go to onboarding wizard.
        $email = strtolower(trim((string) $request->input('email', '')));
        $user = DB::first('SELECT id FROM users WHERE email = :e', ['e' => $email]);
        if ($user) {
            Auth::loginById((int) $user['id']);
        }
        Session::flash('success', __('auth.welcome'));
        return Response::redirect('/onboarding');
    }

    public function logout(Request $request): Response
    {
        $userId = Auth::id();
        AuditLogService::record('auth.logout', 'auth', 'users', $userId ? (string) $userId : null);
        Auth::logout();
        return Response::redirect('/');
    }
}
