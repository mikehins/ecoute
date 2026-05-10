<?php

declare(strict_types=1);

$packageRoot = dirname(__DIR__);
$arguments = array_slice($_SERVER['argv'], 1);

$candidates = [
    $packageRoot.'/vendor/bin/pint',
    dirname($packageRoot, 2).'/vendor/bin/pint',
    dirname($packageRoot, 3).'/vendor/bin/pint',
];

foreach ($candidates as $binary) {
    if (! is_file($binary)) {
        continue;
    }

    $command = [escapeshellarg(PHP_BINARY), escapeshellarg($binary), 'src/', 'tests/', '--format', 'agent'];

    foreach ($arguments as $argument) {
        $command[] = escapeshellarg($argument);
    }

    passthru(implode(' ', $command), $exitCode);
    exit($exitCode);
}

fwrite(STDERR, "Unable to locate the Pint binary for Ecoute.\n");
exit(1);
