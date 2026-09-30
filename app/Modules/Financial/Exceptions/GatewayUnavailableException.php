<?php

namespace App\Modules\Financial\Exceptions;

use RuntimeException;

class GatewayUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Gateway de pagamento indisponível.');
    }
}
