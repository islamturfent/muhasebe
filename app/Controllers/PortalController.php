<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\Controller;
use Muh\Core\DB;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;
use Muh\Services\CurrentContextService;

/**
 * Müşteri / Firma Yetkilisi Portalı: firma yetkilisi (client_authorized/viewer)
 * buradan yalnızca kendisine atanan firmaları görür; her firmada yalnızca okuma
 * için fatura / cari / rapor sayfalarına hızlı erişim sunar.
 */
final class PortalController extends Controller
{
    public function index(Request $request): Response
    {
        Auth::requireCan('dashboard.view');
        $companies = CurrentContextService::companiesForUser();
        $cards = [];
        foreach ($companies as $c) {
            $openInvoices = (float) DB::scalar(
                "SELECT COALESCE(SUM(total - paid),0) FROM invoices
                  WHERE company_id = :c AND status = 'posted' AND deleted_at IS NULL",
                ['c' => (int) $c['id']]
            );
            $cari = (float) DB::scalar(
                "SELECT COALESCE(SUM(balance),0) FROM current_accounts
                  WHERE company_id = :c AND deleted_at IS NULL",
                ['c' => (int) $c['id']]
            );
            $cards[] = [
                'id' => (int) $c['id'],
                'name' => $c['name'],
                'trade_name' => $c['trade_name'] ?? '',
                'currency' => $c['currency'] ?? 'TRY',
                'open_invoices' => $openInvoices,
                'cari_balance' => $cari,
                'invoice_count' => (int) DB::scalar('SELECT COUNT(*) FROM invoices WHERE company_id = :c AND deleted_at IS NULL', ['c' => (int) $c['id']]),
            ];
        }
        return $this->view('app.portal.index', [
            'layout' => 'layouts.app',
            'cards' => $cards,
        ]);
    }
}
