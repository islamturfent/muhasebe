# MUH — System Architecture

## 1. High-level topology

```
                     ┌──────────────────────────────┐
   Browser / SPA     │        MUH Platform           │
   (Tailwind UI)     │  PHP 8.2 (custom framework)  │
        │            │                              │
        │  REST-ish  │  public/index.php (router)   │
        └───────────▶│      │                       │
                     │      ▼                       │
                     │  Middleware chain            │
                     │  RateLimit → Session → CSRF  │
                     │  → Auth → Tenant             │
                     │      ▼                       │
                     │  Controllers → Services      │  ──▶ Business Logic
                     │      ▼                       │
                     │  Models / Repositories (DBAL)│  ──▶ Persistence
                     │      ▼                       │
                     │  MySQL / PostgreSQL (PDO)    │
                     └──────────────────────────────┘
```

The frontend and backend are separated: the frontend is served as views and
talks to controllers/services only through the framework; the code is written
so that the same service layer can be exposed over a JSON REST API (`/api/*`
namespace is reserved). `config/app.php` and `.env`-style `getenv()` values drive
all environment differences.

## 2. Tenant model (multi-tenancy)

```
Accounting Office (TENANT)                 ← top-level isolation unit
 ├── Users  (owner, manager, cpa, accountant, staff, intern, client, viewer)
 ├── Roles & Permissions (RBAC)
 ├── Clients (customers)
 │    └── Companies (1 : many)
 │         ├── Fiscal Periods
 │         ├── Branches / Warehouses
 │         ├── Current Accounts / Suppliers
 │         ├── Products / Stock
 │         ├── Invoices / Orders
 │         ├── Cash / Bank / Checks / Notes
 │         └── Accounting (accounts, entries)
 ├── Subscription → Plan (limits)
 └── Settings, Documents, Notifications, AuditLogs
```

**Isolation strategy:** shared-database multi-tenant. Every business table carries
`tenant_id`. Isolation is defense-in-depth:
1. `TenantScope` trait / service queries always add `tenant_id = ?`.
2. `TenantMiddleware` rejects sessions without a tenant for tenant-routed pages.
3. Company access is additionally gated (`CurrentContextService` re-validates the
   company belongs to the user's tenant before switching).
4. `user_company` table enables per-user company-level access control later.

## 3. Request lifecycle

1. `public/index.php` loads `app/bootstrap.php` (config, DB, session, locale).
2. Routes (`routes/web.php`) register URL → controller + middleware.
3. `Router::dispatch` matches method+path, runs middleware chain per route.
4. Controller method runs; services contain business logic; DB layer is PDO.
5. `Response` object renders JSON or a view (`View` engine).

## 4. Security model

- bcrypt password hashing (`cost 12`) with transparent rehash on login.
- Session-based auth with fixation protection (`session_regenerate_id`).
- CSRF token validated on every state-changing request (`CsrfMiddleware`).
- RBAC: roles → permissions; `Auth::can()` / `Auth::requireCan()` enforce.
- Office owner / system admin bypass via flags.
- SQL injection: all queries parameterized through `DB` (PDO prepared).
- XSS: all output escaped via `e()` (htmlspecialchars).
- Rate limiting per IP.
- Full audit log for critical operations (who/what/when/ip/before/after).

## 5. Folder structure

```
public/              web root (index.php, .htaccess, assets)
app/
  bootstrap.php      boot sequence
  autoload.php       PSR-4 loader (Muh\ → app/)
  helpers.php        global helpers (e, __, money, url, csrf, ...)
  Core/              framework (Config, DB, Request, Response, Router, View,
                     Session, Auth, Model, Middleware, Validator, Hash, Translator)
  Controllers/       request handlers
  Services/          business logic (onboarding, audit, context, ...)
  Models/            active-record models + TenantScope
  Middleware/        auth, guest, csrf, tenant, rate-limit
  Database/          Migrator, Seeder (cross-driver SQL builder)
config/              app.php, database.php
database/migrations/ schema (0001..0006)
database/seeders/    reference data + demo office
routes/              route registration
resources/views/     landing, auth, onboarding, app
resources/lang/      tr/ en translation groups
public/assets/       css/js
storage/             logs, documents, cache
bin/muh              CLI: migrate | seed | drop
docs/                architecture + ERD
```

## 6. Why MySQL now, PostgreSQL-ready

The spec calls for PostgreSQL; this environment ships with MySQL only, so the
default connection is MySQL. The DBAL (`Muh\Core\DB`) and the schema builder
(`Muh\Database\Migrator`) translate DDL and use driver-agnostic column types
(`DECIMAL`, `VARCHAR`, `TIMESTAMP`, `JSON/JSONB`, boolean, big-int ids) based on
the active driver, so switching to PostgreSQL is a config change:
`DB_CONNECTION=pgsql` + enabling `pdo_pgsql`, then `php bin/muh migrate --fresh`.

## 7. Financial correctness

- All money columns are `DECIMAL(15,2)` (never floating point).
- Multi-step writes (e.g. posting an invoice → current account + stock + VAT +
  journal) run inside a DB transaction and the double-entry rule
  (Σ debit = Σ credit) is enforced.
- Math helpers (`money_value`) normalize input to fixed-point strings.
