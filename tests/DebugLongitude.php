<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// Initialize Swiss Ephemeris
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$tjd_start = swe_julday(2023, 3, 1, 12.0, Constants::SE_GREG_CAL);

echo "Start date: " . $tjd_start . "\n";

// Get Saturn position
$saturn = [];
$serr = '';
$ret = swe_calc($tjd_start, Constants::SE_SATURN, Constants::SEFLG_SWIEPH, $saturn, $serr);
if ($ret < 0) {
    echo "Error calculating Saturn: $serr\n";
    exit(1);
}

// Get Moon position
$moon = [];
$ret = swe_calc($tjd_start, Constants::SE_MOON, Constants::SEFLG_SWIEPH, $moon, $serr);
if ($ret < 0) {
    echo "Error calculating Moon: $serr\n";
    exit(1);
}

echo "Saturn longitude: " . $saturn[0] . "°\n";
echo "Saturn latitude: " . $saturn[1] . "°\n";
echo "Moon longitude: " . $moon[0] . "°\n";
echo "Moon latitude: " . $moon[1] . "°\n";

$dl = swe_degnorm($saturn[0] - $moon[0]);
echo "Longitude difference (normalized): " . $dl . "°\n";

// Moon moves approximately 13° per day
$daysToConjunction = $dl / 13.0;
echo "Estimated days to conjunction: " . $daysToConjunction . "\n";

$conjDate = $tjd_start + $daysToConjunction;
echo "Estimated conjunction date: JD " . $conjDate . "\n";

// Convert to calendar date
list($year, $month, $day, $hour) = swe_revjul($conjDate, Constants::SE_GREG_CAL);
echo sprintf("Calendar date: %04d-%02d-%02d %02d:00\n", $year, $month, $day, (int)$hour);

swe_close();
