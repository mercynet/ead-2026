<?php

namespace App\Modules\Financial\Exceptions;

use RuntimeException;

class PaymentPersistenceException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Não foi possível persistir o resultado do pagamento.');
    }
}
