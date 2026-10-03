# Hesap360 — Bulut Muhasebe / Cloud Accounting Platform

Professional, multi-tenant SaaS accounting & financial management platform for Turkey,
built for accounting offices, certified public accountants (mali müşavir) and bookkeepers.

**Stack:** PHP 8.2 (custom lightweight framework, no Composer), PDO, MySQL
(PostgreSQL-ready DBAL), server-rendered views + Tailwind, JSON i18n (TR/EN).

---

## Architecture

```
Muhasebe Ofisi (Tenant)
 |-- Users (owner, accountant, staff, intern, client, viewer)
 |-- Client Customers
      |-- Companies (multi per customer)
           |-- Fiscal Periods
                |-- Chart of Accounts
                |-- Accounting Entries (double-entry: DEBIT == CREDIT)
                |-- Current Accounts, Invoices, Inventory, Cash/Bank, Checks
```

### Multi-tenant isolation
- Every data row carries `tenant_id`.
- Services scope all queries with the active tenant (`TenantScope`, `Auth::tenantId()`).
- A user can only access companies belonging to their own tenant; user→company
  access is further constrained via `user_company` (optional granular control).

### Folder structure
```
public/index.php        front controller
app/bootstrap.php       boot: config, DB, session, locale
app/Core/               framework (Config, DB, Request, Response, Router, View,
                        Session, Auth, Model, Middleware, Validator, Hash, Translator)
app/Controllers/        request handlers
app/Models/             active-record models + TenantScope
app/Services/           business logic (onboarding, audit, ...)
app/Database/           Migrator, Seeder
app/Middleware/         auth, guest, csrf, tenant, rate-limit
config/                 app.php, database.php
database/migrations/    cross-driver schema (SQL builder)
database/seeders/       reference + demo data
routes/                 route registration
resources/views/        server-rendered views (landing, auth, app, onboarding)
resources/lang/         tr/ and en/ translation groups
public/assets/          css/js
storage/                logs, documents, framework cache
bin/                    CLI (muh migrate | seed)
```

---

## Docs
- [USER_GUIDE.md](USER_GUIDE.md) — kullanıcı kılavuzu + orijinal 37 maddenin tamamının uyumluluk matrisi
- [architecture.md](docs/architecture.md) — sistem mimarisi
- [ERD.md](docs/ERD.md) + [ERD.png](docs/ERD.png) — veritabanı şeması
- [DEPLOYMENT.md](DEPLOYMENT.md) — production kurulum kılavuzu

## Continuous integration
A GitHub Actions workflow (`.github/workflows/ci.yml`) runs on every push/PR:
- **tests**: PHP 8.2 + MySQL 8 → `migrate --fresh` → `seed` → `php bin/muh test` (24 checks).
- **security**: starts the app with the PHP built-in server and runs `php bin/muh security` (live HTTP CSRF / tenant isolation / RBAC checks).

No Composer or external package install is required — the app is dependency-free.

## Scheduled tasks (cron)
Automated due/unpaid invoice **reminder e-mails** can be run on a schedule:
```
# Every morning at 08:00
0 8 * * *  cd /path/to/muh && php bin/muh reminders >> storage/logs/cron.log 2>&1

# Every morning also generate automatic notifications (due invoices, critical stock, ...)
5 8 * * *  cd /path/to/muh && php bin/muh notifications >> storage/logs/cron.log 2>&1
```
Run `php bin/muh notifications` manually any time; it is idempotent per day.
With SMTP configured (`MAIL_ENABLED=true`, `MAIL_HOST=...`) it sends real e-mails;
otherwise (default) it writes to `storage/logs/mail.log`.

## Getting started (XAMPP / local)

1. Create the database (MySQL):
   ```sql
   CREATE DATABASE muh CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
   (Adjust `config/database.php` credentials — XAMPP default is root / empty.)

2. Run migrations **and** seeders:
   ```
   php bin/muh migrate
   php bin/muh seed
   ```
   The seeder creates plans (FREE/PRO/BUSINESS/ENTERPRISE), roles, permissions,
   Turkish KDV tax rates and currencies. `0002_demo_office` creates a demo office
   with login `demo@muh.local` / `Demo1234`.

3. Serve the app. With **XAMPP Apache** (recommended) just open:
   ```
   http://localhost/muh/login
   ```
   A root `.htaccess` (project root) forwards `/muh` into the `public/` web-root,
   and `public/.htaccess` routes non-file requests to `index.php`. Both
   `http://localhost/muh/...` (clean) and `http://localhost/muh/public/...` (legacy)
   work.

   Alternatively use the PHP built-in server (serves at web root):
   ```
   php -S localhost:8000 -t public public/index.php
   ```

### .htaccess (already included)
- Project root: forwards `/muh` → `public/`.
- `public/.htaccess`: routes non-file requests to `index.php`.

---

## Switching to PostgreSQL

The DBAL and SQL builder (`app/Database/Migrator.php`) emit driver-aware DDL.
To use PostgreSQL:
1. Set `DB_CONNECTION=pgsql` (or edit `config/database.php` default).
2. Ensure PHP `pdo_pgsql` is enabled.
3. `php bin/muh migrate && php bin/muh seed`

---

## Implementation status (by phase)

