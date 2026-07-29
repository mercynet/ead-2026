<?php

namespace App\Modules\Financial\Enums;

enum PaymentChargeStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
}
