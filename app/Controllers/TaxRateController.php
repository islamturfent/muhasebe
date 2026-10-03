<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\Controller;
use Muh\Core\DB;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;
use Muh\Core\Translator;
use Muh\Services\AuditLogService;

/**
 * Tax rate management (KDV / tevkifat) — rates are tenant-owned and stored in
 * the database (spec #15: "Vergi oranlarını database üzerinden yönet").
 */
final class TaxRateController extends Controller
{
    public function index(Request $request): Response
    {
        Auth::requireCan('tax.read');
        $tenantId = Auth::tenantId();
        $locale = Translator::instance()->locale();

        $rates = DB::select(
            'SELECT * FROM tax_rates WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY is_vat DESC, rate, sort_order',
            ['t' => $tenantId]
        );
        foreach ($rates as &$r) {
            $r['label'] = $this->label($r['name'], $locale);
        }
        unset($r);

        return $this->view('app.tax-rates.index', [
            'layout' => 'layouts.app',
            'rates' => $rates,
            'locale' => $locale,
        ]);
    }

    public function create(Request $request): Response
    {
        Auth::requireCan('tax.update');
        return $this->view('app.tax-rates.create', [
            'layout' => 'layouts.app',
            'input' => $request->all(),
            'errors' => Session::get('_form_errors', []),
        ]);
    }

    public function store(Request $request): Response
    {
        Auth::requireCan('tax.update');
        $name = trim((string) $request->input('name'));
        $rate = (float) $request->input('rate', 0);
        if ($name === '' || $rate < 0 || $rate > 100) {
            Session::set('_form_errors', ['name' => __('taxrate.valid')]);
            return Response::redirect('/app/tax-rates/create');
        }

        $isVat = (bool) $request->input('is_vat');
        $isWithholding = (bool) $request->input('is_withholding');
        $tenantId = Auth::tenantId();

        DB::insert('tax_rates', [
            'tenant_id' => $tenantId,
            'name' => json_encode(['tr' => $name, 'en' => $name], JSON_UNESCAPED_UNICODE),
            'rate' => $rate,
            'type' => $isVat ? 'vat' : ($isWithholding ? 'withholding' : 'other'),
            'is_vat' => $isVat ? 1 : 0,
            'is_withholding' => $isWithholding ? 1 : 0,
            'is_default' => 0,
            'is_active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AuditLogService::record('tax_rate.create', 'tax', 'tax_rates', null, null, ['name' => $name, 'rate' => $rate]);
        Session::flash('success', __('taxrate.created'));
        return Response::redirect('/app/tax-rates');
    }

    public function destroy(Request $request, $id): Response
    {
        Auth::requireCan('tax.update');
        $rate = DB::first(
            'SELECT * FROM tax_rates WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL',
            ['id' => (int) $id, 't' => Auth::tenantId()]
        );
        if ($rate) {
            if ((int) $rate['is_default']) {
                Session::flash('error', __('taxrate.cannot_delete_default'));
            } else {
                DB::execute('UPDATE tax_rates SET deleted_at = :n WHERE id = :id', ['n' => now(), 'id' => (int) $id]);
                AuditLogService::record('tax_rate.delete', 'tax', 'tax_rates', (string) $rate['id']);
                Session::flash('success', __('taxrate.deleted'));
            }
        }
        return Response::redirect('/app/tax-rates');
    }

    private function label(string $name, string $locale): string
    {
        $decoded = json_decode($name, true);
        if (is_array($decoded)) {
            return $decoded[$locale] ?? $decoded['en'] ?? $decoded['tr'] ?? $name;
        }
        return $name;
    }
}
