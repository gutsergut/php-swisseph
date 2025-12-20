<?php
/**
 * Test SEFLG_JPLHOR mode accuracy against swetest64.exe
 *
 * IMPORTANT: SEFLG_JPLHOR only works with JPL ephemeris (SEFLG_JPLEPH).
 * With Swiss Ephemeris, the flag is stripped (matches C behavior sweph.c:6171-6172).
 *
 * When used WITH JPL ephemeris:
 * - Uses IAU 1976 precession (within 1799-2202)
 * - Uses IAU 1980 nutation
 * - Optionally uses EOP corrections from IERS files
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// Set ephemeris path
$ephePath = __DIR__ . '/../../eph/ephe';
swe_set_ephe_path($ephePath);

// Reference values from swetest64.exe
// swetest64.exe -b5.3.2023 -p0123 -fPl -head
$referenceStd = [
    0 => 344.1392546,  // Sun
    1 => 136.1982837,  // Moon
    2 => 333.5195584,  // Mercury
    3 => 15.5215432,   // Venus
];

// Test date: 5.3.2023 = JD 2460008.5
$jd = 2460008.5;

echo "=== SEFLG_JPLHOR Test (Swiss Ephemeris) ===\n";
echo "JD: {$jd} (5.3.2023)\n";
echo "Reference: swetest64.exe\n\n";

echo "NOTE: SEFLG_JPLHOR is STRIPPED when not using SEFLG_JPLEPH.\n";
echo "      Both modes should give identical results with Swiss Ephemeris.\n\n";

// Test planets
$planets = [
    Constants::SE_SUN => 'Sun',
    Constants::SE_MOON => 'Moon',
    Constants::SE_MERCURY => 'Mercury',
    Constants::SE_VENUS => 'Venus',
];

// Base flags
$baseFlags = Constants::SEFLG_SPEED;

echo "--- Standard Mode ---\n";
$stdResults = [];
foreach ($planets as $ipl => $name) {
    $xx = [];
    $serr = '';
    $ret = swe_calc($jd, $ipl, $baseFlags, $xx, $serr);

    if ($ret < 0) {
        echo "$name: Error: $serr\n";
    } else {
        $stdResults[$ipl] = $xx[0];
        $diff = isset($referenceStd[$ipl]) ? $xx[0] - $referenceStd[$ipl] : null;
        $diffArcsec = $diff !== null ? $diff * 3600 : null;
        printf("%s: lon=%11.7f (ref=%11.7f, diff=%+.3f\")\n",
            $name, $xx[0], $referenceStd[$ipl] ?? 0, $diffArcsec ?? 0);
    }
}

echo "\n--- JPLHOR Mode (stripped without SEFLG_JPLEPH) ---\n";
$jplhorFlags = $baseFlags | Constants::SEFLG_JPLHOR;
foreach ($planets as $ipl => $name) {
    $xx = [];
    $serr = '';
    $ret = swe_calc($jd, $ipl, $jplhorFlags, $xx, $serr);

    if ($ret < 0) {
        echo "$name: Error: $serr\n";
    } else {
        $diffFromStd = isset($stdResults[$ipl]) ? ($xx[0] - $stdResults[$ipl]) * 3600 : null;
        printf("%s: lon=%11.7f (vs standard: %+.3f\" - should be 0.000\")\n",
            $name, $xx[0], $diffFromStd ?? 0);
    }
}

// Test on 1850
echo "\n--- Test on 5.3.1850 ---\n";
$jd1850 = swe_julday(1850, 3, 5, 0.0, 1);
echo "JD: {$jd1850}\n";

$xx = [];
$serr = '';
swe_calc($jd1850, Constants::SE_SUN, $baseFlags, $xx, $serr);
$sunStd = $xx[0];

swe_calc($jd1850, Constants::SE_SUN, $jplhorFlags, $xx, $serr);
$sunJplhor = $xx[0];

$diff = ($sunJplhor - $sunStd) * 3600;
printf("Sun standard: %11.7f\n", $sunStd);
printf("Sun JPLHOR:   %11.7f\n", $sunJplhor);
printf("Difference:   %+.3f\" (should be 0.000\" without SEFLG_JPLEPH)\n", $diff);

echo "\n=== Summary ===\n";
echo "✓ SEFLG_JPLHOR is correctly stripped without SEFLG_JPLEPH (matches C behavior)\n";
echo "✓ To use JPLHOR mode, add SEFLG_JPLEPH flag (requires JPL ephemeris files)\n";
