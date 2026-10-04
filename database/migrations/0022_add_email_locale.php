<?php

declare(strict_types=1);

use Muh\Core\DB;

/**
 * Per-recipient e-mail language (Item 4): a `locale` column on companies and
 * current_accounts so automated e-mails (invoice reminders, invites...) can be
 * rendered in the client's preferred language (tr/en). NULL defaults to the
 * office tenant's locale.
 */
return new class {
    public function up(): void
    {
        $for = function (string $table) {
            $cols = DB::select('SHOW COLUMNS FROM ' . $table);
            $has = false;
            foreach ($cols as $c) {
                if (strtolower((string) $c['Field']) === 'locale') {
                    $has = true;
                }
            }
            if (!$has) {
                DB::execute('ALTER TABLE ' . $table . ' ADD COLUMN locale VARCHAR(8) NULL DEFAULT NULL AFTER status');
            }
        };
        $for('companies');
        $for('current_accounts');
    }

    public function down(): void
    {
        foreach (['companies', 'current_accounts'] as $table) {
            DB::execute('ALTER TABLE ' . $table . ' DROP COLUMN IF EXISTS locale');
        }
    }
};
