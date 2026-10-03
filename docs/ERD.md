# MUH — Database ERD

All tables use an auto-increment `id BIGINT` PK, `created_at`/`updated_at`
timestamps where the model expects them, and `deleted_at` for soft deletes.
Money columns are `DECIMAL(15,2)`.

```
tenants (accounting offices)
  ├── users ──────────── user_role ──── roles ──── role_permission ──── permissions
  ├── user_invites, api_tokens, login_attempts, settings
  ├── subscriptions ── plan_id → plans
  ├── customers ── 1:N ── companies ── fiscal_periods
  │                          ├── branches
  │                          ├── current_accounts ── current_account_transactions
  │                          ├── warehouses ── stock_movements
  │                          ├── units ── products
  │                          ├── orders ── order_items
  │                          ├── invoices ── invoice_items
  │                          ├── cash_accounts ── cash_transactions
  │                          ├── bank_accounts ── bank_transactions
  │                          ├── checks, promissory_notes
  │                          └── accounting_accounts ── accounting_entries ── accounting_entry_lines
  ├── tax_rates (nullable tenant → system defaults), currencies, exchange_rates
  ├── documents, notifications, audit_logs
  └── user_company (users ↔ companies access)
```

## Key relationships (FK)

| Child | Parent | Notes |
|-------|--------|-------|
| users.tenant_id | tenants.id | |
| subscriptions.tenant_id, plan_id | tenants.id, plans.id | |
| companies.tenant_id | tenants.id | |
| fiscal_periods.company_id | companies.id | unique (company_id, name) |
| current_accounts.company_id | companies.id | unique (company_id, code) |
| current_account_transactions.current_account_id | current_accounts.id | movement history |
| products.company_id | companies.id | unique (company_id, code) |
| stock_movements.product_id, warehouse_id | products.id, warehouses.id | |
| orders.company_id | companies.id | unique (company_id, number) |
| order_items.order_id, product_id | orders.id, products.id | |
| invoices.company_id, fiscal_period_id, current_account_id | companies.id, fiscal_periods.id, current_accounts.id | unique (company_id, number) |
| invoice_items.invoice_id, product_id | invoices.id, products.id | |
| cash_transactions.cash_account_id | cash_accounts.id | |
| bank_transactions.bank_account_id | bank_accounts.id | |
| checks.current_account_id, bank_account_id | current_accounts.id, bank_accounts.id | |
| promissory_notes.current_account_id, bank_account_id | current_accounts.id, bank_accounts.id | |
| accounting_accounts.company_id, fiscal_period_id | companies.id, fiscal_periods.id | unique (company_id, period_id, code) |
| accounting_entries.company_id, fiscal_period_id, created_by | companies.id, fiscal_periods.id, users.id | unique (company_id, period_id, number) |
| accounting_entry_lines.entry_id, account_id | accounting_entries.id, accounting_accounts.id | |
| user_role.user_id, role_id | users.id, roles.id | pivot, unique |
| role_permission.role_id, permission_id | roles.id, permissions.id | pivot, unique |
| user_company.user_id, company_id | users.id, companies.id | unique |
| audit_logs.user_id | users.id | tenant/company nullable |

## Noteworthy columns

- **plans.features** — JSON of limits (companies, users, warehouses, invoices,
  e-fatura flag, storage_mb) → data-driven quotas, not hard-coded.
- **subscriptions.status** — trial | active | past_due | cancelled | expired.
- **invoices.efatura_status** — draft | sending | sent | accepted | rejected | error.
- **accounting_accounts** — chart of accounts per (company, fiscal_period);
  `opening_debit`/`opening_credit` seed the ledger.
- **accounting_entries** — voucher types journal|transfer|collection|payment|
  opening|closing|carry_forward; lines carry debit/credit so Σ debit = Σ credit.
