<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\DB;
use Muh\Core\Session;
use Muh\Core\Controller;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\TwoFactorAuth;

/**
 * Security settings: 2FA/MFA management (Phase 12).
 */
final class SecurityController extends Controller
{
    public function show(Request $request): Response
    {
        Auth::requireCan('setting.read');
        $user = Auth::user();

        return $this->view('app.settings.security', [
            'layout' => 'layouts.app',
            'two_factor_enabled' => !empty($user['two_factor_enabled']),
            'two_factor_secret' => Session::get('mfa_pending_secret'),
            'two_factor_qr' => Session::get('mfa_pending_qr'),
            'email' => $user['email'] ?? '',
            'recent_audit' => DB::select(
                'SELECT a.*, u.name AS user_name FROM audit_logs a
                  LEFT JOIN users u ON u.id = a.user_id
                 WHERE a.tenant_id = :t ORDER BY a.id DESC LIMIT 15',
                ['t' => Auth::tenantId()]
            ),
        ]);
    }

    public function enableMfa(Request $request): Response
    {
        Auth::requireCan('setting.update');
        $secret = TwoFactorAuth::generateSecret();
        $email = (string) ($request->input('email') ?: (Auth::user()['email'] ?? ''));
        $qr = TwoFactorAuth::provisioningUri($secret, $email);
        Session::set('mfa_pending_secret', $secret);
        Session::set('mfa_pending_qr', $qr);
        return Response::redirect('/app/settings/security');
    }

    public function verifyMfa(Request $request): Response
    {
        Auth::requireCan('setting.update');
        $secret = (string) Session::get('mfa_pending_secret');
        if ($secret === '') {
            return Response::redirect('/app/settings/security');
        }
        if (!TwoFactorAuth::verify($secret, (string) $request->input('code'))) {
            Session::flash('error', __('security.invalid_code'));
            return Response::redirect('/app/settings/security');
        }
        DB::update('users', [
            'two_factor_secret' => $secret,
            'two_factor_enabled' => 1,
        ], 'id = :id', ['id' => Auth::id()]);
        Session::forget('mfa_pending_secret');
        Session::forget('mfa_pending_qr');
        Session::flash('success', __('security.mfa_enabled'));
        return Response::redirect('/app/settings/security');
    }

    public function disableMfa(Request $request): Response
    {
        Auth::requireCan('setting.update');
        DB::update('users', ['two_factor_secret' => null, 'two_factor_enabled' => 0], 'id = :id', ['id' => Auth::id()]);
        Session::flash('success', __('security.mfa_disabled'));
        return Response::redirect('/app/settings/security');
    }
}
