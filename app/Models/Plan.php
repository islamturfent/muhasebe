<?php

declare(strict_types=1);

namespace Muh\Models;

use Muh\Core\Model;

/**
 * Subscription plan. Features/limits are data-driven (not hard-coded).
 */
final class Plan extends Model
{
    protected string $table = 'plans';

    public static function findByCode(string $code): ?array
    {
        foreach (self::whereAll(['code' => strtoupper($code), 'is_active' => 1]) as $plan) {
            return $plan;
        }
        return null;
    }

    public static function allActive(): array
    {
        return self::whereAll(['is_active' => 1]);
    }

    /** Decode the JSON features/limits blob into an array. */
    public static function features(array $plan): array
    {
        $features = json_decode($plan['features'] ?? '{}', true);
        return is_array($features) ? $features : [];
    }
}
