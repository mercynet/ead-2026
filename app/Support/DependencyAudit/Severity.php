<?php

namespace App\Support\DependencyAudit;

enum Severity: int
{
    case Info = 0;
    case Low = 10;
    case Medium = 20;
    case High = 30;
    case Critical = 40;

    public static function fromString(string $value): self
    {
        return match (strtolower($value)) {
            'info' => self::Info,
            'low' => self::Low,
            'medium' => self::Medium,
            'high' => self::High,
            'critical' => self::Critical,
            default => throw new \InvalidArgumentException("Unknown severity [{$value}]."),
        };
    }

    public function label(): string
    {
        return strtolower($this->name);
    }

    public function meetsOrExceeds(self $other): bool
    {
        return $this->value >= $other->value;
    }
}
