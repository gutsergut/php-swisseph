<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/functions.php';

use Swisseph\Constants;

echo "=== Testing swe_set_ephe_path + swe_calc ===\n\n";

// Set ephemeris path
swe_set_ephe_path(__DIR__ . '/../eph/ephe');
echo "Ephe path set to: " . __DIR__ . "/../eph/ephe\n\n";

$x = array_fill(0, 6, 0.0);
$serr = '';

$result = swe_calc(
    2451545.0,
    Constants::SE_VENUS,
    Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED,
    $x,
    $serr
);

echo "Result: " . $result . "\n";
echo "Error: '" . $serr . "'\n";
echo "Longitude: " . $x[0] . "°\n";
echo "Speed: " . $x[3] . "°/day\n";
