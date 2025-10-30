<?php

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\SwephFile\SwephCalculator;
use Swisseph\SwephFile\SwephConstants;

\swe_set_ephe_path('C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/ephe');

echo "Testing SwephCalculator for SEI_EARTH (to get SEI_SUNBARY as by-product)\n\n";

$xearth = [];
$serr = null;
$ret = SwephCalculator::calculate(2451545.0, SwephConstants::SEI_EARTH, SwephConstants::SEI_FILE_PLANET, 0, null, true, $xearth, $serr);

echo "retc=$ret\n";
if ($ret < 0) {
    echo "ERROR: $serr\n";
} else {
    echo "SUCCESS! Earth position:\n";
    echo sprintf("x=%.10f, y=%.10f, z=%.10f\n", $xearth[0], $xearth[1], $xearth[2]);

    // Check if Sun was computed as by-product
    $swed = \Swisseph\SwephFile\SwedState::getInstance();
    $psdp = &$swed->pldat[SwephConstants::SEI_SUNBARY];
    echo "\nSun barycentric (by-product):\n";
    echo sprintf("x=%.10f, y=%.10f, z=%.10f\n", $psdp->x[0], $psdp->x[1], $psdp->x[2]);
    echo sprintf("teval=%.10f\n", $psdp->teval);
}
