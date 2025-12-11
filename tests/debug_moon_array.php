<?php

require_once __DIR__ . '/../vendor/autoload.php';

swe_set_ephe_path('C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\eph\ephe');

$xpm = array_fill(0, 6, 999.999);  // Initialize with sentinel values
$serr = null;

echo "BEFORE SwephCalculator::calculate:\n";
echo "xpm = [" . implode(", ", $xpm) . "]\n\n";

$retc = Swisseph\SwephFile\SwephCalculator::calculate(
    2460409.2630702,
    Swisseph\SwephFile\SwephConstants::SEI_MOON,
    Swisseph\SwephFile\SwephConstants::SEI_FILE_MOON,
    0,  // NO speed flag
    null,
    false,
    $xpm,
    $serr
);

echo "AFTER SwephCalculator::calculate:\n";
echo "Result code: $retc\n";
echo "Array count: " . count($xpm) . "\n";
echo "xpm = [" . implode(", ", $xpm) . "]\n";

// Check each element
for ($i = 0; $i <= 5; $i++) {
    $value = isset($xpm[$i]) ? $xpm[$i] : 'UNDEFINED';
    echo "xpm[$i] = $value\n";
}
