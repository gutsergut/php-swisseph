<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Math;

// Test parameters for Berlin 2025-01-01 12:00 UT
$armc_deg = 294.802392;
$geolat_deg = 52.52;
$eps_deg = 23.43772; // approximate

$armc_rad = deg2rad($armc_deg);
$geolat_rad = deg2rad($geolat_deg);
$eps_rad = deg2rad($eps_deg);

echo "Test apc_sector calculation for house 5\n";
echo "ARMC: $armc_deg°, geolat: $geolat_deg°, eps: $eps_deg°\n\n";

// Calculate kv and dasc
$kv = atan(tan($geolat_rad) * tan($eps_rad) * cos($armc_rad) /
           (1 + tan($geolat_rad) * tan($eps_rad) * sin($armc_rad)));
$dasc = atan(sin($kv) / tan($geolat_rad));

printf("kv = %.6f rad = %.6f°\n", $kv, rad2deg($kv));
printf("dasc = %.6f rad = %.6f°\n", $dasc, rad2deg($dasc));

// For house 5: n=5, is_below_hor=1, k=4
$n = 5;
$k = 4;
$is_below_hor = true;

$a = $kv + $armc_rad + M_PI/2 + $k * (M_PI/2 - $kv) / 3;
$a = Math::normAngleRad($a);

printf("\nFor house %d: k=%d, is_below_hor=%s\n", $n, $k, $is_below_hor ? 'true' : 'false');
printf("a = %.6f rad = %.6f°\n", $a, rad2deg($a));

// Final calculation
$num = tan($dasc) * tan($geolat_rad) * sin($armc_rad) + sin($a);
$den = cos($eps_rad) * (tan($dasc) * tan($geolat_rad) * cos($armc_rad) + cos($a)) +
       sin($eps_rad) * tan($geolat_rad) * sin($armc_rad - $a);
$lon_rad = atan2($num, $den);
$lon_deg = Math::normAngleDeg(rad2deg($lon_rad));

printf("\nFinal longitude: %.6f° (expected: 135.857779°)\n", $lon_deg);
printf("Difference: %.6f°\n", abs($lon_deg - 135.857779));
