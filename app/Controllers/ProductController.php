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
}
