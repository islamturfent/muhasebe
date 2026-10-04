<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\Session;

/**
 * Session helpers for the active company/fiscal period context.
 */
final class SessionContext
{
    public static function setCompany(int $companyId): void
    {
        Session::set('active_company_id', $companyId);
    }

    public static function companyId(): ?int
    {
        return Session::get('active_company_id');
    }

    public static function setPeriod(int $periodId): void
    {
        Session::set('active_fiscal_period_id', $periodId);
    }

    public static function forgetCompany(): void
    {
        Session::forget('active_company_id');
        Session::forget('active_fiscal_period_id');
    }

    public static function periodId(): ?int
    {
        return Session::get('active_fiscal_period_id');
    }
}
