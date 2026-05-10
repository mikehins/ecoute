<?php

declare(strict_types=1);

$packageRoot = dirname(__DIR__);
$arguments = array_slice($_SERVER['argv'], 1);

$candidates = [
    $packageRoot.'/vendor/bin/pest',
    dirname($packageRoot, 2).'/vendor/bin/pest',
    dirname($packageRoot, 3).'/vendor/bin/pest',
];

foreach ($candidates as $binary) {
    if (! is_file($binary)) {
        continue;
    }

    $command = [escapeshellarg(PHP_BINARY), escapeshellarg($binary), '--configuration', escapeshellarg($packageRoot.'/phpunit.xml')];

    foreach ($arguments as $argument) {
        $command[] = escapeshellarg($argument);
    }

    passthru(implode(' ', $command), $exitCode);
    exit($exitCode);
}

fwrite(STDERR, "Unable to locate the Pest binary for Ecoute.\n");
exit(1);
