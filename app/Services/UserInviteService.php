<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\DB;
use Muh\Core\Hash;
use Muh\Core\Validator;
use Muh\Core\ValidationException;
use Muh\Models\User;

/**
 * User invitation lifecycle (Phase 19 — Kullanıcı Davet Sistemi).
 *
 * An office owner/manager invites a person by e-mail with a role and optional
 * set of client companies. The invitee accepts via a token link; upon
 * acceptance their account is created (or linked if it already exists in the
 * tenant), the role and company access are attached, and they are logged in.
 */
final class UserInviteService
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_CANCELLED = 'cancelled';
    public const DAYS_VALID = 7;

    /**
     * Create an invitation.
     * @return string invite token
     */
    public function create(int $tenantId, int $invitedBy, string $email, int $roleId, array $companyIds = [], int $expiresDays = self::DAYS_VALID): string
    {
        $email = strtolower(trim($email));

        $v = new Validator();
        if (!$v->validate(['email' => $email], ['email' => 'required|email'])) {
            throw new ValidationException($v->errors());
        }

        // Role must exist.
        if (!DB::first('SELECT id FROM roles WHERE id = :id', ['id' => $roleId])) {
            throw new ValidationException(['role_id' => __('validation.in')]);
        }

        // Email should not already belong to a user of this tenant.
        if (DB::first('SELECT id FROM users WHERE tenant_id = :t AND email = :e AND deleted_at IS NULL', ['t' => $tenantId, 'e' => $email])) {
            throw new ValidationException(['email' => __('user.email_exists')]);
        }

        // No live pending invite for the same e-mail in this tenant.
        $pending = DB::first(
            'SELECT id FROM user_invites WHERE tenant_id = :t AND email = :e AND status = :s AND expires_at > :n',
            ['t' => $tenantId, 'e' => $email, 's' => self::STATUS_PENDING, 'n' => now()]
        );
        if ($pending) {
            throw new ValidationException(['email' => __('user.invite_pending')]);
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $expiresDays . ' days'));

        $inviteId = (int) DB::insert('user_invites', [
            'tenant_id'   => $tenantId,
            'invited_by'  => $invitedBy,
            'email'       => $email,
            'token'       => $token,
            'role_id'     => $roleId,
            'status'      => self::STATUS_PENDING,
            'expires_at'  => $expiresAt,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Store which companies to grant on acceptance.
        foreach (array_unique(array_map('intval', $companyIds)) as $cid) {
            if ($cid > 0 && DB::first('SELECT id FROM companies WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL', ['id' => $cid, 't' => $tenantId])) {
                DB::insert('user_invite_company', [
                    'invite_id' => $inviteId, 'company_id' => $cid,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        $office = DB::first('SELECT name FROM tenants WHERE id = :id', ['id' => $tenantId]);
        $acceptUrl = \url('/invite/accept?token=' . urlencode($token));

        // In-app notification for the office owner/manager.
        (new NotificationService())->create(
            'user_invite',
            __('user.invite_title', ['office' => $office['name'] ?? '']),
            __('user.invite_body', ['email' => $email]),
            'info', null, null, '/app/users'
        );

        // E-mail.
        $subject = __('user.invite_email_subject', ['office' => $office['name'] ?? '']);
        $html = '<p>' . e(__('user.invite_email_greeting', ['office' => $office['name'] ?? ''])) . '</p>'
              . '<p><a href="' . e($acceptUrl) . '">' . e(__('user.invite_email_cta')) . '</a></p>'
              . '<p>' . e(__('user.invite_email_expiry', ['days' => $expiresDays])) . '</p>';
        (new Mailer())->send($email, $subject, $html);

        AuditLogService::record('user.invite.create', 'user', 'user_invites', (string) $inviteId, null, [
            'email' => $email, 'role_id' => $roleId, 'companies' => array_values(array_unique(array_map('intval', $companyIds))),
        ]);

        return $token;
    }

    /** Fetch a valid (pending, unexpired) invite by token, or null. */
    public function validByToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        return DB::first(
            'SELECT * FROM user_invites WHERE token = :t AND status = :s AND expires_at > :n',
            ['t' => $token, 's' => self::STATUS_PENDING, 'n' => now()]
        );
    }

    /** Companies granted by an invite (by invite id). */
    public function inviteCompanies(int $inviteId): array
    {
        return DB::select(
            'SELECT c.* FROM companies c
              JOIN user_invite_company ic ON ic.company_id = c.id
             WHERE ic.invite_id = :i AND c.deleted_at IS NULL',
            ['i' => $inviteId]
        );
    }

    /**
     * Accept an invitation.
     * If no user exists for the invite e-mail in the tenant, one is created
     * with the supplied name/password. Role + companies are attached and the
     * user is logged in.
     *
     * @return array user
     */
    public function accept(array $invite, array $data): array
    {
        $tenantId = (int) $invite['tenant_id'];
        $email = strtolower(trim($invite['email']));
        $roleId = (int) $invite['role_id'];

        $user = DB::first('SELECT * FROM users WHERE tenant_id = :t AND email = :e AND deleted_at IS NULL', ['t' => $tenantId, 'e' => $email]);

        if ($user) {
            // Link existing user.
            User::assignRole((int) $user['id'], $roleId);
            $this->attachInvitedCompanies((int) $invite['id'], (int) $user['id']);
            $this->markAccepted((int) $invite['id']);
            return $user;
        }

        // Create the account.
        $v = new Validator();
        if (!$v->validate($data, ['name' => 'required|min:2', 'password' => 'required|min:8'])) {
            throw new ValidationException($v->errors());
        }

        $locale = DB::first('SELECT locale, currency FROM tenants WHERE id = :id', ['id' => $tenantId]);
        $userId = (int) DB::insert('users', [
            'tenant_id' => $tenantId,
            'name'      => $data['name'],
            'email'     => $email,
            'phone'     => null,
            'password'  => Hash::make($data['password']),
            'locale'    => $locale['locale'] ?? 'tr',
            'currency'  => $locale['currency'] ?? 'TRY',
            'status'    => 'active',
            'is_owner'  => 0,
            'is_system_admin' => 0,
            'created_at'=> now(),
            'updated_at'=> now(),
        ]);

        User::assignRole($userId, $roleId);
        $this->attachInvitedCompanies((int) $invite['id'], $userId);
        $this->markAccepted((int) $invite['id']);

        AuditLogService::record('user.invite.accepted', 'user', 'users', (string) $userId, null, ['email' => $email]);

        $newUser = DB::first('SELECT * FROM users WHERE id = :id', ['id' => $userId]);
        return $newUser;
    }

    private function attachInvitedCompanies(int $inviteId, int $userId): void
    {
        foreach ($this->inviteCompanies($inviteId) as $c) {
            DB::execute(
                'INSERT IGNORE INTO user_company (user_id, company_id, created_at, updated_at) VALUES (:u, :c, :c1, :u1)',
                ['u' => $userId, 'c' => $c['id'], 'c1' => now(), 'u1' => now()]
            );
        }
    }

    private function markAccepted(int $inviteId): void
    {
        DB::execute(
            'UPDATE user_invites SET status = :s, accepted_at = :n1, updated_at = :n2 WHERE id = :id',
            ['s' => self::STATUS_ACCEPTED, 'n1' => now(), 'n2' => now(), 'id' => $inviteId]
        );
    }

    /** Cancel a pending invite. */
    public function cancel(int $inviteId): void
    {
        DB::execute(
            'UPDATE user_invites SET status = :s, updated_at = :n1 WHERE id = :id AND status = :p',
            ['s' => self::STATUS_CANCELLED, 'n1' => now(), 'id' => $inviteId, 'p' => self::STATUS_PENDING]
        );
    }
}
