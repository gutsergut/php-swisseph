<?php

require __DIR__ . '/vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;

swe_set_ephe_path('C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/ephe');

$serr = '';
$xx = [];
$ret = swe_calc(2451545.0, Constants::SE_JUPITER, Constants::SEFLG_SWIEPH, $xx, $serr);

$swed = SwedState::getInstance();
$pdp = $swed->pldat[SwephConstants::SEI_JUPITER];

echo "Jupiter ephemeris flags:\n";
echo sprintf("  iflg = 0x%X\n", $pdp->iflg);
echo sprintf("  SEI_FLG_ROTATE = 0x%X\n", SwephConstants::SEI_FLG_ROTATE);
echo sprintf("  has_rotate = %d\n", ($pdp->iflg & SwephConstants::SEI_FLG_ROTATE) ? 1 : 0);
echo sprintf("  neval = %d\n", $pdp->neval);
echo sprintf("  ncoe = %d\n", $pdp->ncoe);

echo "\nChecking rotation for different planets:\n";
$planets = [
    'Moon' => Constants::SE_MOON,
    'Mercury' => Constants::SE_MERCURY,
    'Venus' => Constants::SE_VENUS,
    'Mars' => Constants::SE_MARS,
    'Jupiter' => Constants::SE_JUPITER,
    'Saturn' => Constants::SE_SATURN,
];

foreach ($planets as $name => $ipl) {
    $ret = swe_calc(2451545.0, $ipl, Constants::SEFLG_SWIEPH, $xx, $serr);
    $ipli = $ipl;
    if ($ipl == Constants::SE_MOON) {
        $ipli = SwephConstants::SEI_MOON;
    } elseif ($ipl <= Constants::SE_PLUTO) {
        $ipli = $ipl + SwephConstants::SEI_MERCURY;
    }

    $pdp = $swed->pldat[$ipli];
    $hasRotate = ($pdp->iflg & SwephConstants::SEI_FLG_ROTATE) ? 1 : 0;
    echo sprintf("  %s: iflg=0x%X, has_rotate=%d\n", str_pad($name, 10), $pdp->iflg, $hasRotate);
}
