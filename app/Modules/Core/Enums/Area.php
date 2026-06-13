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

    /**
     * Posto mínimo de UserType para alcançar a área. A hierarquia de UserType
     * (developer > admin > instructor > student) permite que um tipo mais alto
     * entre em áreas abaixo (`rbac.md` §1). `Home` é público (posto 0).
     */
    public function minimumRank(): int
    {
        return match ($this) {
            self::Mzrt => UserType::Developer->rank(),
            self::Admin => UserType::Admin->rank(),
            self::Instructor => UserType::Instructor->rank(),
            self::Student => UserType::Student->rank(),
            self::Home => 0,
        };
    }

    public function allows(UserType $userType): bool
    {
        return $userType->rank() >= $this->minimumRank();
    }

    public function requiresAuthentication(): bool
    {
        return $this !== self::Home;
    }
}
