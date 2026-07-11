<?php

namespace App\Modules\Core\Enums;

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

    /**
     * Posto na hierarquia de personas (maior = mais privilégio). Usado pela
     * área para decidir se um tipo pode entrar em superfícies abaixo dele.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Developer => 4,
            self::Admin => 3,
            self::Instructor => 2,
            self::Student => 1,
        };
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
