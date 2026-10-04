# Hesap360 — Kullanıcı Kılavuzu (User Guide)

Bu kılavuz, Hesap360 bulut muhasebe platformunun uçtan uca kullanımını anlatır:
- **Ofis Sahibi / Yönetici** akışları (günlük muhasebe işlemleri)
- **Süper Admin (platform sahibi)** akışları
- Orijinal isteğin **37 maddelik uyumluluk (compliance) matrisi**

> Teknik kurulum için bkz. `README.md` ve `DEPLOYMENT.md`.

---

## 1. Giriş & hesap türleri

| Tür | Kime | Başlangıç giriş (seed) | Menü |
|-----|------|------------------------|------|
| **Ofis** (tenant) | Muhasebe ofisi | `demo@muh.local` / `Demo1234` | `/app/*` |
| **İkinci Ofis** (tenant) | Ayrı, izole ikinci ofis (tenant izolasyonu demo) | `second@muh.local` / `Second1234` | `/app/*` |
| **Süper Admin** | Yazılım sahibi + atananlar | `admin@muh.local` / `Admin1234!` | `/admin/*` |

- Süper admin **yalnızca** `/admin` alanına erişir; ofis (tenant) kullanıcısı bir `/app/*` alanına erişemez. İki menü asla karışmaz.
- E-posta daveti: ofis sahipleri `/app/users` üzerinden; süper adminler `/admin/admins` üzerinden veya `php bin/muh admin:make <email>` ile atanır.

---

## 2. Ofis Sahibi akışı

1. **Kayıt** → `/register` (ad, e-posta, telefon, şifre, ofis adı, ülke, dil, para birimi).
2. **Onboarding** → ilk firma oluştur (vergi bilgileri + mali dönem + hesap planı otomatik kurulur).
3. **Firmalar** (`/app/companies`) → yeni firma ekle, detay görüntüle, şube ekle.
4. **Cari** (`/app/current-accounts`) → müşteri/tedarikçi kartları, bakiye, hareketler.
5. **Stok** (`/app/inventory`) → depo + ürün kartları, açılış stoğu.
6. **Fatura** (`/app/invoices`) → satış/alış faturası kes → cari + stok + KDV + dengeli fiş tek işlemde (transaction).
7. **Kasa / Banka** → hesap + hareket; **Çek/Senet** → portföy.
8. **Muhasebe** (`/app/accounting`) → yevmiye, mizan, bilanço, gelir tablosu; **Raporlar** → tüm raporlar PDF/Excel/CSV.
9. **Kullanıcılar** (`/app/users`) → çalışan davet et, rol + firma erişimi ata.
10. **Abonelik** → plan seç/yükselt (`/app/settings/subscription`).

Aktif firmanın Kasa/Banka/Alacak/Borç/Satış/Alış/Stok/Kâr özetini görmek için ofis panelinde **Firma Paneli** (`/app/company`) butonu.

---

## 3. Süper Admin akışı (`/admin/*`)

