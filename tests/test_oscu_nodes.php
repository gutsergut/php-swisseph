<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo "=== Test Osculating Nodes at J2000 ===\n\n";

$jd = 2451545.0; // J2000.0

// Test Saturn osculating nodes
$xnasc = [];
$xndsc = [];
$xperi = [];
$xaphe = [];
$serr = '';

$ret = swe_nod_aps($jd, Constants::SE_SATURN, Constants::SEFLG_SWIEPH, Constants::SE_NODBIT_OSCU, $xnasc, $xndsc, $xperi, $xaphe, $serr);

if ($ret < 0) {
    echo "ERROR: $serr\n";
} else {
    printf("Saturn Osculating Nodes:\n");
    printf("  Ascending Node:  %.6f° (PHP)\n", $xnasc[0]);
    printf("  C Reference:     113.642581° (swetest64)\n");
    printf("  Difference:      %.4f° = %.1f arcsec\n\n", abs($xnasc[0] - 113.642581), abs($xnasc[0] - 113.642581) * 3600);

    printf("  Descending Node: %.6f° (PHP)\n", $xndsc[0]);
    printf("  Expected:        %.6f° (180° from asc)\n\n", $xnasc[0] + 180);

    printf("  Perihelion:      %.6f° (PHP)\n", $xperi[0]);
    printf("  Distance:        %.6f AU\n\n", $xperi[2]);

    printf("  Aphelion:        %.6f° (PHP)\n", $xaphe[0]);
    printf("  Distance:        %.6f AU\n", $xaphe[2]);
}

// Also test Jupiter for comparison
echo "\n" . str_repeat("=", 60) . "\n\n";

$ret = swe_nod_aps($jd, Constants::SE_JUPITER, Constants::SEFLG_SWIEPH, Constants::SE_NODBIT_OSCU, $xnasc, $xndsc, $xperi, $xaphe, $serr);

if ($ret < 0) {
    echo "ERROR: $serr\n";
} else {
    printf("Jupiter Osculating Nodes:\n");
    printf("  Ascending Node:  %.6f° (PHP)\n", $xnasc[0]);
    printf("  C Reference:     100.494046° (swetest64)\n");
    printf("  Difference:      %.4f° = %.1f arcsec\n\n", abs($xnasc[0] - 100.494046), abs($xnasc[0] - 100.494046) * 3600);

    printf("  Perihelion:      %.6f° (PHP)\n", $xperi[0]);
    printf("  Distance:        %.6f AU\n", $xperi[2]);
}
