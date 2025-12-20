<?php
/**
 * Тест конвертации дат и времени
 */

require __DIR__ . '/../tests/bootstrap.php';

use Swisseph\Constants;

// Test Julian day calculation
$jd = swe_julday(2000, 1, 1, 12.0, Constants::SE_GREG_CAL);
if (abs($jd - 2451545.0) > 0.001) {
    fwrite(STDERR, "J2000.0 calculation wrong: $jd\n");
    exit(1);
}

// Test reverse conversion
$revjul = swe_revjul($jd, Constants::SE_GREG_CAL);
if (!is_array($revjul)) {
    fwrite(STDERR, "swe_revjul did not return array\n");
    exit(2);
}

if (!isset($revjul['y'], $revjul['m'], $revjul['d'], $revjul['ut'])) {
    fwrite(STDERR, "swe_revjul missing required keys\n");
    exit(3);
}

if ($revjul['y'] !== 2000 || $revjul['m'] !== 1 || $revjul['d'] !== 1) {
    fwrite(STDERR, sprintf("Date mismatch: %d-%d-%d\n", $revjul['y'], $revjul['m'], $revjul['d']));
    exit(4);
}

if (abs($revjul['ut'] - 12.0) > 0.001) {
    fwrite(STDERR, "Time mismatch: {$revjul['ut']}\n");
    exit(5);
}

// Test Julian calendar
$jd_julian = swe_julday(1582, 10, 4, 12.0, Constants::SE_JUL_CAL);
$jd_gregorian = swe_julday(1582, 10, 14, 12.0, Constants::SE_GREG_CAL);
if (abs($jd_julian - $jd_gregorian) > 0.1) {
    fwrite(STDERR, "Calendar switch issue\n");
    exit(6);
}

// Test UTC to JD conversion
$iyear = 2024; $imonth = 1; $iday = 15;
$ihour = 12; $imin = 30; $dsec = 45.5;
$serr = null;

$jd_utc = swe_utc_to_jd($iyear, $imonth, $iday, $ihour, $imin, $dsec,
                         Constants::SE_GREG_CAL, $serr);

if (!is_array($jd_utc) || !isset($jd_utc[0], $jd_utc[1])) {
    fwrite(STDERR, "swe_utc_to_jd missing JD values\n");
    exit(8);
}

// Verify JD values are reasonable
if ($jd_utc[0] <= 0.0 || $jd_utc[1] <= 0.0) {
    fwrite(STDERR, "JD values invalid\n");
    exit(9);
}
