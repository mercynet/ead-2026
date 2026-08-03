<?php

namespace App\Modules\Core\Exceptions;

use RuntimeException;

final class TenantAlreadyExistsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Não foi possível criar o tenant.');
    }
}
