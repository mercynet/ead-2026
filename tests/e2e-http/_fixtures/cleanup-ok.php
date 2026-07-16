<?php

/**
 * Fixture de teste do E2eRunCommand: controle — cleanup bem-sucedido, sem casos
 * falhos, deve resultar em exit code 0.
 */
return [
    'endpoint' => 'GET /api/v1/core/auth/me',
    'cases' => [],
    'cleanup' => function (array $ctx): void {
        // no-op: sem resíduo.
    },
];
