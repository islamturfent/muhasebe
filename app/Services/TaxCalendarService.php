<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\DB;

/**
 * Turkey-specific tax calendar (vergi takvimi) & obligations.
 *
 * Generates a plausible default calendar for a year (KDV beyanname, muhtasar/
 * stopaj, geçici vergi, yıllık) with due dates. All entries are editable by the
 * office, so exact national dates can be adjusted by the user.
 */
final class TaxCalendarService
{
    /** Insert default Turkish tax obligations for a year. Returns count inserted. */
    public static function generate(int $tenantId, int $year, ?int $companyId = null): int
    {
        $rows = [];
        $now = now();
        for ($month = 1; $month <= 12; $month++) {
            // KDV Beyannamesi — due around the 25th of the following month.
            $rows[] = static::row($tenantId, $companyId, 'KDV Beyannamesi [' . $year . '-' . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . ']', 'kdv', $year . '-' . str_pad((string) $month, 2, '0', STR_PAD_LEFT), static::dueInMonth($year, $month + 1, 25), $now);
            // Muhtasar & Prim Hizmet Beyannamesi — due around the 27th.
            $rows[] = static::row($tenantId, $companyId, 'Muhtasar & Stopaj [' . $year . '-' . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . ']', 'muhtasar', $year . '-' . str_pad((string) $month, 2, '0', STR_PAD_LEFT), static::dueInMonth($year, $month + 1, 27), $now);
        }
        // Geçici (3-month) tax periods.
        foreach ([['Nisan', 4, 17], ['Temmuz', 7, 17], ['Ekim', 10, 17], ['Ocak', 13, 17]] as [$label, $m, $d]) {
            $rows[] = static::row($tenantId, $companyId, 'Geçici Vergi Dönemi (' . $label . ') ' . $year, 'gecici', $year . '-Q', static::dueInMonth($year, $m, $d), $now);
        }
        // Annual income / corporate tax — due in May.
        $rows[] = static::row($tenantId, $companyId, 'Yıllık Gelir / Kurumlar Vergisi ' . $year, 'annual', (string) $year, static::dueInMonth($year, 5, 27), $now);

        $count = 0;
        foreach ($rows as $r) {
            DB::insert('tax_obligations', $r);
            $count++;
        }
        return $count;
    }

    /** Overdue count for the office (active pending whose due date passed). */
    public static function overdueCount(int $tenantId): int
    {
        return (int) DB::scalar(
            "SELECT COUNT(*) FROM tax_obligations
              WHERE tenant_id = :t AND deleted_at IS NULL AND status = 'pending' AND due_date IS NOT NULL AND due_date < :today",
            ['t' => $tenantId, 'today' => date('Y-m-d')]
        );
    }

    /** Upcoming (within $days) count. */
    public static function upcomingCount(int $tenantId, int $days = 15): int
    {
        return (int) DB::scalar(
            "SELECT COUNT(*) FROM tax_obligations
              WHERE tenant_id = :t AND deleted_at IS NULL AND status = 'pending' AND due_date IS NOT NULL
                AND due_date >= :today AND due_date <= :limit",
            ['t' => $tenantId, 'today' => date('Y-m-d'), 'limit' => date('Y-m-d', strtotime('+' . $days . ' days'))]
        );
    }

    private static function row(int $tenantId, ?int $companyId, string $name, string $type, string $period, string $due, string $now): array
    {
        return [
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'name' => $name,
            'obligation_type' => $type,
            'period_label' => $period,
            'due_date' => $due,
            'amount' => 0,
            'status' => 'pending',
            'notes' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /** Due date helper: day $d of month $m (1-12, or 13 = January next year). */
    private static function dueInMonth(int $year, int $month, int $day): string
    {
        $y = $year;
        if ($month > 12) {
            $y++;
            $month -= 12;
        }
        $m = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        return $y . '-' . $m . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
    }
}
