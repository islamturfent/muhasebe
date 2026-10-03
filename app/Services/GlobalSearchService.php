<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\Auth;
use Muh\Core\DB;

/**
 * Global search across all major entities, always scoped to the current tenant.
 * Returns grouped results with label + url for each hit.
 *
 * Uses positional `?` placeholders (native prepares allow repeating them).
 */
final class GlobalSearchService
{
    public function search(?string $query): array
    {
        $tenantId = Auth::tenantId();
        $q = trim((string) $query);
        if ($q === '') {
            return [];
        }
        $like = '%' . $q . '%';
        $out = [];
        $limit = 10;

        $out['company'] = [
            'label' => __('nav.companies'),
            'items' => array_map(fn ($r) => ['title' => $r['name'], 'sub' => $r['tax_number'] ?? '', 'url' => '/app/companies/' . $r['id']], DB::select(
                'SELECT * FROM companies WHERE tenant_id = ? AND deleted_at IS NULL AND (name LIKE ? OR tax_number LIKE ?) ORDER BY name LIMIT ' . $limit,
                [$tenantId, $like, $like]
            )),
        ];

        $out['current_account'] = [
            'label' => __('nav.current_accounts'),
            'items' => array_map(fn ($r) => ['title' => $r['name'], 'sub' => $r['code'], 'url' => '/app/current-accounts/' . $r['id']], DB::select(
                'SELECT * FROM current_accounts WHERE tenant_id = ? AND deleted_at IS NULL AND (name LIKE ? OR code LIKE ? OR tax_number LIKE ?) ORDER BY name LIMIT ' . $limit,
                [$tenantId, $like, $like, $like]
            )),
        ];

        $out['invoice'] = [
            'label' => __('nav.invoices'),
            'items' => array_map(fn ($r) => ['title' => $r['number'], 'sub' => __('invoice.type_' . $r['type']) . ' · ' . money($r['total']), 'url' => '/app/invoices/' . $r['id']], DB::select(
                'SELECT * FROM invoices WHERE tenant_id = ? AND deleted_at IS NULL AND (number LIKE ? OR document_no LIKE ?) ORDER BY id DESC LIMIT ' . $limit,
                [$tenantId, $like, $like]
            )),
        ];

        $out['product'] = [
            'label' => __('inventory.products'),
            'items' => array_map(fn ($r) => ['title' => $r['name'], 'sub' => $r['code'] . ' · ' . ((float) $r['stock_quantity']) . ' adet', 'url' => '/app/inventory/' . $r['id']], DB::select(
                'SELECT * FROM products WHERE tenant_id = ? AND deleted_at IS NULL AND (name LIKE ? OR code LIKE ? OR barcode LIKE ?) ORDER BY name LIMIT ' . $limit,
                [$tenantId, $like, $like, $like]
            )),
        ];

        $out['entry'] = [
            'label' => __('accounting.entry'),
            'items' => array_map(fn ($r) => ['title' => __('accounting.number') . ' ' . $r['number'], 'sub' => $r['description'] . ' · ' . money($r['debit_total']), 'url' => '/app/accounting/journal'], DB::select(
                'SELECT * FROM accounting_entries WHERE tenant_id = ? AND deleted_at IS NULL AND (number LIKE ? OR description LIKE ?) ORDER BY id DESC LIMIT ' . $limit,
                [$tenantId, $like, $like]
            )),
        ];

        $out['bank'] = [
            'label' => __('nav.bank'),
            'items' => array_map(fn ($r) => ['title' => ($r['account_name'] ?? $r['bank_name']), 'sub' => $r['bank_name'] . ' · ' . ($r['iban'] ?? ''), 'url' => '/app/bank/' . $r['id']], DB::select(
                'SELECT * FROM bank_accounts WHERE tenant_id = ? AND deleted_at IS NULL AND (bank_name LIKE ? OR account_name LIKE ? OR iban LIKE ?) ORDER BY id LIMIT ' . $limit,
                [$tenantId, $like, $like, $like]
            )),
        ];

        $out['cash'] = [
            'label' => __('nav.cash'),
            'items' => array_map(fn ($r) => ['title' => $r['name'], 'sub' => $r['code'], 'url' => '/app/cash/' . $r['id']], DB::select(
                'SELECT * FROM cash_accounts WHERE tenant_id = ? AND deleted_at IS NULL AND (name LIKE ? OR code LIKE ?) ORDER BY id LIMIT ' . $limit,
                [$tenantId, $like, $like]
            )),
        ];

        $half = (int) floor($limit / 2);
        $out['check'] = [
            'label' => __('nav.checks'),
            'items' => array_merge(
                array_map(fn ($r) => ['title' => ($r['check_no'] ?? ''), 'sub' => __('check.type_check') . ' · ' . money($r['amount']), 'url' => '/app/checks'], DB::select(
                    'SELECT * FROM checks WHERE tenant_id = ? AND deleted_at IS NULL AND (check_no LIKE ? OR bank LIKE ?) ORDER BY id LIMIT ' . $half,
                    [$tenantId, $like, $like]
                )),
                array_map(fn ($r) => ['title' => ($r['note_no'] ?? ''), 'sub' => __('check.type_note') . ' · ' . money($r['amount']), 'url' => '/app/checks/notes'], DB::select(
                    'SELECT * FROM promissory_notes WHERE tenant_id = ? AND deleted_at IS NULL AND (note_no LIKE ? OR bank LIKE ?) ORDER BY id LIMIT ' . $half,
                    [$tenantId, $like, $like]
                ))
            ),
        ];

        foreach ($out as $k => $group) {
            if (empty($group['items'])) {
                unset($out[$k]);
            }
        }

        return $out;
    }
}
