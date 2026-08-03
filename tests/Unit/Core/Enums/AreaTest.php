<?php

use App\Modules\Core\Enums\Area;
use App\Modules\Core\Enums\UserType;

it('grants each area exactly its persona', function (Area $area, array $allowed): void {
    foreach (UserType::cases() as $userType) {
        expect($area->allows($userType))->toBe(in_array($userType, $allowed, true));
    }
})->with([
    'mzrt admite só developer' => [Area::Mzrt, [UserType::Developer]],
    'admin admite só admin' => [Area::Admin, [UserType::Admin]],
    'instructor admite só instructor' => [Area::Instructor, [UserType::Instructor]],
    'student admite só student' => [Area::Student, [UserType::Student]],
    'home admite qualquer usuário autenticado' => [Area::Home, UserType::cases()],
]);

it('requires authentication on every area except home', function (): void {
    expect(Area::Home->requiresAuthentication())->toBeFalse();

    foreach ([Area::Mzrt, Area::Admin, Area::Instructor, Area::Student] as $area) {
        expect($area->requiresAuthentication())->toBeTrue();
    }
});
