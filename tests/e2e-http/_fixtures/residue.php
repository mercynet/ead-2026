<?php

use App\Modules\Core\Models\User;

return [
    'endpoint' => 'GET /api/v1/core/auth/me',
    'cases' => [],
    'setup' => function (array $ctx): array {
        User::factory()->developer()->create(['email' => 'residue-fixture@test.local']);

        return [];
    },
];
