<?php

require __DIR__ . '/../tests/bootstrap.php';

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;

$jd_ut = 2460677.0;
$jd_tt = $jd_ut + 74.0 / 86400.0; // rough delta T

$serr = '';

// Warm up - this fills the planet data cache
$xx = [];
swe_calc_ut($jd_ut, Constants::SE_MERCURY, Constants::SEFLG_SPEED, $xx, $serr);

// Get cached planet data
$swed = SwedState::getInstance();

echo "Mercury speed calculation debug:\n\n";

// Get Mercury barycentric data
$mercury_pd = $swed->pldat[\Swisseph\SwephFile\SwephConstants::SEI_MERCURY] ?? null;
$earth_pd = $swed->pldat[\Swisseph\SwephFile\SwephConstants::SEI_EARTH] ?? null;

if (!$mercury_pd || !$earth_pd) {
    die("Planet data not available\n");
}

$xx_orig = $mercury_pd->x;
$xobs = $earth_pd->x;

echo "Mercury barycentric:\n";
echo "  pos: [{$xx_orig[0]}, {$xx_orig[1]}, {$xx_orig[2]}]\n";
echo "  vel: [{$xx_orig[3]}, {$xx_orig[4]}, {$xx_orig[5]}]\n";
echo "\nEarth barycentric:\n";
echo "  pos: [{$xobs[0]}, {$xobs[1]}, {$xobs[2]}]\n";
echo "  vel: [{$xobs[3]}, {$xobs[4]}, {$xobs[5]}]\n";

$c_au_per_day = 173.144632674240;
$xx0 = $xx_orig;

// xxsv = position at t-1 (pos - speed)
$xxsv = [$xx0[0] - $xx0[3], $xx0[1] - $xx0[4], $xx0[2] - $xx0[5]];
$xxsp = $xxsv;

echo "\nxxsv (position at t-1):\n";
echo "  [{$xxsv[0]}, {$xxsv[1]}, {$xxsv[2]}]\n";

// First iteration for t-1 position
$niter = 1; // for SWIEPH

for ($j = 0; $j <= $niter; $j++) {
    $dx = [$xxsp[0], $xxsp[1], $xxsp[2]];
    // subtract observer velocity effect (geocentric case)
    $dx[0] -= ($xobs[0] - $xobs[3]);
    $dx[1] -= ($xobs[1] - $xobs[4]);
    $dx[2] -= ($xobs[2] - $xobs[5]);

    // new dt for t-1
    $r = sqrt($dx[0]*$dx[0] + $dx[1]*$dx[1] + $dx[2]*$dx[2]);
    $dt_sp = $r / $c_au_per_day;

    echo "Iteration $j for t-1: r=$r, dt_sp=$dt_sp\n";

    // rough apparent position at t-1
    $xxsp[0] = $xxsv[0] - $dt_sp * $xx0[3];
    $xxsp[1] = $xxsv[1] - $dt_sp * $xx0[4];
    $xxsp[2] = $xxsv[2] - $dt_sp * $xx0[5];
}

// true position - apparent position at time t-1
$xxsp_before = $xxsp;
$xxsp[0] = $xxsv[0] - $xxsp[0];
$xxsp[1] = $xxsv[1] - $xxsp[1];
$xxsp[2] = $xxsv[2] - $xxsp[2];

echo "\nxxsp (true-apparent at t-1):\n";
echo "  [{$xxsp[0]}, {$xxsp[1]}, {$xxsp[2]}]\n";

// Now compute apparent position at t
$xx = $xx0;
$dx0 = $xx[0] - $xobs[0];
$dx1 = $xx[1] - $xobs[1];
$dx2 = $xx[2] - $xobs[2];
$r = sqrt($dx0*$dx0 + $dx1*$dx1 + $dx2*$dx2);
$dt_light = $r / $c_au_per_day;

echo "\nLight-time at t: r=$r, dt=$dt_light days\n";

// iteration 1
for ($i = 0; $i < 3; $i++) {
    $xx[$i] = $xx0[$i] - $xx0[$i + 3] * $dt_light;
}

// iteration 2
$dx0 = $xx[0] - $xobs[0];
$dx1 = $xx[1] - $xobs[1];
$dx2 = $xx[2] - $xobs[2];
$r2 = sqrt($dx0*$dx0 + $dx1*$dx1 + $dx2*$dx2);
$dt2 = $r2 / $c_au_per_day;
for ($i = 0; $i < 3; $i++) {
    $xx[$i] = $xx0[$i] - $xx0[$i + 3] * $dt2;
}

echo "Light-time at t (after 2nd iter): r=$r2, dt=$dt2 days\n";

// part of daily motion from change of dt
$xxsp_final = [
    $xx0[0] - $xx[0] - $xxsp[0],
    $xx0[1] - $xx[1] - $xxsp[1],
    $xx0[2] - $xx[2] - $xxsp[2]
];

echo "\nxxsp final (correction for speed):\n";
echo "  [{$xxsp_final[0]}, {$xxsp_final[1]}, {$xxsp_final[2]}]\n";

// Compute the effect on speed in arcsec/day
$r_geo = sqrt(
    ($xx[0] - $xobs[0]) * ($xx[0] - $xobs[0]) +
    ($xx[1] - $xobs[1]) * ($xx[1] - $xobs[1]) +
    ($xx[2] - $xobs[2]) * ($xx[2] - $xobs[2])
);

$deg_per_au = 57.29577951308232; // 180/pi
$arcsec_per_deg = 3600.0;
$effect_arcsec = sqrt($xxsp_final[0]*$xxsp_final[0] + $xxsp_final[1]*$xxsp_final[1] + $xxsp_final[2]*$xxsp_final[2]) / $r_geo * $deg_per_au * $arcsec_per_deg;

echo "\nSpeed correction effect: ~$effect_arcsec arcsec/day\n";

// Compute C reference values
echo "\n\n=== Compare with C swetest ===\n";
exec('"C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\с-swisseph\swisseph\windows\programs\swetest64.exe" -p2 -b15.1.2025 -n1 -s1 -fPJl 2>&1', $output);
foreach ($output as $line) {
    echo "$line\n";
}
