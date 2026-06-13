<?php

declare(strict_types=1);

test('environments config parses ECOUTE_ENVIRONMENTS env variable', function () {
    // When ECOUTE_ENVIRONMENTS is not set, default to local, staging
    putenv('ECOUTE_ENVIRONMENTS');
    $config = require __DIR__.'/../../config/ecoute.php';
    expect($config['environments'])->toBe(['local', 'staging']);

    // When ECOUTE_ENVIRONMENTS is custom list
    putenv('ECOUTE_ENVIRONMENTS=local,staging,production');
    $config = require __DIR__.'/../../config/ecoute.php';
    expect($config['environments'])->toBe(['local', 'staging', 'production']);

    // Handle spaces and empty values
    putenv('ECOUTE_ENVIRONMENTS= local , staging , , production ');
    $config = require __DIR__.'/../../config/ecoute.php';
    expect($config['environments'])->toBe(['local', 'staging', 'production']);

    // Clean up env
    putenv('ECOUTE_ENVIRONMENTS');
});
