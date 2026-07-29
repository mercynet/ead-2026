<?php

namespace App\Modules\Financial\Exceptions;

use RuntimeException;

class CheckoutConflictException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
