<?php

namespace App\Shared\Exceptions;

use RuntimeException;

/**
 * Falha genérica de redefinição de senha. Mensagem uniforme para token
 * inexistente, adulterado, expirado ou já usado — não vaza qual condição
 * falhou (evita enumeração).
 */
class PasswordResetInvalidException extends RuntimeException
{
    public static function make(): self
    {
        return new self('Password reset token is invalid or has expired.');
    }
}
