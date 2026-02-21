<?php

namespace App\Exceptions;

use RuntimeException;

class ResourceNotFoundException extends RuntimeException
{
    protected string $resource;

    public static function make(string $resource, string|int $identifier): self
    {
        $exception = new self(sprintf('%s not found.', $resource));
        $exception->resource = $resource;

        return $exception;
    }

    public static function course(string|int $identifier): self
    {
        return self::make('Course', $identifier);
    }

    public function getResource(): string
    {
        return $this->resource;
    }
}
