<?php

namespace App\Exceptions;

use RuntimeException;

class AccessDeniedException extends RuntimeException
{
    protected string $resource;

    public static function make(string $resource, string|int $identifier): self
    {
        $exception = new self(sprintf('Access denied to %s.', $resource));
        $exception->resource = $resource;

        return $exception;
    }

    public static function lesson(string|int $identifier): self
    {
        return self::make('lesson', $identifier);
    }

    public function getResource(): string
    {
        return $this->resource;
    }
}
