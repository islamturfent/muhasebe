<?php

declare(strict_types=1);

use Muh\Database\Schema;

return new class {
    public function up(): void
    {
        // ---- companies (client firms) ----
        Schema::create('companies', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->foreignId('customer_id')->nullableForeignId('parent_company_id');
            $t->string('name', 255);
            $t->string('trade_name', 255, true);
            $t->string('tax_number', 40, true);
            $t->string('tax_office', 190, true);
            $t->string('mersis', 190, true);
            $t->string('address', 500, true);
            $t->string('phone', 40, true);
            $t->string('email', 190, true);
            $t->string('website', 190, true);
            $t->string('logo_path', 500, true);
            $t->string('company_type', 40, true);   // limited|joint-stock|sole|partnership|branch
            $t->string('currency', 8, true, 'TRY');
            $t->string('status', 20, true, 'active');
            $t->timestamps();
            $t->softDeletes();
            $t->index('tenant_id');
            $t->index('tax_number');
            $t->foreign('tenant_id', 'tenants');
        });

        // ---- customers (client firms relationship to the office) ----
        Schema::create('customers', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->string('name', 255);
            $t->string('email', 190, true);
            $t->string('phone', 40, true);
            $t->string('contact_person', 190, true);
            $t->string('address', 500, true);
            $t->string('status', 20, true, 'active');
            $t->timestamps();
            $t->softDeletes();
            $t->index('tenant_id');
            $t->foreign('tenant_id', 'tenants');
        });

        // ---- fiscal periods ----
        Schema::create('fiscal_periods', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->foreignId('company_id');
            $t->string('name', 100);
            $t->date('start_date')->date('end_date');
            $t->boolean('is_closed');
            $t->boolean('is_current');
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['company_id', 'name']);
            $t->index('company_id');
        });

        // ---- branches ----
        Schema::create('branches', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->foreignId('company_id');
            $t->string('name', 255);
            $t->string('address', 500, true);
            $t->string('phone', 40, true);
            $t->string('city', 190, true);
            $t->timestamps();
            $t->softDeletes();
            $t->index('company_id');
        });

        // ---- suppliers ----
        Schema::create('suppliers', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->string('name', 255);
            $t->string('tax_number', 40, true);
            $t->string('tax_office', 190, true);
            $t->string('email', 190, true);
            $t->string('phone', 40, true);
            $t->string('address', 500, true);
            $t->string('status', 20, true, 'active');
            $t->timestamps();
            $t->softDeletes();
            $t->index('tenant_id');
        });

        // ---- current accounts (borç/alacak - AR/AP cards) ----
        Schema::create('current_accounts', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->foreignId('company_id');
            $t->string('code', 40);
            $t->string('name', 255);
            $t->string('type', 20, true, 'customer');   // customer|supplier|both
            $t->string('tax_number', 40, true);
            $t->string('email', 190, true);
            $t->string('phone', 40, true);
            $t->string('address', 500, true);
            $t->string('iban', 50, true);
            $t->decimal('risk_limit');
            $t->decimal('balance');                      // current computed balance
            $t->string('status', 20, true, 'active');
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['company_id', 'code']);
            $t->index('tenant_id');
            $t->index('company_id');
            $t->foreign('company_id', 'companies');
        });

        // ---- current account transactions (movement history) ----
        Schema::create('current_account_transactions', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->foreignId('company_id');
            $t->foreignId('current_account_id');
            $t->string('type', 20);                      // debt|credit|payment|collection
            $t->date('date');
            $t->decimal('amount');
            $t->string('description', 500, true);
            $t->string('reference_type', 40, true);      // invoice|payment|entry...
            $t->string('reference_id', 40, true);
            $t->timestamps();
            $t->index('current_account_id');
            $t->index('company_id');
            $t->foreign('current_account_id', 'current_accounts');
        });
    }

    public function down(): void
    {
        foreach (['current_account_transactions', 'current_accounts', 'suppliers', 'branches',
                  'fiscal_periods', 'customers', 'companies'] as $table) {
            Schema::drop($table);
        }
    }
};
