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
        $efatura = $request->query('efatura') ?: null;
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
        if ($type && in_array($type, ['sales', 'purchase', 'sales_return', 'purchase_return', 'proforma'], true)) {
            $sql .= ' AND i.type = :type';
            $params['type'] = $type;
        }
        if ($efatura && in_array($efatura, ['draft', 'sending', 'sent', 'accepted', 'rejected', 'error'], true)) {
            $sql .= ' AND i.efatura_status = :ef';
            $params['ef'] = $efatura;
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
            'efatura' => $efatura,
            'from' => $from,
            'to' => $to,
            'page' => $page['page'],
            'lastPage' => $page['lastPage'],
            'total' => $page['total'],
        ]);
    }

    /**
     * Bulk invoice form: create the same invoice for several current accounts.
     */
    public function bulkCreate(Request $request): Response
    {
        Auth::requireCan('invoice.create');
        $tenantId = Auth::tenantId();
        $companies = DB::select('SELECT id, name FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => $tenantId]);
        $selectedCompany = (int) ($request->query('company_id') ?? ($companies[0]['id'] ?? 0));
        $accounts = $selectedCompany
            ? DB::select('SELECT id, code, name FROM current_accounts WHERE company_id = :c AND deleted_at IS NULL ORDER BY name', ['c' => $selectedCompany])
            : [];
        $products = $selectedCompany
            ? DB::select('SELECT id, code, name, sale_price, vat_rate FROM products WHERE company_id = :c AND deleted_at IS NULL ORDER BY name', ['c' => $selectedCompany])
            : [];

        return $this->view('app.invoices.bulk-create', [
            'layout' => 'layouts.app',
            'companies' => $companies,
            'selectedCompany' => $selectedCompany,
            'accounts' => $accounts,
            'products' => $products,
        ]);
    }

    public function bulk(Request $request): Response
    {
        Auth::requireCan('invoice.create');
        $accountIds = (array) $request->input('accounts', []);
        if (!$accountIds) {
            Session::set('_form_errors', ['accounts' => __('invoice.select_accounts')]);
            return Response::redirect('/app/invoices/bulk/create?company_id=' . (int) $request->input('company_id'));
        }
        $service = new InvoiceService();
        try {
            $result = $service->createBulk($request->all(), $accountIds, $request);
        } catch (ValidationException $e) {
            Session::set('_form_errors', $e->errors);
            return Response::redirect('/app/invoices/bulk/create?company_id=' . (int) $request->input('company_id'));
        }
        Session::flash('success', __('invoice.bulk_done', ['n' => count($result['created'])]));
        return Response::redirect('/app/invoices');
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

    /** Standalone, print/PDF-friendly invoice (no app chrome). */
    public function print(Request $request, $id): Response
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
        // No layout → the view is a self-contained HTML document.
        return $this->view('app.invoices.print', ['invoice' => $invoice, 'items' => $items]);
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
