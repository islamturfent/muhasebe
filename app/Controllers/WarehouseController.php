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
 * Warehouses / depots (Phase 5).
 */
final class WarehouseController extends Controller
{
    public function index(Request $request): Response
    {
        Auth::requireCan('warehouse.read');
        $service = new InventoryService();
        return $this->view('app.inventory.warehouses', [
            'layout' => 'layouts.app',
            'warehouses' => $service->warehouses(),
        ]);
    }

    public function create(Request $request): Response
    {
        Auth::requireCan('warehouse.create');
        $companies = DB::select('SELECT id, name FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => Auth::tenantId()]);
        return $this->view('app.inventory.warehouse-create', [
            'layout' => 'layouts.app',
            'companies' => $companies,
            'selectedCompany' => (int) ($request->query('company_id') ?? ($companies[0]['id'] ?? 0)),
        ]);
    }

    public function store(Request $request): Response
    {
        Auth::requireCan('warehouse.create');
        try {
            $id = (new InventoryService())->createWarehouse($request->all());
        } catch (ValidationException $e) {
            Session::set('_form_errors', $e->errors);
            return Response::redirect('/app/inventory/warehouses/create');
        }
        Session::flash('success', __('inventory.created_warehouse'));
        return Response::redirect('/app/inventory');
    }
}
