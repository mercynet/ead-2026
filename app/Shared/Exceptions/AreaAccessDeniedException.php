<?php

namespace App\Shared\Exceptions;

use App\Modules\Core\Enums\Area;
use RuntimeException;

class AreaAccessDeniedException extends RuntimeException
{
    public static function make(Area $area): self
    {
        return new self(sprintf('Acesso negado à área %s.', $area->value));
    }
}
