<?php

declare(strict_types=1);

use Muh\Database\Schema;

return new class {
    public function up(): void
    {
        // ---- cash accounts (kasa) ----
        Schema::create('cash_accounts', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->foreignId('company_id');
            $t->string('name', 255);
            $t->string('code', 40);
            $t->string('currency', 8, true, 'TRY');
            $t->decimal('balance');
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['company_id', 'code']);
            $t->index('company_id');
        });

        Schema::create('cash_transactions', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->foreignId('company_id')->nullableForeignId('cash_account_id');
            $t->string('type', 20);                        // collection|payment|transfer|opening
            $t->date('date');
            $t->decimal('amount');
            $t->string('description', 500, true);
            $t->string('reference_type', 40, true);
            $t->string('reference_id', 40, true);
            $t->timestamps();
            $t->index('cash_account_id');
            $t->index('company_id');
            $t->foreign('cash_account_id', 'cash_accounts');
        });

        // ---- bank accounts ----
        Schema::create('bank_accounts', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->foreignId('company_id');
            $t->string('bank_name', 255);
            $t->string('account_name', 255, true);
            $t->string('iban', 50, true);
            $t->string('account_number', 60, true);
            $t->string('branch', 190, true);
            $t->string('currency', 8, true, 'TRY');
            $t->decimal('balance');
            $t->string('status', 20, true, 'active');
            $t->timestamps();
            $t->softDeletes();
            $t->index('company_id');
        });

        Schema::create('bank_transactions', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->foreignId('company_id')->nullableForeignId('bank_account_id');
            $t->string('type', 20);                        // deposit|withdrawal|transfer|fee|interest
            $t->date('date');
            $t->decimal('amount');
            $t->string('description', 500, true);
            $t->string('reference_type', 40, true);
            $t->string('reference_id', 40, true);
            $t->timestamps();
            $t->index('bank_account_id');
            $t->index('company_id');
            $t->foreign('bank_account_id', 'bank_accounts');
        });

        // ---- checks (çek) ----
        Schema::create('checks', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->foreignId('company_id');
            $t->foreignId('current_account_id')->nullableForeignId('bank_account_id');
            $t->string('direction', 20, true, 'incoming'); // incoming|outgoing
            $t->string('status', 24, true, 'in_portfolio');// in_portfolio|banked|collected|endorsed|returned|unpaid|cancelled
            $t->string('check_no', 64, true);
            $t->date('issue_date', true);
            $t->date('due_date');
            $t->decimal('amount');
            $t->string('bank', 190, true);
            $t->string('branch', 190, true);
            $t->string('notes', 500, true);
            $t->timestamps();
            $t->softDeletes();
            $t->index('company_id');
            $t->index('status');
        });

        // ---- promissory notes (senet) ----
        Schema::create('promissory_notes', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->foreignId('company_id');
            $t->foreignId('current_account_id')->nullableForeignId('bank_account_id');
            $t->string('direction', 20, true, 'incoming'); // incoming|outgoing
            $t->string('status', 24, true, 'in_portfolio');// in_portfolio|banked|collected|endorsed|returned|unpaid|cancelled
            $t->string('note_no', 64, true);
            $t->date('issue_date', true);
            $t->date('due_date');
            $t->decimal('amount');
            $t->string('bank', 190, true);
            $t->string('notes', 500, true);
            $t->timestamps();
            $t->softDeletes();
            $t->index('company_id');
            $t->index('status');
        });
    }

    public function down(): void
    {
        foreach (['promissory_notes', 'checks', 'bank_transactions', 'bank_accounts',
                  'cash_transactions', 'cash_accounts'] as $table) {
            Schema::drop($table);
        }
    }
};
