<?php

namespace App\Enums;

enum CertificateStatus: string
{
    case ISSUED = 'issued';
    case REVOKED = 'revoked';
}
