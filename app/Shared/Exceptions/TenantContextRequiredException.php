<?php

namespace App\Shared\Exceptions;

use RuntimeException;

class TenantContextRequiredException extends RuntimeException
{
    public static function make(string $message = 'Tenant context is required.'): self
    {
        return new self($message);
    }
}
