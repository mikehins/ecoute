<?php

declare(strict_types=1);

$packageRoot = dirname(__DIR__);
$autoloadCandidates = [
    $packageRoot.'/vendor/autoload.php',
    dirname($packageRoot, 2).'/vendor/autoload.php',
    dirname($packageRoot, 3).'/vendor/autoload.php',
];

foreach ($autoloadCandidates as $autoload) {
    if (is_file($autoload)) {
        require $autoload;

        return;
    }
}

fwrite(STDERR, "Unable to locate Composer autoload.php for Ecoute tests.\n");
exit(1);
