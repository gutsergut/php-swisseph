<?php
/**
 * Test for numbered asteroid (Eros = 433) calculation
 *
 * Eros is asteroid #433, accessed via SE_AST_OFFSET + 433 = 10433
 *
 * Reference values from swetest64.exe at J2000.0 (JD 2451545.0):
 *   Geocentric:   lon=236.2756596274° lat=-7.7336246428° dist=1.8542876408 AU
 *   Heliocentric: lon=205.1128342278° lat=-10.6894652836° dist=1.3453936364 AU
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// Set ephemeris path to include asteroid files
$ephePath = realpath(__DIR__ . '/../../eph/ephe');
if ($ephePath === false) {
    echo "ERROR: Ephemeris path not found\n";
    exit(1);
}

echo "Ephemeris path: $ephePath\n";
swe_set_ephe_path($ephePath);

// Check if Eros file exists
$erosFile = $ephePath . '/ast0/se00433s.se1';
if (!file_exists($erosFile)) {
    echo "ERROR: Eros ephemeris file not found: $erosFile\n";
    echo "Please download the file from Swiss Ephemeris repository\n";
    exit(1);
}
echo "Eros file found: $erosFile\n\n";

// Reference values from C swetest64.exe
$REF_GEO_LON = 236.2756596274;
$REF_GEO_LAT = -7.7336246428;
$REF_GEO_DIST = 1.8542876408;
$REF_HELIO_LON = 205.1128342278;
$REF_HELIO_LAT = -10.6894652836;
$REF_HELIO_DIST = 1.3453936364;

// Tolerance: 0.001° for angles, 0.0001 AU for distance
$TOL_ANGLE = 0.001;
$TOL_DIST = 0.0001;

// Test date: J2000.0 (JD 2451545.0)
$jd_tt = 2451545.0;

// Eros planet number: SE_AST_OFFSET + 433
$ipl = Constants::SE_AST_OFFSET + 433;

echo "Testing Eros (asteroid #433)\n";
echo "Planet number: $ipl (SE_AST_OFFSET + 433)\n";
echo "Julian Day: $jd_tt (J2000.0)\n\n";

// Calculate geocentric ecliptic coordinates with speed
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;
$xx = [];
$serr = null;

$ret = swe_calc($jd_tt, $ipl, $iflag, $xx, $serr);

if ($ret < 0) {
    echo "ERROR: Calculation failed: $serr\n";
    exit(1);
}

echo "Geocentric Ecliptic Coordinates:\n";
echo sprintf("  Longitude: %.6f°\n", $xx[0]);
echo sprintf("  Latitude:  %.6f°\n", $xx[1]);
echo sprintf("  Distance:  %.6f AU\n", $xx[2]);
echo sprintf("  Speed Lon: %.6f°/day\n", $xx[3]);
echo sprintf("  Speed Lat: %.6f°/day\n", $xx[4]);
echo sprintf("  Speed Dist: %.6f AU/day\n", $xx[5]);
echo "\n";

// Get equatorial coordinates
$iflag_eq = Constants::SEFLG_SWIEPH | Constants::SEFLG_EQUATORIAL;
$xx_eq = [];
$serr = null;

$ret = swe_calc($jd_tt, $ipl, $iflag_eq, $xx_eq, $serr);

if ($ret < 0) {
    echo "ERROR: Equatorial calculation failed: $serr\n";
    exit(1);
}

echo "Geocentric Equatorial Coordinates:\n";
echo sprintf("  Right Ascension: %.6f°\n", $xx_eq[0]);
echo sprintf("  Declination:     %.6f°\n", $xx_eq[1]);
echo sprintf("  Distance:        %.6f AU\n", $xx_eq[2]);
echo "\n";

// Test heliocentric coordinates
$iflag_hel = Constants::SEFLG_SWIEPH | Constants::SEFLG_HELCTR;
$xx_hel = [];
$serr = null;

$ret = swe_calc($jd_tt, $ipl, $iflag_hel, $xx_hel, $serr);

if ($ret < 0) {
    echo "ERROR: Heliocentric calculation failed: $serr\n";
    exit(1);
}

echo "Heliocentric Ecliptic Coordinates:\n";
echo sprintf("  Longitude: %.6f°\n", $xx_hel[0]);
echo sprintf("  Latitude:  %.6f°\n", $xx_hel[1]);
echo sprintf("  Distance:  %.6f AU\n", $xx_hel[2]);
echo "\n";

// Verify geocentric coordinates
$passed = true;
$errors = [];

if (abs($xx[0] - $REF_GEO_LON) > $TOL_ANGLE) {
    $passed = false;
    $errors[] = sprintf("Geocentric Longitude: expected %.6f, got %.6f (diff: %.6f)",
        $REF_GEO_LON, $xx[0], abs($xx[0] - $REF_GEO_LON));
}
if (abs($xx[1] - $REF_GEO_LAT) > $TOL_ANGLE) {
    $passed = false;
    $errors[] = sprintf("Geocentric Latitude: expected %.6f, got %.6f (diff: %.6f)",
        $REF_GEO_LAT, $xx[1], abs($xx[1] - $REF_GEO_LAT));
}
if (abs($xx[2] - $REF_GEO_DIST) > $TOL_DIST) {
    $passed = false;
    $errors[] = sprintf("Geocentric Distance: expected %.6f, got %.6f (diff: %.6f)",
        $REF_GEO_DIST, $xx[2], abs($xx[2] - $REF_GEO_DIST));
}

// Verify heliocentric coordinates
if (abs($xx_hel[0] - $REF_HELIO_LON) > $TOL_ANGLE) {
    $passed = false;
    $errors[] = sprintf("Heliocentric Longitude: expected %.6f, got %.6f (diff: %.6f)",
        $REF_HELIO_LON, $xx_hel[0], abs($xx_hel[0] - $REF_HELIO_LON));
}
if (abs($xx_hel[1] - $REF_HELIO_LAT) > $TOL_ANGLE) {
    $passed = false;
    $errors[] = sprintf("Heliocentric Latitude: expected %.6f, got %.6f (diff: %.6f)",
        $REF_HELIO_LAT, $xx_hel[1], abs($xx_hel[1] - $REF_HELIO_LAT));
}
if (abs($xx_hel[2] - $REF_HELIO_DIST) > $TOL_DIST) {
    $passed = false;
    $errors[] = sprintf("Heliocentric Distance: expected %.6f, got %.6f (diff: %.6f)",
        $REF_HELIO_DIST, $xx_hel[2], abs($xx_hel[2] - $REF_HELIO_DIST));
}

// Get planet name
$name = swe_get_planet_name($ipl);
echo "Planet name: $name\n\n";

if ($passed) {
    echo "=== Eros Asteroid Test PASSED ===\n";
    exit(0);
} else {
    echo "=== Eros Asteroid Test FAILED ===\n";
    foreach ($errors as $err) {
        echo "  ERROR: $err\n";
    }
    exit(1);
}
