<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\Controller;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;
use Muh\Core\ValidationException;
use Muh\Services\UserInviteService;

/**
 * Public invite acceptance (guest flow).
 *
 * The office owner/manager e-mails a token link that points here. The invitee
 * verifies their identity, sets an account password (if new), and is taken
 * straight into the dashboard.
 */
final class InviteController extends Controller
{
    public function acceptForm(Request $request): Response
    {
        $token = (string) $request->query('token', '');
        $invite = (new UserInviteService())->validByToken($token);
        if (!$invite) {
            return $this->view('app.users.invalid-invite', ['layout' => 'layouts.guest']);
        }
        $exists = (bool) \Muh\Core\DB::first(
            'SELECT id FROM users WHERE tenant_id = :t AND email = :e AND deleted_at IS NULL',
            ['t' => (int) $invite['tenant_id'], 'e' => strtolower(trim($invite['email']))]
        );
        $office = \Muh\Core\DB::first('SELECT name FROM tenants WHERE id = :id', ['id' => (int) $invite['tenant_id']]);
        return $this->view('app.users.accept', [
            'layout' => 'layouts.guest',
            'invite' => $invite,
            'office' => $office,
            'exists' => $exists,
            'errors' => Session::get('_form_errors', []),
        ]);
    }

    public function accept(Request $request): Response
    {
        $token = (string) $request->input('token', '');
        $service = new UserInviteService();
        $invite = $service->validByToken($token);
        if (!$invite) {
            Session::flash('error', __('user.invite_invalid'));
            return Response::redirect('/');
        }

        try {
            $user = $service->accept($invite, $request->all());
        } catch (ValidationException $e) {
            Session::set('_form_errors', $e->errors);
            return Response::redirect('/invite/accept?token=' . urlencode($token));
        }

        Auth::loginById((int) $user['id']);
        Session::flash('success', __('user.invite_welcome'));
        return Response::redirect('/app/dashboard');
    }
}
