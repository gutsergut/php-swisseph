<?php
/**
 * Test JPL ephemeris integration with swe_calc() for all planets
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// Set ephemeris path
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

// Set JPL file (first try de441, then de200)
$jplPath = __DIR__ . '/../../eph/data/ephemerides/jpl';
$jplFile = file_exists("$jplPath/de441.eph") ? 'de441.eph' : 'de200.eph';
swe_set_ephe_path($jplPath);
swe_set_jpl_file($jplFile);
echo "Using JPL file: $jplFile\n\n";

$jd = Constants::J2000; // J2000.0 = 2451545.0

$planets = [
    Constants::SE_SUN => 'Sun',
    Constants::SE_MOON => 'Moon',
    Constants::SE_MERCURY => 'Mercury',
    Constants::SE_VENUS => 'Venus',
    Constants::SE_MARS => 'Mars',
    Constants::SE_JUPITER => 'Jupiter',
    Constants::SE_SATURN => 'Saturn',
    Constants::SE_URANUS => 'Uranus',
    Constants::SE_NEPTUNE => 'Neptune',
    Constants::SE_PLUTO => 'Pluto',
];

echo "=== Planet positions at J2000.0 (JD=2451545.0) ===\n";
echo str_repeat("=", 80) . "\n\n";

// Reset path for SWIEPH comparisons
$swePath = __DIR__ . '/../../eph/ephe';

foreach ($planets as $ipl => $name) {
    echo "--- $name ---\n";

    // JPL
    swe_set_ephe_path($jplPath);
    $xx_jpl = [];
    $serr = '';
    $ret = swe_calc($jd, $ipl, Constants::SEFLG_JPLEPH | Constants::SEFLG_SPEED, $xx_jpl, $serr);

    if ($ret < 0) {
        echo "JPL Error: $serr\n\n";
        continue;
    }

    // SWIEPH (Moshier/Swiss Ephemeris files)
    swe_set_ephe_path($swePath);
    $xx_swe = [];
    $ret = swe_calc($jd, $ipl, Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED, $xx_swe, $serr);

    if ($ret < 0) {
        echo "SWIEPH Error: $serr\n\n";
        continue;
    }

    $dlon = abs($xx_jpl[0] - $xx_swe[0]) * 3600;  // arcsec
    $dlat = abs($xx_jpl[1] - $xx_swe[1]) * 3600;  // arcsec
    $ddist = abs($xx_jpl[2] - $xx_swe[2]) * 149597870.7;  // km

    printf("JPL:    lon=%11.6f, lat=%10.6f, dist=%10.6f AU\n", $xx_jpl[0], $xx_jpl[1], $xx_jpl[2]);
    printf("SWIEPH: lon=%11.6f, lat=%10.6f, dist=%10.6f AU\n", $xx_swe[0], $xx_swe[1], $xx_swe[2]);
    printf("Diff:   dlon=%7.3f\", dlat=%7.3f\", ddist=%.0f km\n\n", $dlon, $dlat, $ddist);
}

echo "Note: Differences are expected between JPL and SWIEPH/Moshier ephemerides.\n";
echo "Typical accuracy: lon/lat within ~10\", distance within ~1000km.\n";
