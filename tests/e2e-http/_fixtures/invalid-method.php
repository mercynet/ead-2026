<?php

return [
    'endpoint' => 'GET /initial',
    'cases' => [
        [
            'name' => 'invalid method is rejected',
            'method' => 'TRACE',
        ],
    ],
];