| Bölüm | Görev |
|-------|-------|
| **Panel** `/admin` | Küresel istatistikler (ofis, firma, kullanıcı, fatura, süper admin) |
| **Ofisler** `/admin/tenants` | Tüm tenant'ları listele/ara, detay gör, **askıya al/aktifleştir**, **ofise gir** (impersonation) |
| **Abonelikler** `/admin/subscriptions` | Tüm abonelikler + plan bazlı özet; tenant planını değiştir |
| **Planlar** `/admin/plans` | Plan CRUD (fiyat + limitler `features`) — tamamen DB'den |
| **Denetim** `/admin/audit` | Tüm tenant'ların kritik işlem kayıtları |
| **Süper Adminler** `/admin/admins` | Sistem admini oluştur / ata / yetkisini kaldır |
| **Platform Ayarları** `/admin/settings` | **Bakım modu** (production'da 503) + **platform duyurusu** |

**Impersonation (destek):** Tenant detayında "Ofise Gir" → o ofisin sahibi olarak çalışma alanına girersin; üstte amber çubuk belirir, "Geri Dön" ile `/admin`'e dönersin. Tüm girişler denetim kaydına düşer.

---

## 4. Komut satırı (CLI)

```
php bin/muh migrate [--fresh]    # şema (--fresh: tüm tabloları bırak)
php bin/muh seed                 # referans verileri + demo ofisler + süper admin
php bin/muh test                 # 54 birim/regresyon testi
php bin/muh test:email-template  # e-posta şablonu gate'i
php bin/muh test:backup          # yedek rotasyonu gate'i
php bin/muh i18n:check           # tr/en çeviri bütünlüğü + tanımsız anahtar denetimi
php bin/muh doctor               # üretim hazırlık denetimi
php bin/muh security             # HTTP güvenlik testleri (CSRF, tenant izolasyonu, RBAC /admin)
php bin/muh backup --upload      # SQL yedek + off-box kopya (BACKUP_REMOTE_DIR)
php bin/muh backup:restore <x>   # yedeği geri yükle
php bin/muh notify:summary       # günlük e-posta özeti (her aktif ofise)
php bin/muh key:generate         # APP_KEY üret
php bin/muh admin:make <email>   # kullanıcıyı süper admin yap
```

**Demo ofisler:** `0002_demo_office` + `0006_second_demo_office` iki ayrı, birbirinden
izole ofis kurar. Bu sayede tenant izolasyonu (`php bin/muh security`) gerçek iki
tenant/iki firma üzerinde test edilir.

**Sürekli entegrasyon (CI):** Depoyu GitHub'a gönderdiğinizde
`.github/workflows/ci.yml` otomatik çalışır — PHP 8.2 + MySQL 8 içinde
`migrate --fresh` → `seed` → `test` (54 kontrol) + `doctor` + `i18n:check` yapar;
ayrı `email-templates` ve `backup-rotation` gate job'ları ile `security`
(HTTP CSRF/tenant/RBAC) süitini koşar. Harici bağımlılık (Composer) gerekmez.

---

## 5. 37 madde uyumluluk matrisi

| # | Madde | Durum | Nerede |
|---|-------|-------|--------|
| 1 | Çok kiracılı SaaS hiyerarşisi (ofis→müşteri→firma→dönem) | ✅ | `tenants`, `companies`, `fiscal_periods` + tenant izolasyonu |
| 2 | Kullanıcı rolleri (sahip, müşavir, muhasebeci, personel, stajyer, yönetici, müşteri, izleyici) | ✅ | `roles` + `role_permission` |
| 3 | TR/EN iki dilli + i18n | ✅ | `resources/lang` (tüm UI metinleri çeviriden) |
| 4 | SaaS landing page (hero, özellikler, çözümler, fiyat, SSS, iletişim, CTA) | ✅ | `/` `HomeController` |
| 5 | Kayıt + onboarding sihirbazı | ✅ | `/register` + `/onboarding` |
| 6 | Çoklu müşteri yönetimi + "Firma Değiştir" | ✅ | `/app/dashboard` + `/app/switch-company` |
| 7 | Firma yönetimi (vergi, MERSİS, mali dönem, para birimi, logo...) | ✅ | `/app/companies` |
| 8 | Tam çift taraflı muhasebe (borç=alacak) | ✅ | `AccountingService`, yevmiye/mizan/bilanço/gelir |
| 9 | Cari hesaplar (müşteri/tedarikçi, bakiye, vade, ekstre) | ✅ | `/app/current-accounts` |
| 10 | Fatura (satış/alış/iade/proforma), PDF/yazdır/e-posta; cari+stok+KDV+fiş | ✅ | `/app/invoices` + `InvoiceService` |
| 11 | e-Fatura / e-Arşiv (adapter, test/prod, durum takibi) | ✅ | `EFaturaGateway` + `RESTEFaturaGateway` |
| 12 | Stok (barkod, ürün, depo, hareket, kritik stok, değerleme) | ✅ | `/app/inventory` + `InventoryService` |
| 13 | Kasa & banka (hesap, tahsilat/ödeme, virman, IBAN, EFT) | ✅ | `/app/cash`, `/app/bank` |
| 14 | Çek / senet (portföy, bankaya teslim, tahsil, ciro...) | ✅ | `/app/checks` |
| 15 | Vergi & KDV motoru (KDV, istisna, tevkifat, dahil/hariç) | ✅ | `tax_rates` yönetimi + KDV raporu |
| 16 | Raporlar (mizan, bilanço, gelir, yevmiye, cari, KDV, stok, satış, alış, kasa, banka, kârlılık, borç/alacak) | ✅ | `/app/reports` (PDF/Excel/CSV) |
| 17 | Muhasebe ofisi dashboard | ✅ | `/app/dashboard` |
| 18 | Firma dashboard (kasa, banka, cari, satış, alış, stok, borç, alacak, kâr, vadeler) | ✅ | `/app/company` |
| 19 | Kullanıcı davet sistemi (e-posta, rol) | ✅ | `/app/users` + `InviteController` |
| 20 | Gelişmiş yetkilendirme (görüntüle/oluştur/düzenle/sil/onayla/export/yazdır/rapor) | ✅ | RBAC (`role_permission`) |
| 21 | Audit log (kullanıcı, firma, işlem, IP, eski/yeni) | ✅ | `AuditLogService` + `/app/audit` + `/admin/audit` |
| 22 | Abonelik sistemi (FREE/PRO/BUSINESS/ENTERPRISE, trial/active/past_due/cancelled/expired) | ✅ | `plans` + `subscriptions` + `/admin/plans` |
| 23 | Ödeme altyapısı (abstraction, webhook, kart saklama yok) | ✅ | `PaymentGateway` + `StripePaymentGateway` + webhook |
| 24 | Limit yönetimi (firma, kullanıcı, depo, fatura, e-fatura, depolama) | ✅ | `PlanLimitsService` |
| 25 | Dosya/belge yönetimi (firma/cari/fatura ile ilişkili) | ✅ | `/app/documents` |
| 26 | Global arama (firma, cari, fatura, stok, fiş, banka, kasa, çek, senet) | ✅ | `/app/search` |
| 27 | Import/Export (Excel; kolon eşleme, önizleme, hata raporu, doğrulama) | ✅ | `/app/import` |
| 28 | Bildirimler (vade, ödenmemiş, kritik stok, e-fat, abonelik...) | ✅ | `/app/notifications` |
| 29 | Güvenlik (auth, hash, 2FA, session, RBAC, tenant, rate limit, CSRF/XSS/SQLi, audit, backup) | ✅ | Middleware + `TwoFactorAuth` + `bin/muh backup` |
| 30 | Veritabanı (PostgreSQL hazır DBAL, tablolar + PK/FK/index/unique) | ✅ | `database/migrations` |
| 31 | Para hesaplama (DECIMAL, transaction) | ✅ | `DECIMAL(15,2)` + `DB::transaction` |
| 32 | API mimarisi (REST, auth, validation, error, pagination, filtre, sıralama, arama) | ✅ | Router + `Request`/`Response` |
| 33 | UI/UX (sol menü, üst menü: firma seçici, arama, dil, bildirim, profil) | ✅ | `layouts/app.php` |
| 34 | Türkiye odaklı (₺, DD.MM.YYYY, 1.234,56) + modüler uluslararası mimari | ✅ | `money()`/`format_date()` locale'e göre |
| 35 | Gerçek veri akışı (frontend→API→iş mantığı→DB), hepsi transaction içinde | ✅ | `InvoiceService` dikey dilimi |
| 36 | Aşamalı geliştirme (Phase 1–14) | ✅ | `bin/muh test` + migration geçmişi |
| 37 | Sonuç (multi-tenant, multi-company, multi-user, TR+EN, abonelik bazlı, güvenli, ölçeklenebilir, production-ready) | ✅ | Tümü |

**Not:** Gerçek Stripe / e-Fatura gönderimi canlı API anahtarı ister; kod ve config hazırdır (`.env`: `STRIPE_SECRET_KEY`, `EFATURA_TEST_URL/PROD_URL/USERNAME/PASSWORD`). Anahtarlar olmadan `simulated` sürücü devreye girer.
