<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

$LAPSE_RATE = 0.0065;

// Berlin altitude
$alt = 0.0;

// Calculate pressure
$atpress = 1013.25 * pow(1.0 - 0.0065 * $alt / 288.0, 5.255);
$attemp = 10.0; // default temp

echo "Pressure: $atpress mbar\n";
echo "Temperature: $attemp°C\n\n";

// Test refraction at horizon
$xx = [0.000001, 0.0];
swe_refrac_extended($xx[0], 0.0, $atpress, $attemp, $LAPSE_RATE, Constants::SE_APP_TO_TRUE, $xx);

$refr = $xx[1] - $xx[0];
echo "Apparent alt: " . $xx[0] . "°\n";
echo "True alt: " . $xx[1] . "°\n";
echo "Refraction: $refr degrees\n";
echo "Refraction: " . ($refr * 60) . " arcminutes\n";
