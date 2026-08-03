<?php

namespace App\Modules\Core\Exceptions;

use App\Modules\Core\Models\User;
use RuntimeException;

final class AdminPromotionRequiredException extends RuntimeException
{
    public function __construct(public readonly User $admin)
    {
        parent::__construct('Admin promotion requires explicit approval.');
    }
}
