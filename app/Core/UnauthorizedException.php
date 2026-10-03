<?php

declare(strict_types=1);

namespace Muh\Core;

class UnauthorizedException extends HttpException
{
    public function __construct(string $message = 'Unauthorized')
    {
        parent::__construct($message, 401);
    }
}
