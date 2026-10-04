<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\DB;
use Muh\Core\Controller;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;
use Muh\Core\ValidationException;
use Muh\Services\InventoryService;

/**
 * Products / stock cards (Phase 5).
 */
final class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        Auth::requireCan('inventory.read');

        $companyId = (int) ($request->query('company_id') ?? 0);
        $search = $request->query('search') ?: null;

        $service = new InventoryService();
        $tenantId = Auth::tenantId();
        $sql = 'SELECT p.*, c.name AS company_name, u.abbr AS unit_abbr FROM products p
                 JOIN companies c ON c.id = p.company_id
                 LEFT JOIN units u ON u.id = p.unit_id
                WHERE p.tenant_id = :t AND p.deleted_at IS NULL';
        $params = ['t' => $tenantId];
        if ($companyId) {
            $sql .= ' AND p.company_id = :c';
            $params['c'] = $companyId;
        }
        if ($search) {
            $sql .= ' AND (p.name LIKE :s OR p.code LIKE :s OR p.barcode LIKE :s)';
            $params['s'] = '%' . $search . '%';
        }
        $sql .= ' ORDER BY p.name ASC';

        $page = paginate($sql, $params, 25);
        $companies = DB::select('SELECT id, name FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => $tenantId]);
        $warehouses = $service->warehouses();

        return $this->view('app.inventory.products', [
            'layout' => 'layouts.app',
            'products' => $page['items'],
            'companies' => $companies,
            'warehouses' => $warehouses,
            'companyId' => $companyId,
            'search' => $search,
            'page' => $page['page'],
            'lastPage' => $page['lastPage'],
            'total' => $page['total'],
        ]);
    }

    /** Stok/stok kartlarını CSV/Excel olarak dışa aktar (master-data export). */
    public function export(Request $request): Response
    {
        Auth::requireCan('inventory.read');
        $tenantId = Auth::tenantId();
        $format = $request->query('format', 'csv');
        $companyId = (int) ($request->query('company_id') ?? 0);

        $sql = 'SELECT p.*, c.name AS company_name, u.abbr AS unit_abbr FROM products p
                 JOIN companies c ON c.id = p.company_id
                 LEFT JOIN units u ON u.id = p.unit_id
                WHERE p.tenant_id = :t AND p.deleted_at IS NULL';
        $params = ['t' => $tenantId];
        if ($companyId) {
            $sql .= ' AND p.company_id = :c';
            $params['c'] = $companyId;
        }
        $sql .= ' ORDER BY p.name ASC';
        $rows = DB::select($sql, $params);

        $headers = [
            __('inventory.code'), __('inventory.name'), __('inventory.company'), __('inventory.barcode'),
            __('inventory.type'), __('inventory.unit'), __('inventory.purchase_price'), __('inventory.sale_price'),
            __('inventory.stock'), __('inventory.critical_stock'), __('inventory.stock_value'),
        ];
        $data = array_map(fn ($p) => [
            $p['code'], $p['name'], $p['company_name'], $p['barcode'] ?? '',
            $p['type'] === 'product' ? __('inventory.type_product') : __('inventory.type_service'),
            $p['unit_abbr'] ?? '', number_format((float) ($p['purchase_price'] ?? 0), 2, ',', '.'),
            number_format((float) ($p['sale_price'] ?? 0), 2, ',', '.'), (float) $p['stock_quantity'],
            (float) ($p['critical_stock'] ?? 0), number_format((float) ($p['purchase_price'] ?? 0) * (float) $p['stock_quantity'], 2, ',', '.'),
        ], $rows);

        return $format === 'excel'
            ? \Muh\Services\ReportExportService::excel(__('inventory.products'), $headers, $data, 'stok.xls')
            : \Muh\Services\ReportExportService::csv($headers, $data, 'stok.csv');
    }

    public function create(Request $request): Response
    {
        Auth::requireCan('inventory.create');

        $service = new InventoryService();
        $companies = DB::select('SELECT id, name FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => Auth::tenantId()]);
        $units = $service->units();

        return $this->view('app.inventory.product-create', [
            'layout' => 'layouts.app',
            'companies' => $companies,
            'units' => $units,
            'selectedCompany' => (int) ($request->query('company_id') ?? ($companies[0]['id'] ?? 0)),
        ]);
    }

    /**
     * Physical stock count (sayım): set a product's real counted quantity.
     */
    public function stockTake(Request $request, $id): Response
    {
        $id = (int) $id;
        Auth::requireCan('inventory.update');
        try {
            (new InventoryService())->stockTake($id, (float) $request->input('counted'), $request->input('description') ?: null);
            Session::flash('success', __('inventory.stock_take_done'));
        } catch (ValidationException $e) {
            Session::set('_form_errors', $e->errors);
        }
        return Response::redirect('/app/inventory/' . $id);
    }

    public function store(Request $request): Response
    {
        Auth::requireCan('inventory.create');

        try {
            $id = (new InventoryService())->createProduct($request->all());
        } catch (ValidationException $e) {
            Session::set('_form_errors', $e->errors);
            return Response::redirect('/app/inventory/create?company_id=' . (int) $request->input('company_id'));
        }
        Session::flash('success', __('inventory.created_product'));
        return Response::redirect('/app/inventory/' . $id);
    }

    public function show(Request $request, $id): Response
    {
        $id = (int) $id;
        Auth::requireCan('inventory.read');

        \Muh\Services\CurrentContextService::guardRecord((int) Auth::tenantId(), 'products', $id);

        $service = new InventoryService();
        $product = $service->product(Auth::tenantId(), $id);
        if (!$product) {
            return Response::redirect('/app/inventory');
        }
        $movements = $service->productMovements($id);
        $company = DB::first('SELECT id, name FROM companies WHERE id = :id', ['id' => $product['company_id']]);

        return $this->view('app.inventory.product-show', [
            'layout' => 'layouts.app',
            'product' => $product,
            'company' => $company,
            'movements' => $movements,
        ]);
    }

    public function edit(Request $request, $id): Response
    {
        $id = (int) $id;
        Auth::requireCan('inventory.update');

        $service = new InventoryService();
        $product = $service->product(Auth::tenantId(), $id);
        if (!$product) {
            return Response::redirect('/app/inventory');
        }
        $units = $service->units();
        return $this->view('app.inventory.product-edit', [
            'layout' => 'layouts.app',
            'product' => $product,
            'units' => $units,
        ]);
    }

    public function update(Request $request, $id): Response
    {
        $id = (int) $id;
        Auth::requireCan('inventory.update');
        (new InventoryService())->updateProduct($id, $request->all());
        Session::flash('success', __('inventory.updated_product'));
        return Response::redirect('/app/inventory/' . $id);
    }

    public function destroy(Request $request, $id): Response
    {
        $id = (int) $id;
        Auth::requireCan('inventory.delete');
        (new InventoryService())->deleteProduct($id);
        Session::flash('success', __('inventory.deleted_product'));
        return Response::redirect('/app/inventory');
    }

    /** Depo transfer formu. */
    public function transferForm(Request $request): Response
    {
        Auth::requireCan('inventory.create');
        $tenantId = Auth::tenantId();
        $products = DB::select('SELECT id, code, name, stock_quantity FROM products WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => $tenantId]);
        $warehouses = (new InventoryService())->warehouses();
        return $this->view('app.inventory.transfer', [
            'layout' => 'layouts.app',
            'products' => $products,
            'warehouses' => $warehouses,
        ]);
    }

    /** Depo transferini uygula. */
    public function transfer(Request $request): Response
    {
        Auth::requireCan('inventory.create');
        try {
            (new InventoryService())->transfer(
                (int) $request->input('product_id'),
                (int) $request->input('from_warehouse'),
                (int) $request->input('to_warehouse'),
                (float) str_replace(',', '.', (string) $request->input('quantity')),
                trim((string) $request->input('description')) ?: null
            );
        } catch (\Muh\Core\ValidationException $e) {
            Session::set('_form_errors', $e->errors);
            Session::flash('error', __('inventory.transfer_invalid'));
            return Response::redirect('/app/inventory/transfer');
        }
        Session::flash('success', __('inventory.transferred'));
        return Response::redirect('/app/inventory/transfer');
    }
}
