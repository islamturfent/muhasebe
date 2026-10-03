<?php

declare(strict_types=1);

namespace Muh\Middleware;

use Muh\Core\Request;
use Muh\Core\Response;

/**
 * Middleware contract.
 */
abstract class Middleware
{
    /**
     * Return a Response to short-circuit the request, or null to continue.
     */
    abstract public function handle(Request $request): ?Response;
}
