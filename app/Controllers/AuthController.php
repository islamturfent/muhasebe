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
use Muh\Services\AuthTokenService;
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

        // Security policy gates (super admin).
        if (\Muh\Services\SecurityPolicyService::is('require_email_verify') && empty($user['email_verified_at'])) {
            Auth::logout();
            Session::flash('error', __('auth.verify_required'));
            return Response::redirect('/login');
        }
        if (\Muh\Services\SecurityPolicyService::is('require_2fa') && empty($user['two_factor_enabled']) && empty($user['is_system_admin'])) {
            Auth::logout();
            Session::flash('error', __('auth.2fa_required'));
            return Response::redirect('/login');
        }

        // Persist the user's preferred locale.
        if (!empty($user['locale'])) {
            Session::set('locale', $user['locale']);
        }

        // 'Beni Hatırla' (remember me): long-lived persistent login.
        if ($remember) {
            Auth::setRememberMe((int) $user['id']);
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
        Auth::clearRememberMe($userId);
        Auth::logout();
        return Response::redirect('/');
    }

    // ---- Forgot password ----
    public function showForgot(Request $request): Response
    {
        return $this->view('auth.forgot', ['layout' => 'layouts.guest']);
    }

    public function sendResetLink(Request $request): Response
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        $user = DB::first('SELECT id, email FROM users WHERE email = :e AND deleted_at IS NULL', ['e' => $email]);
        if ($user) {
            $token = AuthTokenService::create($email, 'reset', 60);
            $link = url('/reset-password?token=' . $token . '&email=' . urlencode($email));
            (new \Muh\Services\Mailer())->send(
                $email,
                __('auth.reset_mail_subject'),
                '<p>' . e(__('auth.reset_mail_body')) . '</p><p><a href="' . e($link) . '">' . e(__('auth.reset_mail_button')) . '</a></p><p><a href="' . e($link) . '">' . e($link) . '</a></p>'
            );
            AuditLogService::record('auth.password.forgot', 'auth', 'users', (string) $user['id']);
        }
        // Always show generic success (avoid user enumeration).
        Session::flash('success', __('auth.reset_sent'));
        return Response::redirect('/login');
    }

    // ---- Reset password ----
    public function showReset(Request $request): Response
    {
        return $this->view('auth.reset', [
            'layout' => 'layouts.guest',
            'token' => (string) $request->query('token', ''),
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request): Response
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        $token = (string) $request->input('token', '');
        $password = (string) $request->input('password', '');
        $confirm = (string) $request->input('password_confirmation', '');

        if ($password !== $confirm || mb_strlen($password) < (int) config('app.security.password_min_length', 8)) {
            Session::flash('error', __('auth.password_short'));
            return Response::redirect('/reset-password?token=' . urlencode($token) . '&email=' . urlencode($email));
        }
        if (!AuthTokenService::consume($email, $token, 'reset')) {
            Session::flash('error', __('auth.reset_invalid'));
            return Response::redirect('/forgot-password');
        }
        $updated = DB::update('users', ['password' => \Muh\Core\Hash::make($password), 'email_verified_at' => now(), 'updated_at' => now()], 'email = :e AND deleted_at IS NULL', ['e' => $email]);
        if (!$updated) {
            Session::flash('error', __('auth.reset_invalid'));
            return Response::redirect('/forgot-password');
        }
        AuditLogService::record('auth.password.reset', 'auth', 'users', null, null, ['email' => $email]);
        Session::flash('success', __('auth.reset_done'));
        return Response::redirect('/login');
    }

    // ---- E-mail verification ----
    public function verifyEmail(Request $request, $token): Response
    {
        $email = strtolower((string) ($request->query('email') ?? $request->input('email')));
        $user = DB::first('SELECT id, email FROM users WHERE email = :e AND deleted_at IS NULL', ['e' => $email]);
        if (!$user || !AuthTokenService::consume($email, (string) $token, 'verify')) {
            Session::flash('error', __('auth.verify_invalid'));
            return Response::redirect('/login');
        }
        DB::update('users', ['email_verified_at' => now(), 'updated_at' => now()], 'id = :id', ['id' => (int) $user['id']]);
        Session::flash('success', __('auth.verify_done'));
        return Response::redirect('/login');
    }
}
