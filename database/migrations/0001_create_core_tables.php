<?php

declare(strict_types=1);

use Muh\Database\Schema;
use Muh\Core\DB;

return new class {
    public function up(): void
    {
        // ---- tenants (accounting offices - top of the data hierarchy) ----
        Schema::create('tenants', function ($t) {
            $t->id();
            $t->string('name', 255);
            $t->string('slug', 160);
            $t->string('legal_name', 255, true);
            $t->string('email', 190, true);
            $t->string('phone', 40, true);
            $t->string('country', 4)->string('locale', 8)->string('currency', 8);
            $t->string('address', 500, true);
            $t->string('tax_number', 40, true)->string('tax_office', 190, true);
            $t->string('logo_path', 500, true);
            $t->string('status', 20, true, 'active');
            $t->timestamps();
            $t->softDeletes();
            $t->unique('slug');
        });

        // ---- users ----
        Schema::create('users', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->string('name', 190);
            $t->string('email', 190);
            $t->string('phone', 40, true);
            $t->string('password', 255);
            $t->string('avatar_path', 500, true);
            $t->string('locale', 8, true);
            $t->string('currency', 8, true);
            $t->string('status', 20, true, 'active');       // pending|active|suspended
            $t->boolean('is_owner')->boolean('is_system_admin');
            $t->dateTime('last_login_at');
            $t->string('last_login_ip', 45, true);
            $t->string('two_factor_secret', 255, true);
            $t->boolean('two_factor_enabled');
            $t->string('remember_token', 100, true);
            $t->timestamps();
            $t->softDeletes();
            $t->unique('email');
            $t->index('tenant_id');
            $t->foreign('tenant_id', 'tenants');
        });

        // ---- roles ----
        Schema::create('roles', function ($t) {
            $t->id();
            $t->string('key', 64);
            $t->string('name', 190);
            $t->boolean('is_system')->boolean('is_owner');
            $t->string('description', 500, true);
            $t->timestamps();
            $t->softDeletes();
            $t->unique('key');
        });

        // ---- permissions ----
        Schema::create('permissions', function ($t) {
            $t->id();
            $t->string('module', 64);
            $t->string('key', 190);
            $t->string('action', 64);
            $t->integer('sort_order');
            $t->timestamps();
            $t->unique('key');
            $t->index('module');
        });

        // ---- role_permission (pivot) ----
        Schema::create('role_permission', function ($t) {
            $t->id();
            $t->foreignId('role_id')->foreignId('permission_id');
            $t->timestamps();
            $t->unique(['role_id', 'permission_id']);
        });

        // ---- user_role (pivot) ----
        Schema::create('user_role', function ($t) {
            $t->id();
            $t->foreignId('user_id')->foreignId('role_id');
            $t->timestamps();
            $t->unique(['user_id', 'role_id']);
        });

        // ---- plans ----
        Schema::create('plans', function ($t) {
            $t->id();
            $t->string('code', 32);
            $t->string('name', 190);
            $t->text('description', true);
            $t->decimal('price_monthly');
            $t->decimal('price_yearly');
            $t->string('currency', 8, true, 'TRY');
            $t->json('features');        // limits & feature flags
            $t->integer('sort_order');
            $t->boolean('is_active');
            $t->timestamps();
            $t->softDeletes();
            $t->unique('code');
        });

        // ---- subscriptions ----
        Schema::create('subscriptions', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->foreignId('plan_id');
            $t->string('status', 20, true, 'trial'); // trial|active|past_due|cancelled|expired
            $t->dateTime('trial_ends_at');
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->dateTime('cancelled_at');
            $t->string('billing_cycle', 20, true, 'monthly'); // monthly|yearly
            $t->string('payment_provider', 40, true);
            $t->string('provider_customer_id', 190, true);
            $t->string('provider_subscription_id', 190, true);
            $t->timestamps();
            $t->softDeletes();
            $t->index('tenant_id');
            $t->index('status');
        });

        // ---- tenant settings (key/value) ----
        Schema::create('settings', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->string('group', 64)->string('key', 190);
            $t->text('value', true);
            $t->timestamps();
            $t->unique(['tenant_id', 'group', 'key']);
        });

        // ---- failed login tracking ----
        Schema::create('login_attempts', function ($t) {
            $t->id();
            $t->string('email', 190, true);
            $t->string('ip', 45);
            $t->boolean('success');
            $t->dateTime('attempted_at');
            $t->index(['email', 'ip']);
        });

        // ---- user invites ----
        Schema::create('user_invites', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->foreignId('invited_by');
            $t->string('email', 190);
            $t->string('token', 100);
            $t->foreignId('role_id');
            $t->string('status', 20, true, 'pending'); // pending|accepted|expired
            $t->dateTime('expires_at');
            $t->dateTime('accepted_at', true);
            $t->timestamps();
            $t->unique('token');
            $t->index('email');
        });

        // ---- API tokens (for headless / integrations) ----
        Schema::create('api_tokens', function ($t) {
            $t->id();
            $t->foreignId('user_id');
            $t->string('name', 190, true);
            $t->string('token_hash', 255);
            $t->dateTime('last_used_at', true);
            $t->dateTime('expires_at', true);
            $t->timestamps();
            $t->index('token_hash');
        });
    }

    public function down(): void
    {
        foreach (['api_tokens', 'user_invites', 'login_attempts', 'settings', 'subscriptions',
                  'plans', 'user_role', 'role_permission', 'permissions', 'roles',
                  'users', 'tenants'] as $table) {
            Schema::drop($table);
        }
    }
};
