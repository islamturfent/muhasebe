<?php

declare(strict_types=1);

namespace Muh\Models;

use Muh\Core\Model;

final class Company extends Model
{
    protected string $table = 'companies';
    protected bool $softDeletes = true;
}
