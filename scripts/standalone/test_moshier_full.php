<?php
/**
 * Full Moshier Ephemeris Test
 *
 * Tests SEFLG_MOSEPH for all supported bodies:
 * - Sun, Moon, Mercury-Pluto
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\Julian;
use Swisseph\SwephFile\SwedState;

// Test date: 2025-01-01 12:00 UT
$year = 2025;
$month = 1;
$day = 1;
$hour = 12.0;

$jd = Julian::toJulianDay($year, $month, $day, $hour, Constants::SE_GREG_CAL);
echo "Test Date: $year-$month-$day $hour:00 UT\n";
echo "Julian Day: $jd\n";
echo str_repeat("=", 80) . "\n\n";

// Set ephemeris path (not needed for Moshier, but good practice)
SwedState::getInstance()->setEphePath(__DIR__ . '/../../eph/ephe');

// Test bodies
$bodies = [
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

// Flags: Moshier ephemeris with speed
$iflag = Constants::SEFLG_MOSEPH | Constants::SEFLG_SPEED;

echo sprintf("%-10s | %-12s | %-10s | %-10s | %-10s\n",
    "Body", "Longitude", "Latitude", "Distance", "Speed");
echo str_repeat("-", 80) . "\n";

$errors = [];
$success = 0;

foreach ($bodies as $ipl => $name) {
    $xx = [];
    $serr = null;

    $ret = swe_calc_ut($jd, $ipl, $iflag, $xx, $serr);

    if ($ret < 0) {
        echo sprintf("%-10s | ERROR: %s\n", $name, $serr);
        $errors[] = "$name: $serr";
        continue;
    }

    $lon = $xx[0];
    $lat = $xx[1];
    $dist = $xx[2];
    $speed = $xx[3];

    echo sprintf("%-10s | %12.6f | %10.6f | %10.6f | %10.6f\n",
        $name, $lon, $lat, $dist, $speed);

    // Basic sanity checks
    if ($lon < 0 || $lon >= 360) {
        $errors[] = "$name: Longitude out of range: $lon";
    } elseif ($lat < -90 || $lat > 90) {
        $errors[] = "$name: Latitude out of range: $lat";
    } elseif ($dist <= 0) {
        $errors[] = "$name: Distance <= 0: $dist";
    } else {
        $success++;
    }
}

echo str_repeat("=", 80) . "\n";
echo "Results: $success/" . count($bodies) . " bodies computed successfully\n";

if (count($errors) > 0) {
    echo "\nErrors:\n";
    foreach ($errors as $err) {
        echo "  - $err\n";
    }
}

echo "\n";

// Test equatorial coordinates
echo "Testing Equatorial coordinates (RA/Dec):\n";
echo str_repeat("-", 80) . "\n";

$iflag_eq = Constants::SEFLG_MOSEPH | Constants::SEFLG_SPEED | Constants::SEFLG_EQUATORIAL;

foreach ([Constants::SE_SUN, Constants::SE_MOON, Constants::SE_MARS] as $ipl) {
    $name = $bodies[$ipl];
    $xx = [];
    $serr = null;

    $ret = swe_calc_ut($jd, $ipl, $iflag_eq, $xx, $serr);

    if ($ret < 0) {
        echo sprintf("%-10s | ERROR: %s\n", $name, $serr);
        continue;
    }

    // RA in hours
    $ra_hours = $xx[0] / 15.0;
    $ra_h = (int)$ra_hours;
    $ra_m = (int)(($ra_hours - $ra_h) * 60);
    $ra_s = (($ra_hours - $ra_h) * 60 - $ra_m) * 60;

    // Dec in degrees
    $dec = $xx[1];
    $dec_sign = $dec >= 0 ? '+' : '-';
    $dec = abs($dec);
    $dec_d = (int)$dec;
    $dec_m = (int)(($dec - $dec_d) * 60);
    $dec_s = (($dec - $dec_d) * 60 - $dec_m) * 60;

    echo sprintf("%-10s | RA: %02dh%02dm%05.2fs | Dec: %s%02d°%02d'%05.2f\"\n",
        $name, $ra_h, $ra_m, $ra_s, $dec_sign, $dec_d, $dec_m, $dec_s);
}

echo "\nMoshier ephemeris integration test complete.\n";
