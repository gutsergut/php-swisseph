<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use function swe_lun_occult_where;
use function swe_set_ephe_path;

echo "=== Test swe_lun_occult_where() ===\n\n";

// Set ephemeris path
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

// Test 1: Saturn occultation on 2024-04-06
echo "Test 1: Saturn occultation on 2024-04-06 10:18:38 UT\n";
echo "Reference JD: 2460406.929617\n\n";

$tjd_ut = 2460406.929617;
$ipl = Constants::SE_SATURN;
$starname = null;
$ifl = Constants::SEFLG_SWIEPH;
$geopos = array_fill(0, 10, 0.0);
$attr = array_fill(0, 20, 0.0);
$serr = null;

$retflag = swe_lun_occult_where($tjd_ut, $ipl, $starname, $ifl, $geopos, $attr, $serr);

if ($retflag === Constants::SE_ERR) {
    echo "ERROR: $serr\n";
    exit(1);
}

if ($retflag === 0) {
    echo "No occultation at this time\n";
    exit(1);
}

echo "Return flags: $retflag\n";
echo "Geographic position of maximum:\n";
echo sprintf("  Longitude: %.4f°\n", $geopos[0]);
echo sprintf("  Latitude:  %.4f°\n", $geopos[1]);
echo sprintf("  Altitude:  %.1f m\n", $geopos[2] ?? 0);
echo "\n";

echo "Eclipse attributes:\n";
echo sprintf("  Fraction covered:     %.6f\n", $attr[0]);
echo sprintf("  Diameter ratio:       %.6f\n", $attr[1]);
echo sprintf("  Obscuration:          %.6f\n", $attr[2]);
echo sprintf("  Core shadow diam:     %.3f km\n", $attr[3]);
echo sprintf("  Azimuth:              %.4f°\n", $attr[4] ?? 0);
echo sprintf("  True altitude:        %.4f°\n", $attr[5] ?? 0);
echo sprintf("  Apparent altitude:    %.4f°\n", $attr[6] ?? 0);
echo sprintf("  Angular separation:   %.6f°\n", $attr[7] ?? 0);
echo "\n";

// Determine eclipse type
$types = [];
if ($retflag & Constants::SE_ECL_CENTRAL) $types[] = "CENTRAL";
if ($retflag & Constants::SE_ECL_NONCENTRAL) $types[] = "NONCENTRAL";
if ($retflag & Constants::SE_ECL_TOTAL) $types[] = "TOTAL";
if ($retflag & Constants::SE_ECL_ANNULAR) $types[] = "ANNULAR";
if ($retflag & Constants::SE_ECL_PARTIAL) $types[] = "PARTIAL";

echo "Eclipse type: " . implode(" | ", $types) . "\n";

// Test 2: Venus occultation (find one from recent years)
echo "\n=== Test 2: Check for no occultation ===\n";
$tjd_ut2 = 2460000.0; // Random date without occultation
$geopos2 = array_fill(0, 10, 0.0);
$attr2 = array_fill(0, 20, 0.0);
$serr2 = null;

$retflag2 = swe_lun_occult_where($tjd_ut2, Constants::SE_VENUS, null, $ifl, $geopos2, $attr2, $serr2);

if ($retflag2 === 0) {
    echo "✓ Correctly detected no occultation\n";
} else {
    echo "Found occultation (unexpected): flags=$retflag2\n";
    echo sprintf("  Location: %.4f°, %.4f°\n", $geopos2[0], $geopos2[1]);
}

echo "\n=== All tests complete ===\n";
