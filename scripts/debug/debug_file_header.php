<?php

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\SwephFile\SwephReader;
use Swisseph\SwephFile\SwephConstants;
use Swisseph\SwephFile\SwedState;

\swe_set_ephe_path('C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/ephe');

echo "Testing file header reading for sepl_18.se1\n\n";

$swed = SwedState::getInstance();
$serr = null;

// Open and read header
if (!SwephReader::openAndReadHeader(SwephConstants::SEI_FILE_PLANET, 'sepl_18.se1', $swed->ephepath, $serr)) {
    echo "ERROR: $serr\n";
    exit(1);
}

echo "File opened successfully!\n\n";

$fdp = &$swed->fidat[SwephConstants::SEI_FILE_PLANET];

echo "Number of planets in file: {$fdp->npl}\n";
echo "Planet indices in file:\n";
for ($i = 0; $i < $fdp->npl; $i++) {
    $ipli = $fdp->ipl[$i];
    echo "  [$i] => $ipli\n";
}

echo "\nPlanet data status:\n";
$planetNames = [
    SwephConstants::SEI_SUNBARY => 'SEI_SUNBARY',
    SwephConstants::SEI_EARTH => 'SEI_EARTH',
    SwephConstants::SEI_JUPITER => 'SEI_JUPITER',
];

foreach ($planetNames as $idx => $name) {
    $pdp = &$swed->pldat[$idx];
    echo "$name (index $idx): dseg={$pdp->dseg}, tfstart={$pdp->tfstart}, tfend={$pdp->tfend}\n";
}
