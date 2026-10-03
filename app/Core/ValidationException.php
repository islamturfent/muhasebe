<?php

declare(strict_types=1);

namespace Muh\Core;

class ValidationException extends HttpException
{
    public array $errors;

    public function __construct(array $errors, string $message = 'Validation failed')
    {
        $this->errors = $errors;
        parent::__construct($message, 422);
    }
}
