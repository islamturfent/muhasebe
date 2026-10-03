<?php

declare(strict_types=1);

/**
 * Default units (birimler). Shared across all tenants (tenant_id = null). Can be
 * extended per-tenant later.
 */
use Muh\Core\DB;

return function (): void {
    $now = now();
    $units = [
        ['name' => 'Adet',   'abbr' => 'adet'],
        ['name' => 'Kilogram', 'abbr' => 'kg'],
        ['name' => 'Metre',  'abbr' => 'm'],
        ['name' => 'Litre',  'abbr' => 'lt'],
        ['name' => 'Saat',   'abbr' => 'saat'],
        ['name' => 'Paket',  'abbr' => 'paket'],
    ];
    foreach ($units as $u) {
        $exists = DB::first('SELECT id FROM units WHERE abbr = :a', ['a' => $u['abbr']]);
        if (!$exists) {
            DB::insert('units', array_merge($u, ['created_at' => $now, 'updated_at' => $now]));
        }
    }
};
