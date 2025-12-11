<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

use Swisseph\Domain\Houses\Systems\Apc;
use Swisseph\Math;
use Swisseph\Houses;
use Swisseph\Obliquity;
use Swisseph\DeltaT;
use Swisseph\Sidereal;

// Berlin 2025-01-01 12:00 UT
$jd_ut = swe_julday(2025, 1, 1, 12.0, \Swisseph\Constants::SE_GREG_CAL);
$geolon_deg = 13.41;
$geolat_deg = 52.52;

// Calculate ARMC and obliquity
$eps_rad = Obliquity::meanObliquityRadFromJdTT($jd_ut + DeltaT::deltaTSecondsFromJd($jd_ut) / 86400.0);
$armc_rad = Houses::armcFromSidereal($jd_ut, $geolon_deg);
[$asc_rad, $mc_rad] = Houses::ascMcFromArmc($armc_rad, deg2rad($geolat_deg), $eps_rad);

echo "Parameters:\n";
printf("  ARMC: %.6f° (%.6f rad)\n", rad2deg($armc_rad), $armc_rad);
printf("  geolat: %.6f° (%.6f rad)\n", $geolat_deg, deg2rad($geolat_deg));
printf("  eps: %.6f° (%.6f rad)\n", rad2deg($eps_rad), $eps_rad);
echo "\n";

// Create APC instance
$apc = new Apc();

// Get all cusps
$cusps = $apc->cusps($armc_rad, deg2rad($geolat_deg), $eps_rad, $asc_rad, $mc_rad);

echo "APC cusps:\n";
$ref = [
    1 => 53.174602,
    2 => 79.910605,
    3 => 97.174127,
    4 => 112.976294,
    5 => 135.857779,
    6 => 182.568172,
    7 => 233.174602,
    8 => 266.963800,
    9 => 281.277876,
    10 => 292.976294,
    11 => 308.236161,
    12 => 341.460366,
];

for ($i = 1; $i <= 12; $i++) {
    $cusp_deg = rad2deg($cusps[$i]);
    $diff = abs($cusp_deg - $ref[$i]);
    $status = ($diff < 0.01) ? '✓' : '✗';
    printf("  House %2d: %.6f° (ref: %.6f°, diff: %.6f°) %s\n",
        $i, $cusp_deg, $ref[$i], $diff, $status);
}
