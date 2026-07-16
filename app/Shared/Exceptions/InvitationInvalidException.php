<?php

namespace App\Shared\Exceptions;

use RuntimeException;

/**
 * Falha genérica de aceite de convite. A mensagem é deliberadamente uniforme
 * para token inexistente, adulterado, expirado ou já usado — não vaza qual
 * condição falhou (evita enumeração de convites).
 */
class InvitationInvalidException extends RuntimeException
{
    public static function make(): self
    {
        return new self('Invitation is invalid or has expired.');
    }
}
