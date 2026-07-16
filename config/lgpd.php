<?php

use App\Modules\Core\Models\Invitation;
use App\Modules\Core\Models\User;

/**
 * Inventário canônico de PII (LGPD): model => campos pessoais auditados.
 *
 * Guardado pela invariante #9 (tests/Architecture/PiiAuditTest): todo model
 * listado usa o trait LogsActivity e loga estes campos. Ao introduzir PII
 * novo, registre aqui — ver docs/specs/00-architecture/security-privacy-lgpd.md.
 */
return [

    'pii' => [
        User::class => [
            'name',
            'email',
            'cpf',
            'headline',
            'bio',
            'avatar',
            'linkedin_url',
            'twitter_url',
        ],
        Invitation::class => [
            'email',
        ],
    ],

];
