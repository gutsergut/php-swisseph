<?php

require __DIR__ . '/bootstrap.php';

use Swisseph\Constants;

// Set ephemeris path
$ephePath = realpath(__DIR__ . '/../../eph/ephe');
swe_set_ephe_path($ephePath);
echo "Ephemeris path set to: $ephePath\n\n";

echo "=== Gauquelin Sector Test ===\n\n";

// Test data
$jd_ut = 2460677.0; // 2025-01-01 12:00 UT (CORRECTED: JD starts at noon, not midnight!)
$geopos = [13.4, 52.5, 0.0]; // Berlin: lon, lat, height
$atpress = 1013.25; // Default atmospheric pressure
$attemp = 15.0; // Temperature in Celsius

// Verify JD is correct
$date_check = swe_revjul($jd_ut, 1);
printf("Test date: JD %.1f = %04d-%02d-%02d %02d:%02d UT\n",
    $jd_ut, $date_check['y'], $date_check['m'], $date_check['d'],
    (int)$date_check['ut'], (int)(($date_check['ut'] - (int)$date_check['ut']) * 60));
echo "Location: Berlin (lon=13.4°, lat=52.5°)\n\n";

// Test 1: Sun with method 0 (geometric with latitude)
echo "Test 1: Sun - Method 0 (geometric with latitude)\n";
$dgsect = 0.0;
$serr = null;
$ret = swe_gauquelin_sector(
    $jd_ut,
    Constants::SE_SUN,
    null,
    0,
    0,
    $geopos,
    $atpress,
    $attemp,
    $dgsect,
    $serr
);

if ($ret === Constants::SE_OK) {
    printf("  Gauquelin sector: %.3f\n", $dgsect);
    $sector_int = (int)$dgsect;
    printf("  Sector number: %d\n", $sector_int);
} else {
    echo "  ERROR: $serr\n";
    exit(1);
}

// Test 2: Sun with method 1 (geometric without latitude)
echo "\nTest 2: Sun - Method 1 (geometric without latitude)\n";
$dgsect = 0.0;
$serr = null;
$ret = swe_gauquelin_sector(
    $jd_ut,
    Constants::SE_SUN,
    null,
    0,
    1,
    $geopos,
    $atpress,
    $attemp,
    $dgsect,
    $serr
);

if ($ret === Constants::SE_OK) {
    printf("  Gauquelin sector: %.3f\n", $dgsect);
} else {
    echo "  ERROR: $serr\n";
    exit(1);
}

// Test 3: Moon with method 2 (from rise/set, no refraction)
echo "\nTest 3: Moon - Method 2 (from rise/set, no refraction)\n";
$dgsect = 0.0;
$serr = null;
$ret = swe_gauquelin_sector(
    $jd_ut,
    Constants::SE_MOON,
    null,
    0,
    2,
    $geopos,
    $atpress,
    $attemp,
    $dgsect,
    $serr
);

if ($ret === Constants::SE_OK) {
    printf("  Gauquelin sector: %.3f\n", $dgsect);
} else {
    echo "  ERROR: ret=$ret, serr=$serr\n";
    // Continue with other tests
}

// Test 4: Fixed star Spica with method 0
echo "\nTest 4: Fixed star Spica - Method 0 (geometric with latitude)\n";
$star = 'Spica';
$dgsect = 0.0;
$serr = null;
$ret = swe_gauquelin_sector(
    $jd_ut,
    0, // ipl ignored for stars
    $star,
    0,
    0,
    $geopos,
    $atpress,
    $attemp,
    $dgsect,
    $serr
);

if ($ret === Constants::SE_OK) {
    printf("  Star: $star\n");
    printf("  Gauquelin sector: %.3f\n", $dgsect);
} else {
    echo "  ERROR: ret=$ret, serr=$serr\n";
}

// Test 5: Mars with method 3 (from rise/set, with refraction)
echo "\nTest 5: Mars - Method 3 (from rise/set, with refraction)\n";
$dgsect = 0.0;
$serr = null;
$ret = swe_gauquelin_sector(
    $jd_ut,
    Constants::SE_MARS,
    null,
    0,
    3,
    $geopos,
    $atpress,
    $attemp,
    $dgsect,
    $serr
);

if ($ret === Constants::SE_OK) {
    printf("  Gauquelin sector: %.3f\n", $dgsect);
} else {
    echo "  ERROR: ret=$ret, serr=$serr\n";
}

echo "\n=== Test completed ===\n";
