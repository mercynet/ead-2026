<?php

namespace App\Modules\Financial\Enums;

enum PaymentChargeState: string
{
    case Created = 'created';
    case Processing = 'processing';
    case Resolved = 'resolved';
    case Unknown = 'unknown';
}
