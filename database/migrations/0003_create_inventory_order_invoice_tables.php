<?php

declare(strict_types=1);

use Muh\Database\Schema;

return new class {
    public function up(): void
    {
        // ---- warehouses/depots ----
        Schema::create('warehouses', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->foreignId('company_id');
            $t->string('name', 255);
            $t->string('code', 40);
            $t->string('address', 500, true);
            $t->boolean('is_default');
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['company_id', 'code']);
            $t->index('company_id');
        });

        // ---- units ----
        Schema::create('units', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->string('name', 64);
            $t->string('abbr', 16);
            $t->timestamps();
            $t->unique(['tenant_id', 'abbr']);
        });

        // ---- products / services / stock cards ----
        Schema::create('products', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->foreignId('company_id')->nullableForeignId('unit_id');
            $t->string('code', 64);
            $t->string('name', 255);
            $t->string('barcode', 64, true);
            $t->string('type', 20, true, 'product');      // product|service
            $t->decimal('purchase_price');
            $t->decimal('sale_price');
            $t->decimal('vat_rate');
            $t->decimal('stock_quantity');
            $t->decimal('critical_stock');
            $t->string('description', 1000, true);
            $t->string('status', 20, true, 'active');
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['company_id', 'code']);
            $t->index('tenant_id');
            $t->index('barcode');
            $t->foreign('company_id', 'companies');
            $t->foreign('unit_id', 'units');
        });

        // ---- stock movements ----
        Schema::create('stock_movements', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->foreignId('company_id');
            $t->foreignId('warehouse_id')->nullableForeignId('product_id');
            $t->string('type', 20);                        // purchase|sale|return|transfer_in|transfer_out|adjustment
            $t->date('date');
            $t->decimal('quantity');
            $t->decimal('unit_price');
            $t->decimal('total');
            $t->string('reference_type', 40, true);
            $t->string('reference_id', 40, true);
            $t->string('description', 500, true);
            $t->timestamps();
            $t->index('product_id');
            $t->index('warehouse_id');
            $t->index('company_id');
            $t->foreign('product_id', 'products');
            $t->foreign('warehouse_id', 'warehouses');
        });

        // ---- orders ----
        Schema::create('orders', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->foreignId('company_id');
            $t->foreignId('branch_id')->nullableForeignId('currency_id')->nullableForeignId('warehouse_id');
            $t->string('number', 40);
            $t->string('type', 20, true, 'sales');         // sales|purchase
            $t->string('status', 24, true, 'draft');       // draft|confirmed|shipped|completed|cancelled
            $t->date('date');
            $t->date('delivery_date', true);
            $t->decimal('subtotal');
            $t->decimal('discount');
            $t->decimal('tax');
            $t->decimal('total');
            $t->text('notes', true);
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['company_id', 'number']);
            $t->index('company_id');
            $t->index('status');
        });

        // ---- order items ----
        Schema::create('order_items', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->foreignId('company_id')->nullableForeignId('order_id')->nullableForeignId('product_id')->nullableForeignId('unit_id');
            $t->decimal('quantity');
            $t->decimal('unit_price');
            $t->decimal('discount');
            $t->decimal('tax_rate');
            $t->decimal('tax');
            $t->decimal('total');
            $t->index('order_id');
            $t->foreign('order_id', 'orders');
            $t->foreign('product_id', 'products');
        });

        // ---- invoices ----
        Schema::create('invoices', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->foreignId('company_id');
            $t->foreignId('fiscal_period_id')->nullableForeignId('branch_id')->nullableForeignId('customer_id')->nullableForeignId('currency_id')->nullableForeignId('order_id')->nullableForeignId('current_account_id');
            $t->string('number', 40);
            $t->string('type', 20, true, 'sales');          // sales|purchase|sales_return|purchase_return|proforma
            $t->string('status', 24, true, 'draft');        // draft|posted|cancelled
            $t->string('efatura_status', 24, true, 'draft');// draft|sending|sent|accepted|rejected|error
            $t->date('date');
            $t->date('due_date', true);
            $t->string('document_no', 40, true);
            $t->decimal('subtotal');
            $t->decimal('discount');
            $t->decimal('tax');
            $t->decimal('total');
            $t->decimal('paid');
            $t->string('status_label', 30, true);
            $t->longText('notes');
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['company_id', 'number']);
            $t->index('company_id');
            $t->index('current_account_id');
            $t->index('status');
            $t->foreign('company_id', 'companies');
            $t->foreign('fiscal_period_id', 'fiscal_periods');
            $t->foreign('current_account_id', 'current_accounts');
        });

        // ---- invoice items ----
        Schema::create('invoice_items', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->foreignId('company_id')->nullableForeignId('invoice_id')->nullableForeignId('product_id')->nullableForeignId('unit_id');
            $t->decimal('quantity');
            $t->decimal('unit_price');
            $t->decimal('discount');
            $t->decimal('tax_rate');
            $t->decimal('tax');
            $t->decimal('line_total');
            $t->decimal('total');
            $t->index('invoice_id');
            $t->index('product_id');
            $t->foreign('invoice_id', 'invoices');
            $t->foreign('product_id', 'products');
        });
    }

    public function down(): void
    {
        foreach (['invoice_items', 'invoices', 'order_items', 'orders', 'stock_movements',
                  'products', 'units', 'warehouses'] as $table) {
            Schema::drop($table);
        }
    }
};
