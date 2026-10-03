<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\Controller;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;
use Muh\Services\AuditLogService;
use Muh\Services\EFaturaService;

/**
 * Settings landing page — links to the various settings sub-sections.
 */
final class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $sections = [
            ['/app/settings/security', 'security.title', '🔐'],
            ['/app/settings/subscription', 'subscription.title', '💳'],
            ['/app/users', 'user.title', '👥'],
            ['/app/tax-rates', 'taxrate.title', '🧾'],
            ['/app/audit', 'audit.title', '📋'],
            ['/app/documents', 'document.title', '📁'],
            ['/app/settings/email', 'mail.title', '✉️'],
            ['/app/settings/efatura', 'efatura.settings_title', '🧾'],
            ['/app/settings/currency', 'currency.title', '💱'],
        ];

        return $this->view('app.settings.index', [
            'layout' => 'layouts.app',
            'sections' => $sections,
        ]);
    }

    /** e-Fatura entegratör ayarları ekranı. */
    public function efatura(Request $request): Response
    {
        Auth::requireCan('settings.update');
        return $this->view('app.settings.efatura', [
            'layout' => 'layouts.app',
            'cfg' => EFaturaService::tenantSettings(),
            'providers' => ['simulated' => 'efatura.provider_simulated'] + EFaturaService::supportedProviders(),
        ]);
    }

    public function saveEfatura(Request $request): Response
    {
        Auth::requireCan('settings.update');
        EFaturaService::saveTenantConfig($request->all());
        AuditLogService::record('efatura.settings.save', 'efatura', 'settings', null, null, ['provider' => $request->input('provider')], null, (int) Auth::tenantId());
        Session::flash('success', __('efatura.settings_saved'));
        return Response::redirect('/app/settings/efatura');
    }

    // ---- Para birimleri & döviz kurları ----
    public function currency(Request $request): Response
    {
        Auth::requireCan('settings.read');
        $today = date('Y-m-d');
        $list = \Muh\Services\CurrencyService::all();
        return $this->view('app.settings.currency', [
            'layout' => 'layouts.app',
            'currencies' => $list,
            'today' => $today,
        ]);
    }

    public function saveCurrency(Request $request): Response
    {
        Auth::requireCan('settings.update');
        $date = $request->input('date') ? (string) $request->input('date') : date('Y-m-d');
        foreach ($request->input('rates', []) as $code => $rate) {
            $code = strtoupper((string) $code);
            if ($code === 'TRY') {
                continue;
            }
            $rate = (float) str_replace(',', '.', (string) $rate);
            if ($rate > 0) {
                \Muh\Services\CurrencyService::setRate($code, 'TRY', $rate, $date);
            }
        }
        \Muh\Services\AuditLogService::record('settings.currency.save', 'settings', 'exchange_rates', null, null, ['date' => $date]);
        Session::flash('success', __('currency.saved'));
        return Response::redirect('/app/settings/currency');
    }
}
