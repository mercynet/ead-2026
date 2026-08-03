<?php

namespace App\Modules\Core\Exceptions;

use Illuminate\Database\UniqueConstraintViolationException;
use RuntimeException;

class TenantDomainCreationCollisionException extends RuntimeException
{
    public function __construct(public readonly UniqueConstraintViolationException $constraintViolation)
    {
        parent::__construct($constraintViolation->getMessage(), 0, $constraintViolation);
    }
}
