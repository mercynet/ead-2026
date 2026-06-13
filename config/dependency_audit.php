<?php

return [
    'fail_on' => 'high',

    'baseline_path' => 'security/dependency-audit-baseline.json',

    'trusted_composer_plugins' => [
        'pestphp/pest-plugin' => 'Pest plugin manager usado em testes.',
        'php-http/discovery' => 'PSR discovery helper.',
        'dealerdirect/phpcodesniffer-composer-installer' => 'Composer installer de standards PHPCS.',
    ],

    'trusted_repository_hosts' => [
        'github.com',
        'api.github.com',
        'packagist.org',
        'repo.packagist.org',
        'codeload.github.com',
    ],

    'trusted_vendor_bins' => [
        'laravel/pint' => ['builds/pint'],
        'pestphp/pest' => ['bin/pest'],
        'phpstan/phpstan' => ['phpstan'],
        'larastan/larastan' => ['extension.neon'],
    ],

    'trusted_laravel_providers' => [],
];
