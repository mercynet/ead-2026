<?php

use App\Modules\Core\Enums\Area;
use App\Modules\Core\Enums\UserType;

it('grants each area exactly the user types at or above its rank', function (Area $area, array $allowed): void {
    foreach (UserType::cases() as $userType) {
        expect($area->allows($userType))->toBe(in_array($userType, $allowed, true));
    }
})->with([
    'mzrt admite só developer' => [Area::Mzrt, [UserType::Developer]],
    'admin admite admin + developer' => [Area::Admin, [UserType::Admin, UserType::Developer]],
    'instructor admite instructor + acima' => [Area::Instructor, [UserType::Instructor, UserType::Admin, UserType::Developer]],
    'student admite todos os tipos' => [Area::Student, [UserType::Student, UserType::Instructor, UserType::Admin, UserType::Developer]],
    'home admite todos os tipos' => [Area::Home, [UserType::Student, UserType::Instructor, UserType::Admin, UserType::Developer]],
]);

it('requires authentication on every area except home', function (): void {
    expect(Area::Home->requiresAuthentication())->toBeFalse();

    foreach ([Area::Mzrt, Area::Admin, Area::Instructor, Area::Student] as $area) {
        expect($area->requiresAuthentication())->toBeTrue();
    }
});

it('ranks user types in strict descending privilege order', function (): void {
    expect(UserType::Developer->rank())->toBeGreaterThan(UserType::Admin->rank())
        ->and(UserType::Admin->rank())->toBeGreaterThan(UserType::Instructor->rank())
        ->and(UserType::Instructor->rank())->toBeGreaterThan(UserType::Student->rank());
});
