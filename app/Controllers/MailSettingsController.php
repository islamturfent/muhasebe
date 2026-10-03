<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\Controller;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;
use Muh\Services\MailSettingService;
use Muh\Services\Mailer;

/**
 * E-posta (SMTP) bildirim ayarları — bağlantı, gönderen ve bildirim tipleri.
 */
final class MailSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        Auth::requireCan('settings.update');
        $tenantId = Auth::tenantId();
        $settings = MailSettingService::get($tenantId);

        // Read the tail of the mail log for inspection.
        $logPath = config('app.filesystem.logs', dirname(__DIR__, 2) . '/storage/logs') . '/mail.log';
        $logLines = [];
        if (is_file($logPath)) {
            $lines = array_slice(file($logPath, FILE_IGNORE_NEW_LINES) ?: [], -20);
            foreach ($lines as $l) {
                if (trim($l) !== '') {
                    $logLines[] = $l;
                }
            }
        }

        return $this->view('app.settings.email', [
            'layout' => 'layouts.app',
            'settings' => $settings,
            'logLines' => $logLines,
        ]);
    }

    public function save(Request $request): Response
    {
        Auth::requireCan('settings.update');
        MailSettingService::save(Auth::tenantId(), $request->all());
        Session::flash('success', __('mail.saved'));
        return Response::redirect('/app/settings/email');
    }

    public function test(Request $request): Response
    {
        Auth::requireCan('settings.update');
        $tenantId = Auth::tenantId();
        $to = (string) $request->input('test_email');
        if ($to === '') {
            $to = MailSettingService::tenantEmail($tenantId);
        }
        $opts = MailSettingService::mailerOptions($tenantId);
        $sent = (new Mailer())->send($to, __('mail.test_subject'), __('mail.test_body'), $opts);
        Session::flash($sent ? 'success' : 'error', __('mail.test_sent', ['to' => $to]));
        return Response::redirect('/app/settings/email');
    }
}
