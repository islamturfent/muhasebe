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
use Muh\Services\InvoiceService;

/**
 * Invoices (Phase 6).
 */
final class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        Auth::requireCan('invoice.read');

        $tenantId = Auth::tenantId();
        $companyId = (int) ($request->query('company_id') ?? 0);
        $type = $request->query('type') ?: null;
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        $sql = 'SELECT i.*, c.name AS company_name, ca.name AS account_name
                 FROM invoices i
                 JOIN companies c ON c.id = i.company_id
                 LEFT JOIN current_accounts ca ON ca.id = i.current_account_id
                WHERE i.tenant_id = :t AND i.deleted_at IS NULL';
        $params = ['t' => $tenantId];
        if ($companyId) {
            $sql .= ' AND i.company_id = :c';
            $params['c'] = $companyId;
        }
        if ($type && in_array($type, ['sales', 'purchase'], true)) {
            $sql .= ' AND i.type = :type';
            $params['type'] = $type;
        }
        if ($from) {
            $sql .= ' AND i.date >= :from';
            $params['from'] = $from;
        }
        if ($to) {
            $sql .= ' AND i.date <= :to';
            $params['to'] = $to;
        }
        $sql .= ' ORDER BY i.id DESC';

        $page = paginate($sql, $params, 25);
        $companies = DB::select('SELECT id, name FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => $tenantId]);

        return $this->view('app.invoices.index', [
            'layout' => 'layouts.app',
            'invoices' => $page['items'],
            'companies' => $companies,
            'companyId' => $companyId,
            'type' => $type,
            'from' => $from,
            'to' => $to,
            'page' => $page['page'],
            'lastPage' => $page['lastPage'],
            'total' => $page['total'],
        ]);
    }

    public function create(Request $request): Response
    {
        Auth::requireCan('invoice.create');
        $tenantId = Auth::tenantId();

        $companies = DB::select('SELECT id, name FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => $tenantId]);
        $selectedCompany = (int) ($request->query('company_id') ?? ($companies[0]['id'] ?? 0));

        $accounts = [];
        $products = [];
        if ($selectedCompany) {
            $accounts = DB::select('SELECT id, code, name FROM current_accounts WHERE company_id = :c AND deleted_at IS NULL ORDER BY name', ['c' => $selectedCompany]);
            $products = DB::select('SELECT id, code, name, sale_price, purchase_price, vat_rate, type FROM products WHERE company_id = :c AND deleted_at IS NULL ORDER BY name', ['c' => $selectedCompany]);
        }

        return $this->view('app.invoices.create', [
            'layout' => 'layouts.app',
            'companies' => $companies,
            'selectedCompany' => $selectedCompany,
            'accounts' => $accounts,
            'products' => $products,
        ]);
    }

    public function store(Request $request): Response
    {
        Auth::requireCan('invoice.create');
        $service = new InvoiceService();
        try {
            $id = $service->create($request->all(), $request);
        } catch (ValidationException $e) {
            Session::set('_form_errors', $e->errors);
            return Response::redirect('/app/invoices/create?company_id=' . (int) $request->input('company_id'));
        }
        Session::flash('success', __('invoice.created'));
        return Response::redirect('/app/invoices/' . $id);
    }

    public function show(Request $request, $id): Response
    {
        $id = (int) $id;
        Auth::requireCan('invoice.read');

        $invoice = DB::first(
            'SELECT i.*, c.name AS company_name, c.trade_name, c.tax_number, c.tax_office, c.address,
                    c.email AS company_email, c.phone AS company_phone,
                    ca.name AS account_name, ca.address AS account_address, ca.tax_number AS account_tax
               FROM invoices i
               JOIN companies c ON c.id = i.company_id
               LEFT JOIN current_accounts ca ON ca.id = i.current_account_id
              WHERE i.id = :id AND i.tenant_id = :t AND i.deleted_at IS NULL',
            ['id' => $id, 't' => Auth::tenantId()]
        );
        if (!$invoice) {
            return Response::redirect('/app/invoices');
        }

        $items = DB::select(
            'SELECT ii.*, p.name AS product_name FROM invoice_items ii
              LEFT JOIN products p ON p.id = ii.product_id
             WHERE ii.invoice_id = :id ORDER BY ii.id',
            ['id' => $id]
        );

        return $this->view('app.invoices.show', [
            'layout' => 'layouts.app',
            'invoice' => $invoice,
            'items' => $items,
        ]);
    }

    public function sendEfatura(Request $request, $id): Response
    {
        $id = (int) $id;
        Auth::requireCan('invoice.send');

        $requestedType = (string) ($request->input('doc_type') ?: $request->query('doc_type', 'invoice'));
        $service = new \Muh\Services\EFaturaService();
        try {
            $status = $service->sendInvoice($id, $requestedType);
        } catch (\Muh\Core\ValidationException $e) {
            Session::set('_form_errors', $e->errors);
            return Response::redirect('/app/invoices/' . $id);
        }
        Session::flash('success', __('efatura.sent_ok') . ' (' . __('efatura.st_' . $status) . ')');
        return Response::redirect('/app/invoices/' . $id);
    }
}