| Phase | Area | State |
|-------|------|-------|
| 1 | SaaS architecture + DB schema + auth | ✅ |
| 2 | Tenant + office + company management | ✅ (registration/onboarding, company mgmt) |
| 3 | Users + roles + permissions | ✅ (RBAC seed + Auth::can) |
| 4 | Customers + current accounts | ✅ current accounts (list/create/edit/delete, balances, movements, audit)
| 5 | Inventory + warehouse | ✅ products, warehouses, stock movements, opening stock |
| 6 | Sales + purchase + invoices | ✅ invoice posting → cari + stock + VAT + journal in one transaction |
| 7 | Cash + bank + checks | ✅ kasa, banka ekstre, çek + senet portföy, durum takibi |
| 8 | Accounting engine (double-entry) | ✅ balanced journal, mizan, bilanço, gelir tablosu |
| 9 | Reports | ✅ report pages + PDF/Excel/CSV export (custom PDF engine, no libs) |
| 10 | Subscription + billing | ✅ payment gateway abstraction (`SimulatedGateway` default + `StripePaymentGateway` real), webhook auto-update + signature verify, plan limits enforced |
| 11 | Notifications + documents | ✅ file upload/download per company, notification centre + auto scans |
| 12 | Security + audit + backups | ✅ audit viewer, TOTP 2FA, `bin/muh backup` SQL dumps |
| 13 | TR/EN localization | ✅ all UI text via translation files (hard-coded Turkish removed) |
| 14 | Testing/perf/production | ✅ `php bin/muh test` (24 self-tests), `php bin/muh security` (HTTP: CSRF, tenant izolasyonu, RBAC /admin), security headers, .env, maintenance mode, pagination |
| Opt. | e-Fatura / e-Arşiv | ✅ `EFaturaGateway` interface + simulated (default) + `RESTEFaturaGateway` (gerçek HTTP entegratör, test/prod, Basic auth); e-Fatura + e-Arşiv send, doc type + envelope id |
| Opt. | Global search | ✅ grouped results across company/cari/invoice/product/entry/bank/cash/check/note |
| Opt. | Import / Export | ✅ CSV import of current accounts & stock (mapping, preview, error report); CSV/Excel/PDF export |
| Opt. | Demo data | ✅ `0004_demo_company` seeder provisions a full demo firm (chart, warehouse, cari, stock, posted invoices) |
| Opt. | Production prep | ✅ `APP_ENV`/`APP_DEBUG`/`APP_KEY` via `.env`, HTTPS + HSTS + CSP, `php bin/muh key:generate`, see `DEPLOYMENT.md` |
| Opt. | Raporlar (spec #16) | ✅ Stok, Satış, Alış, Kasa, Banka, Kârlılık, Borç/Alacak + Mizan/Yevmiye/Bilanço/Gelir/KDV/Cari; PDF/Excel/CSV |
| Opt. | Vergi oranları | ✅ tenant-scoped `tax_rates` management screen (KDV / tevkifat) |
| Opt. | Profil | ✅ `/app/profile` — ad/telefon/dil/para birimi + 2FA durumu |
| Opt. | E-posta | ✅ SMTP istemcisi (STARTTLS/SSL, AUTH LOGIN) + log/mail() geri dönüşü |
| Opt. | Süper Admin | ✅ `/admin` saha dışı panel — tenant yönetimi (askıya al/aktifleştir), küresel denetim, abonelik/plan yönetimi, plan CRUD, platform bakım modu+duyurusu, impersonation (ofise gir). Sistem admini atama/iptal (`bin/muh admin:make` + `0005_super_admin`); giriş: `admin@muh.local` / `Admin1234!` |
| Opt. | Firma Paneli (spec #18) | ✅ `/app/company` — aktif firmanın Kasa/Banka/Alacak/Borç/Satış/Alış/Stok/Kâr + yaklaşan ödemeler |

Financial values are stored as `DECIMAL(15,2)` and all multi-step writes run inside
DB transactions to keep DR/CR balanced and consistent.

---

## Security
- bcrypt password hashing (cost 12) with rehash-on-login
- session-based auth, session fixation protection
- CSRF token on all state-changing forms
- tenant-scoped data isolation
- RBAC via roles→permissions (granular view/create/edit/delete/approve/export)
- SQL injection protection via prepared statements
- rate limiting per IP
- full audit log for critical operations
- optional TOTP 2FA; HTTP→HTTPS redirect + HSTS + CSP headers (see `DEPLOYMENT.md`)

## Deployment
See [DEPLOYMENT.md](DEPLOYMENT.md) for the production setup guide (env config,
Apache vhost, TLS, security headers, scheduled backups, health check).

> **Not:** Sunucuya canlı kurulum, proje **tamamen bitince** (tüm phase'ler + canlı
> Stripe/e-Fatura entegrasyonları onaylandıktan sonra) yapılacaktır. Şu anda öncelik
> geliştirme ve test aşamasındadır.

## Localization
- UI text is never hard-coded; everything goes through `__()`/`trans()`.
- `tr` and `en` dictionaries under `resources/lang`.
- Locale persists per session (and per user in DB).
- `money()`/`format_date()` adapt to locale (TR: `1.234,56 ₺`, `DD.MM.YYYY`).
