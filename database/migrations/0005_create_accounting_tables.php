<?php

declare(strict_types=1);

use Muh\Database\Schema;

return new class {
    public function up(): void
    {
        // ---- accounting accounts (hesap planı) ----
        Schema::create('accounting_accounts', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->foreignId('company_id');
            $t->foreignId('fiscal_period_id');
            $t->string('code', 32);
            $t->string('name', 255);
            $t->string('type', 32, true);                 // asset|liability|equity|income|expense
            $t->string('subtype', 64, true);
            $t->string('group', 8, true);                 // 1xxx etc.
            $t->boolean('is_header');
            $t->string('currency', 8, true, 'TRY');
            $t->decimal('opening_debit')->decimal('opening_credit');
            $t->timestamps();
            $t->index(['company_id', 'fiscal_period_id']);
            $t->unique(['company_id', 'fiscal_period_id', 'code']);
            $t->foreign('company_id', 'companies');
            $t->foreign('fiscal_period_id', 'fiscal_periods');
        });

        // ---- accounting entries (fişler: yevmiye/mahsup/tahsil/tediye/açılış/kapanış/devir) ----
        Schema::create('accounting_entries', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->foreignId('company_id');
            $t->foreignId('fiscal_period_id');
            $t->foreignId('created_by');
            $t->string('voucher_type', 24, true, 'journal'); // journal|transfer|collection|payment|opening|closing|carry_forward
            $t->string('number', 40);
            $t->date('date');
            $t->string('description', 500, true);
            $t->decimal('debit_total');
            $t->decimal('credit_total');
            $t->string('status', 20, true, 'posted');     // draft|posted|cancelled
            $t->string('reference_type', 40, true);
            $t->string('reference_id', 40, true);
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['company_id', 'fiscal_period_id', 'number']);
            $t->index(['company_id', 'date']);
            $t->foreign('company_id', 'companies');
            $t->foreign('fiscal_period_id', 'fiscal_periods');
            $t->foreign('created_by', 'users');
        });

        // ---- entry lines ----
        Schema::create('accounting_entry_lines', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->foreignId('company_id')->nullableForeignId('entry_id')->nullableForeignId('account_id');
            $t->decimal('debit');
            $t->decimal('credit');
            $t->string('description', 500, true);
            $t->timestamps();
            $t->index('entry_id');
            $t->index('account_id');
            $t->foreign('entry_id', 'accounting_entries');
            $t->foreign('account_id', 'accounting_accounts');
        });
    }

    public function down(): void
    {
        foreach (['accounting_entry_lines', 'accounting_entries', 'accounting_accounts'] as $table) {
            Schema::drop($table);
        }
    }
};
