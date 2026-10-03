<?php

declare(strict_types=1);

use Muh\Database\Schema;

return new class {
    public function up(): void
    {
        // ---- currencies ----
        Schema::create('currencies', function ($t) {
            $t->id();
            $t->string('code', 8);        // TRY, USD, EUR ...
            $t->string('name', 190, true);
            $t->string('symbol', 8, true);
            $t->timestamps();
            $t->unique('code');
        });

        // ---- tax rates (KDV) - data-driven, never hard-coded ----
        Schema::create('tax_rates', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->string('name', 190);
            $t->decimal('rate', 5, 2);       // 0.00, 0.01, 0.10, 0.20 ...
            $t->string('type', 20, true, 'vat');   // vat|withholding|special_consumption
            $t->boolean('is_vat')->boolean('is_withholding');
            $t->boolean('is_default')->boolean('is_active');
            $t->integer('sort_order');
            $t->timestamps();
            $t->softDeletes();
            $t->index('tenant_id');
        });

        // ---- exchange rates ----
        Schema::create('exchange_rates', function ($t) {
            $t->id();
            $t->string('from_currency', 8);
            $t->string('to_currency', 8);
            $t->date('date');
            $t->decimal('rate', 18, 6);
            $t->timestamps();
            $t->unique(['from_currency', 'to_currency', 'date']);
        });

        // ---- documents / files ----
        Schema::create('documents', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->foreignId('company_id')->nullableForeignId('current_account_id')->nullableForeignId('invoice_id')->nullableForeignId('uploaded_by');
            $t->string('name', 255);
            $t->string('original_name', 255);
            $t->string('path', 500);
            $t->string('mime', 120, true);
            $t->decimal('size', 15, 0);
            $t->string('category', 64, true);   // invoice|receipt|contract|bank_statement|other
            $t->text('notes', true);
            $t->timestamps();
            $t->softDeletes();
            $t->index('company_id');
        });

        // ---- notifications ----
        Schema::create('notifications', function ($t) {
            $t->id();
            $t->foreignId('tenant_id')->nullableForeignId('user_id')->nullableForeignId('company_id');
            $t->string('type', 64, true);       // due_date|unpaid_invoice|critical_stock|efatura_error|subscription|user|document|system
            $t->string('title', 255);
            $t->text('body', true);
            $t->string('level', 20, true, 'info'); // info|warning|success|danger
            $t->boolean('is_read');
            $t->string('action_url', 500, true);
            $t->text('payload', true);
            $t->timestamps();
            $t->index('user_id');
            $t->index('tenant_id');
        });

        // ---- audit logs ----
        Schema::create('audit_logs', function ($t) {
            $t->id();
            $t->foreignId('tenant_id')->nullableForeignId('user_id')->nullableForeignId('company_id');
            $t->string('action', 64);
            $t->string('module', 64, true);
            $t->string('entity_type', 64, true);
            $t->string('entity_id', 64, true);
            $t->longText('old_value');
            $t->longText('new_value');
            $t->string('ip', 45, true);
            $t->string('user_agent', 500, true);
            $t->dateTime('created_at');
            $t->index(['tenant_id', 'company_id']);
            $t->index('created_at');
            $t->foreign('user_id', 'users');
        });

        // ---- user-company access (which users can see which companies) ----
        Schema::create('user_company', function ($t) {
            $t->id();
            $t->foreignId('user_id')->foreignId('company_id');
            $t->timestamps();
            $t->unique(['user_id', 'company_id']);
            $t->foreign('user_id', 'users');
            $t->foreign('company_id', 'companies');
        });
    }

    public function down(): void
    {
        foreach (['user_company', 'audit_logs', 'notifications', 'documents',
                  'exchange_rates', 'tax_rates', 'currencies'] as $table) {
            Schema::drop($table);
        }
    }
};
