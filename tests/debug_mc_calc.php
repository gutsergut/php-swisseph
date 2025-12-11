<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

$armc_deg = 294.802392;
$eps_deg = 23.4377; // approximate

$eps_rad = deg2rad($eps_deg);
$armc_rad = deg2rad($armc_deg);

// Manual calculation
$tan_mc = cos($eps_rad) * tan($armc_rad);
$mc_rad = atan($tan_mc);
$mc_deg = rad2deg($mc_rad);

printf("ARMC: %.6f°\n", $armc_deg);
printf("eps: %.6f°\n", $eps_deg);
printf("tan_mc: %.6f\n", $tan_mc);
printf("mc_rad (before adjustment): %.6f (%.6f°)\n", $mc_rad, $mc_deg);

// Quadrant adjustment
if ($mc_deg < 0.0) {
    $mc_deg += 180.0;
    echo "Added 180° (mc_rad < 0)\n";
}
if (cos($armc_rad) < 0) {
    $mc_deg += 180.0;
    echo "Added 180° (cos(armc) < 0)\n";
}

printf("Final MC: %.6f°\n", $mc_deg);
printf("Expected: 292.976294°\n");
