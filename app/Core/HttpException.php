<?php

declare(strict_types=1);

namespace Muh\Core;

use RuntimeException;

/**
 * Base HTTP exception carrying an HTTP status code.
 */
class HttpException extends RuntimeException
{
    public function __construct(string $message, int $status = 500)
    {
        parent::__construct($message, $status);
    }
}
