<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
// CRITICAL: Set ephemeris path BEFORE any calculations
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');
echo "=== Lunar Eclipse When Location Test ===\n";
echo "Testing swe_lun_eclipse_when_loc() - location-specific visibility\n\n";

// Test locations
$locations = [
    'Moscow' => [37.6173, 55.7558, 0],          // Moscow, Russia (visible)
    'New York' => [-74.0060, 40.7128, 0],       // New York, USA (not visible - below horizon)
    'Tokyo' => [139.6917, 35.6895, 0],          // Tokyo, Japan (partial visibility)
    'Sydney' => [151.2093, -33.8688, 0],        // Sydney, Australia
];

// Search for 2024-09-18 partial lunar eclipse
$tjd_start = swe_julday(2024, 1, 1, 0.0, Constants::SE_GREG_CAL);

foreach ($locations as $city => $geopos) {
    echo "=== Testing from $city ===\n";
    printf("Location: %.4f°%s, %.4f°%s, %.0f m\n",
        abs($geopos[0]), $geopos[0] >= 0 ? 'E' : 'W',
        abs($geopos[1]), $geopos[1] >= 0 ? 'N' : 'S',
        $geopos[2]
    );

    $tret = [];
    $attr = [];
    $serr = '';

    $retflag = swe_lun_eclipse_when_loc(
        $tjd_start,
        Constants::SEFLG_SWIEPH,
        $geopos,
        $tret,
        $attr,
        0,  // forward
        $serr
    );

    if ($retflag === Constants::SE_ERR) {
        echo "ERROR: $serr\n\n";
        // Debug: print error details
        if (empty($serr)) {
            echo "(Empty error message - investigating...)\n";
            var_dump($tret);
            var_dump($attr);
        }
        continue;
    }

    // Convert maximum time to calendar date
    $date = swe_revjul($tret[0], Constants::SE_GREG_CAL);
    $year = $date['y'];
    $month = $date['m'];
    $day = $date['d'];
    $ut = $date['ut'];
    $hour = floor($ut);
    $min = floor(($ut - $hour) * 60);
    $sec = (($ut - $hour) * 60 - $min) * 60;

    echo "Eclipse Date: $year-" . sprintf("%02d-%02d", $month, $day) . "\n";
    printf("Maximum Time: %02d:%02d:%02.0f UT (JD %.6f)\n", $hour, $min, $sec, $tret[0]);

    // Eclipse type
    echo "Eclipse Type: ";
    if ($retflag & Constants::SE_ECL_TOTAL) {
        echo "TOTAL ";
    }
    if ($retflag & Constants::SE_ECL_PARTIAL) {
        echo "PARTIAL ";
    }
    if ($retflag & Constants::SE_ECL_PENUMBRAL) {
        echo "PENUMBRAL ";
    }
    echo "\n";

    // Visibility flags
    echo "Visibility: ";
    if ($retflag & Constants::SE_ECL_VISIBLE) {
        echo "VISIBLE ";
        if ($retflag & Constants::SE_ECL_MAX_VISIBLE) {
            echo "(maximum visible) ";
        }
    } else {
        echo "NOT VISIBLE";
    }
    echo "\n";

    // Eclipse magnitude and moon position
    printf("Umbral Magnitude: %.4f\n", $attr[0]);
    printf("Penumbral Magnitude: %.4f\n", $attr[1]);
    printf("Moon Azimuth: %.2f°\n", $attr[4]);
    printf("Moon Altitude (true): %.2f°\n", $attr[5]);
    printf("Moon Altitude (apparent): %.2f°\n", $attr[6]);

    // Phase visibility
    echo "\nPhase Visibility:\n";
    if ($retflag & Constants::SE_ECL_PENUMBBEG_VISIBLE) {
        echo "  ✓ Penumbral begin visible\n";
    }
    if ($retflag & Constants::SE_ECL_PARTBEG_VISIBLE) {
        echo "  ✓ Partial begin visible\n";
    }
    if ($retflag & Constants::SE_ECL_MAX_VISIBLE) {
        echo "  ✓ Maximum visible\n";
    }
    if ($retflag & Constants::SE_ECL_PARTEND_VISIBLE) {
        echo "  ✓ Partial end visible\n";
    }
    if ($retflag & Constants::SE_ECL_PENUMBEND_VISIBLE) {
        echo "  ✓ Penumbral end visible\n";
    }

    // Moon rise/set during eclipse
    if ($tret[8] > 0) {
        $date = swe_revjul($tret[8], Constants::SE_GREG_CAL);
        $ut = $date['ut'];
        $hour = floor($ut);
        $min = floor(($ut - $hour) * 60);
        $sec = (($ut - $hour) * 60 - $min) * 60;
        printf("\n  🌅 Moon rises during eclipse: %02d:%02d:%02.0f UT\n", $hour, $min, $sec);
    }

    if ($tret[9] > 0) {
        $date = swe_revjul($tret[9], Constants::SE_GREG_CAL);
        $ut = $date['ut'];
        $hour = floor($ut);
        $min = floor(($ut - $hour) * 60);
        $sec = (($ut - $hour) * 60 - $min) * 60;
        printf("  🌇 Moon sets during eclipse: %02d:%02d:%02.0f UT\n", $hour, $min, $sec);
    }

    // Contact times (if visible)
    echo "\nContact Times:\n";
    if ($tret[6] > 0) {
        $date = swe_revjul($tret[6], Constants::SE_GREG_CAL);
        $ut = $date['ut'];
        $hour = floor($ut);
        $min = floor(($ut - $hour) * 60);
        printf("  P1 (Penumbral begin): %02d:%02d UT\n", $hour, $min);
    } else {
        echo "  P1 (Penumbral begin): Not visible (moon below horizon)\n";
    }

    if ($tret[2] > 0) {
        $date = swe_revjul($tret[2], Constants::SE_GREG_CAL);
        $ut = $date['ut'];
        $hour = floor($ut);
        $min = floor(($ut - $hour) * 60);
        printf("  U1 (Partial begin): %02d:%02d UT\n", $hour, $min);
    } else if (($retflag & Constants::SE_ECL_PARTIAL) || ($retflag & Constants::SE_ECL_TOTAL)) {
        echo "  U1 (Partial begin): Not visible (moon below horizon)\n";
    }

    if ($tret[3] > 0) {
        $date = swe_revjul($tret[3], Constants::SE_GREG_CAL);
        $ut = $date['ut'];
        $hour = floor($ut);
        $min = floor(($ut - $hour) * 60);
        printf("  U4 (Partial end): %02d:%02d UT\n", $hour, $min);
    } else if (($retflag & Constants::SE_ECL_PARTIAL) || ($retflag & Constants::SE_ECL_TOTAL)) {
        echo "  U4 (Partial end): Not visible (moon below horizon)\n";
    }

    if ($tret[7] > 0) {
        $date = swe_revjul($tret[7], Constants::SE_GREG_CAL);
        $ut = $date['ut'];
        $hour = floor($ut);
        $min = floor(($ut - $hour) * 60);
        printf("  P4 (Penumbral end): %02d:%02d UT\n", $hour, $min);
    } else {
        echo "  P4 (Penumbral end): Not visible (moon below horizon)\n";
    }

    echo "\n" . str_repeat("-", 60) . "\n\n";
}

