<?php

declare(strict_types=1);

namespace Muh\Models;

use Muh\Core\Model;

final class FiscalPeriod extends Model
{
    protected string $table = 'fiscal_periods';
    protected bool $softDeletes = true;
}
