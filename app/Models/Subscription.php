<?php

declare(strict_types=1);

namespace Muh\Models;

use Muh\Core\Model;

/**
 * Subscription linking a tenant to a plan with a lifecycle status:
 * trial | active | past_due | cancelled | expired
 */
final class Subscription extends Model
{
    protected string $table = 'subscriptions';
    protected bool $softDeletes = true;
}