// Validation test for Moscow (should see entire eclipse)
echo "=== Validation: Moscow should see 2024-09-18 eclipse ===\n";
$geopos_moscow = [37.6173, 55.7558, 0];
$tret = [];
$attr = [];
$serr = '';

$retflag = swe_lun_eclipse_when_loc(
    $tjd_start,
    Constants::SEFLG_SWIEPH,
    $geopos_moscow,
    $tret,
    $attr,
    0,
    $serr
);

$date = swe_revjul($tret[0], Constants::SE_GREG_CAL);
$success = true;

// Expected: 2024-09-18
if ($date['y'] !== 2024 || $date['m'] !== 9 || $date['d'] !== 18) {
    echo sprintf("FAIL: Expected 2024-09-18, got %04d-%02d-%02d\n", $date['y'], $date['m'], $date['d']);
    $success = false;
}

// Expected: partial eclipse
if (!($retflag & Constants::SE_ECL_PARTIAL)) {
    echo "FAIL: Expected PARTIAL eclipse flag\n";
    $success = false;
}

// Expected: visible
if (!($retflag & Constants::SE_ECL_VISIBLE)) {
    echo "FAIL: Expected eclipse to be VISIBLE from Moscow\n";
    $success = false;
}

// Expected: maximum visible
if (!($retflag & Constants::SE_ECL_MAX_VISIBLE)) {
    echo "FAIL: Expected eclipse maximum to be VISIBLE from Moscow\n";
    $success = false;
}

// Moon should be above horizon (positive altitude)
if ($attr[5] <= 0) {
    echo sprintf("FAIL: Expected moon above horizon, got altitude %.2f°\n", $attr[5]);
    $success = false;
}

if ($success) {
    echo "✓ All validations PASSED\n";
    echo "  - Correct date: 2024-09-18\n";
    echo "  - Correct type: PARTIAL\n";
    echo "  - Eclipse visible from Moscow\n";
    printf("  - Moon altitude: %.2f° (above horizon)\n", $attr[5]);
} else {
    echo "✗ Some validations FAILED\n";
    exit(1);
}

echo "\n✓ ALL TESTS PASSED: Location-specific lunar eclipse search successful\n";
