<?php

namespace App\Modules\Assessment\Enums;

enum CertificateStatus: string
{
    case ISSUED = 'issued';
    case REVOKED = 'revoked';
}
