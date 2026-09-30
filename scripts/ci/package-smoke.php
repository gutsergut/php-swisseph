<?php

declare(strict_types=1);

// Run against an extracted release archive after composer install --no-dev.
$packageRoot = $argv[1] ?? dirname(__DIR__, 2);
$autoload = $packageRoot . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Install production dependencies in the extracted package first.\n");
    exit(1);
}

require $autoload;

use Swisseph\Constants;
use Swisseph\ErrorCodes;
use Swisseph\OO\Swisseph;

if (ErrorCodes::name(ErrorCodes::NOT_FOUND) !== 'NOT_FOUND') {
    throw new RuntimeException('Error code autoload smoke check failed.');
}
require $packageRoot . '/src/Error.php';
require $packageRoot . '/src/Error.php';

$swe = new Swisseph();
$jd = $swe->julianDay(2000, 1, 1, 12.0);
if (abs($jd - 2451545.0) > 1e-10) {
    throw new RuntimeException('Julian day smoke check failed.');
}

$houses = $swe->houses($jd, 52.52, 13.405, 'P');
if (!$houses->isSuccess() || !is_finite($houses->ascendant)) {
    throw new RuntimeException('House calculation smoke check failed.');
}

if (swe_degnorm(-90.0) !== 270.0 || Constants::SE_SUN !== 0) {
    throw new RuntimeException('Procedural API smoke check failed.');
}

echo "Package smoke OK: Composer autoload, OO API and procedural API.\n";
