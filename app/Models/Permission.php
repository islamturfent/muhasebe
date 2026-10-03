<?php

declare(strict_types=1);

namespace Muh\Models;

use Muh\Core\DB;
use Muh\Core\Model;

/**
 * Permissions are granular "key" privileges used by RBAC.
 * Keys follow a module.scope.action convention, e.g. invoice.create,
 * report.view, company.read.
 */
final class Permission extends Model
{
    protected string $table = 'permissions';

    public static function allGrouped(): array
    {
        $rows = DB::select('SELECT * FROM permissions ORDER BY module, sort_order, key');
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['module']][] = $row;
        }
        return $grouped;
    }

    public static function syncFrom(array $definitions): void
    {
        $keyCol = DB::quoteIdentifier('key');
        $existing = DB::select('SELECT id, ' . $keyCol . ' FROM permissions');
        $map = [];
        foreach ($existing as $row) {
            $map[$row['key']] = $row['id'];
        }

        $order = 0;
        foreach ($definitions as $module => $perms) {
            foreach ($perms as $action => $label) {
                $key = $module . '.' . $action;
                $data = [
                    'module'    => $module,
                    'key'       => $key,
                    'action'    => $action,
                    'sort_order'=> $order++,
                ];
                if (isset($map[$key])) {
                    DB::update('permissions', $data, 'id = :id', ['id' => $map[$key]]);
                } else {
                    DB::insert('permissions', $data);
                }
            }
        }
    }
}
