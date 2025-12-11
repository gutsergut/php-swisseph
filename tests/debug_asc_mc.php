<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$jd = swe_julday(2025, 1, 1, 12.0, \Swisseph\Constants::SE_GREG_CAL);
echo "JD: " . number_format($jd, 6) . "\n";
echo "Expected: JD 2460677.000000\n\n";

$cusp = [];
$ascmc = [];

swe_houses($jd, 52.52, 13.41, 'P', $cusp, $ascmc);
printf("Placidus: Asc=%.6f° MC=%.6f° ARMC=%.6f°\n", $ascmc[0], $ascmc[1], $ascmc[2]);
echo "Expected: Asc=53.174602° MC=292.976294° ARMC=294.802392°\n";
echo "\n";

// Manual calculation
$gmst_h = \swe_sidtime($jd);
printf("GMST: %.6f hours (= %.6f degrees)\n", $gmst_h, $gmst_h * 15.0);

$armc_manual = \Swisseph\Math::normAngleDeg($gmst_h * 15.0 + 13.41);
printf("ARMC (manual calc): %.6f°\n", $armc_manual);
printf("ARMC from swe_houses: %.6f°\n", $ascmc[2]);
