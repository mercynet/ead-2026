<?php

namespace App\Exceptions;

use RuntimeException;

class TenantContextRequiredException extends RuntimeException
{
    public static function make(): self
    {
        return new self('Tenant context is required.');
    }
}
