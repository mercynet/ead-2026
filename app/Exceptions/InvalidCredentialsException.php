<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidCredentialsException extends RuntimeException
{
    public static function make(): self
    {
        return new self('Invalid credentials.');
    }
}
