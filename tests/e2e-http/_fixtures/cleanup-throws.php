<?php

/**
 * Fixture de teste do E2eRunCommand: cleanup do spec estoura para provar que o
 * runner sinaliza resíduo via exit code (não pode declarar sucesso deixando lixo).
 */
return [
    'endpoint' => 'GET /api/v1/core/auth/me',
    'cases' => [],
    'cleanup' => function (array $ctx): void {
        throw new RuntimeException('cleanup boom');
    },
];
