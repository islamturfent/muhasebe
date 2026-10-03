<?php

declare(strict_types=1);

namespace Muh\Models;

use Muh\Core\Model;

/**
 * A named role (Office Owner, Certified Public Accountant, Accountant,
 * Staff, Intern, Client Authorised, Viewer). Permissions are bound through
 * role_permission.
 */
final class Role extends Model
{
    protected string $table = 'roles';
}
