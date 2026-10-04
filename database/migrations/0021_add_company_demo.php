<?php

declare(strict_types=1);

use Muh\Core\DB;

/**
 * Demo-data flag (Item 4 / production polish): marks a company as sample/demo
 * so the UI can show a non-intrusive banner and operators can exclude demo data
 * from meaningful financial totals/audit. Defaults to 0 for real companies.
 */
return new class {
    public function up(): void
    {
        $cols = DB::select('SHOW COLUMNS FROM companies');
        $has = false;
        foreach ($cols as $c) {
            if (strtolower((string) $c['Field']) === 'is_demo') {
                $has = true;
                break;
            }
        }
        if (!$has) {
            DB::execute('ALTER TABLE companies ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0 AFTER status');
        }
    }

    public function down(): void
    {
        DB::execute('ALTER TABLE companies DROP COLUMN IF EXISTS is_demo');
    }
};
