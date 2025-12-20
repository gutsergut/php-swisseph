<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/functions.php';

use Swisseph\Constants;
use Swisseph\Domain\Heliacal\HeliacalAscensional;

echo "=== Testing find_conjunct_sun ===\n\n";

$tjd = 0.0;
$serr = '';

$result = HeliacalAscensional::find_conjunct_sun(
    2451545.0 - 50,  // Venus inferior conjunction search
    Constants::SE_VENUS,
    Constants::SEFLG_SWIEPH,
    1,  // TypeEvent
    $tjd,
    $serr
);

echo "Result: " . $result . "\n";
echo "Error: '" . $serr . "'\n";
echo "JD: " . $tjd . "\n";
