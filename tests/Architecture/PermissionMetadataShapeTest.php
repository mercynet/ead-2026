<?php

use App\Modules\Core\Enums\UserType;

it('defines complete eligible user type metadata for every canonical permission', function (): void {
    $userTypes = array_map(fn (UserType $userType): string => $userType->value, UserType::cases());

    foreach (config('permissions') as $permissionName => $metadata) {
        expect($metadata)
            ->toHaveKey('label')
            ->toHaveKey('user_types')
            ->and($metadata['label'])->toBeString()->not->toBeEmpty()
            ->and($metadata['user_types'])->toBeArray()->not->toBeEmpty()
            ->and($metadata['user_types'])->each->toBeIn($userTypes)
            ->and($metadata['user_types'])->toContain(UserType::Developer->value);
    }
});

it('uses only User effective permission surfaces outside the User model', function (): void {
    $userModel = realpath(app_path('Modules/Core/Models/User.php'));
    $forbiddenMethods = [
        'hasDirectPermission',
        'hasAnyDirectPermission',
        'hasAllDirectPermissions',
        'getDirectPermissions',
        'getPermissionsViaRoles',
        'getPermissionNames',
        'permissions',
    ];
    $pattern = '/->\s*('.implode('|', $forbiddenMethods).')\s*\(/';
    $violations = [];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));

    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php' || $file->getRealPath() === $userModel) {
            continue;
        }

        $contents = file_get_contents($file->getRealPath());

        if ($contents !== false && preg_match($pattern, $contents, $matches) === 1) {
            $violations[] = $file->getRealPath().': '.$matches[1];
        }
    }

    expect($violations)->toBeEmpty(
        'Raw Spatie permission inspection bypasses User effective permission ceiling: '.implode(', ', $violations),
    );
});
