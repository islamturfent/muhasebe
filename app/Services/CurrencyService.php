<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\DB;

/**
 * Currency & exchange-rate helpers (multi-currency, Phase 14).
 *
 * Rates are stored in `exchange_rates` (from_currency, to_currency, date, rate)
 * and are managed via the office settings screen. All monetary math stays in
 * the database NUMERIC columns; here we only read/format/convert on demand.
 */
final class CurrencyService
{
    /** @return array<int,array{code:string,name:string,symbol:string,rate:float}> */
    public static function all(): array
    {
        $rows = DB::select('SELECT * FROM currencies ORDER BY code');
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'code' => (string) $r['code'],
                'name' => (string) ($r['name'] ?? ''),
                'symbol' => (string) ($r['symbol'] ?? ''),
                'rate' => 1.0, // default: assume TRY==1 unless overridden below
            ];
        }
        // Fill latest TRY-based rate for non-TRY currencies.
        $today = date('Y-m-d');
        foreach ($out as $i => $cur) {
            if ($cur['code'] === 'TRY') {
                $out[$i]['rate'] = 1.0;
                continue;
            }
            $out[$i]['rate'] = (float) self::rateToTry($cur['code'], $today);
        }
        return $out;
    }

    /** Rate that converts 1 unit of $from into TRY on the given date (latest ≤ date). */
    public static function rateToTry(string $from, string $date): float
    {
        if (strtoupper($from) === 'TRY') {
            return 1.0;
        }
        $row = DB::first(
            'SELECT rate FROM exchange_rates WHERE from_currency = :f AND to_currency = :t AND date <= :d ORDER BY date DESC LIMIT 1',
            ['f' => $from, 't' => 'TRY', 'd' => $date]
        );
        return (float) ($row['rate'] ?? 0.0);
    }

    /** Convert $amount from $from to $to on the given date (latest known rate). */
    public static function convert(string $from, string $to, float $amount, ?string $date = null): float
    {
        $date = $date ?? date('Y-m-d');
        if (strtoupper($from) === strtoupper($to)) {
            return $amount;
        }
        $fromTry = self::rateToTry($from, $date); // how much TRY per 1 $from
        $toTry = self::rateToTry($to, $date);     // how much TRY per 1 $to
        if ($fromTry <= 0 || $toTry <= 0) {
            return $amount; // unknown rate → passthrough (never fabricate)
        }
        return $amount * ($fromTry / $toTry);
    }

    /** Upsert an exchange rate for a date (from→to). */
    public static function setRate(string $from, string $to, float $rate, ?string $date = null): void
    {
        $date = $date ?? date('Y-m-d');
        $exists = DB::first(
            'SELECT id FROM exchange_rates WHERE from_currency = :f AND to_currency = :t AND date = :d',
            ['f' => $from, 't' => $to, 'd' => $date]
        );
        if ($exists) {
            DB::execute('UPDATE exchange_rates SET rate = :r, updated_at = :n WHERE id = :id', ['r' => $rate, 'n' => now(), 'id' => (int) $exists['id']]);
        } else {
            DB::insert('exchange_rates', [
                'from_currency' => $from, 'to_currency' => $to, 'date' => $date, 'rate' => $rate,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public static function symbol(string $code): string
    {
        static $map = ['TRY' => '₺', 'USD' => '$', 'EUR' => '€', 'GBP' => '£'];
        return $map[strtoupper($code)] ?? strtoupper($code);
    }
}
