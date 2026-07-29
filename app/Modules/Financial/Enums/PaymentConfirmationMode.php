<?php

namespace App\Modules\Financial\Enums;

enum PaymentConfirmationMode: string
{
    case Manual = 'manual';
    case Automatic = 'automatic';
}
