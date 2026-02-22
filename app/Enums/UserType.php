<?php

namespace App\Enums;

enum UserType: string
{
    case Developer = 'developer';
    case Admin = 'admin';
    case Instructor = 'instructor';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::Developer => 'Developer',
            self::Admin => 'Admin',
            self::Instructor => 'Instructor',
            self::Student => 'Student',
        };
    }

    public function isDeveloper(): bool
    {
        return $this === self::Developer;
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    public function isInstructor(): bool
    {
        return $this === self::Instructor;
    }

    public function isStudent(): bool
    {
        return $this === self::Student;
    }

    public function canAccessAllTenants(): bool
    {
        return $this === self::Developer;
    }

    public function canAccessOwnTenantOnly(): bool
    {
        return in_array($this, [self::Admin, self::Instructor, self::Student]);
    }

    public function canManageContent(): bool
    {
        return in_array($this, [self::Developer, self::Admin, self::Instructor]);
    }
}
