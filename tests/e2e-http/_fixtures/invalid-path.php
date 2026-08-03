<?php

return [
    'endpoint' => 'GET /fallback',
    'cases' => [
        [
            'name' => 'invalid dynamic path is rejected',
            'path' => fn (array $ctx): null => null,
        ],
    ],
];
