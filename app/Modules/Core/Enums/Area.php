<?php

namespace App\Modules\Core\Enums;

/**
 * Superfícies (audiências) do sistema. Cada área é um namespace de API estável
 * que um frontend consome. A área restringe QUAL superfície o usuário alcança;
 * o RBAC restringe O QUE ele faz lá. Ver docs/specs/00-architecture/areas-surfaces.md.
 */
enum Area: string
{
    case Mzrt = 'mzrt';
    case Admin = 'admin';
    case Instructor = 'instructor';
    case Student = 'student';
    case Home = 'home';

    public function allows(UserType $userType): bool
    {
        return match ($this) {
            self::Mzrt => $userType === UserType::Developer,
            self::Admin => $userType === UserType::Admin,
            self::Instructor => $userType === UserType::Instructor,
            self::Student => $userType === UserType::Student,
            self::Home => true,
        };
    }

    public function requiresAuthentication(): bool
    {
        return $this !== self::Home;
    }
}
