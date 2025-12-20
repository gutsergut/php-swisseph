<?php
require 'vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\Swe\Jpl\JplEphemeris;
use Swisseph\Swe\Jpl\JplConstants;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;
use Swisseph\Swe\Planets\PlanetApparentPipeline;

// Reset and open JPL
JplEphemeris::resetInstance();
$jpl = JplEphemeris::getInstance();
$ss = [];
$serr = '';
$ret = $jpl->open($ss, 'de200.eph', 'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/data/ephemerides/jpl/', $serr);

if ($ret !== JplConstants::OK) {
    die("Failed to open: $serr\n");
}

$jdTt = 2451545.0;
$eps = deg2rad(23.4392911);
$cosEps = cos($eps);
$sinEps = sin($eps);

// Get Mercury, Earth, Sun barycentric
$mercBary = [];
$jpl->pleph($jdTt, JplConstants::J_MERCURY, JplConstants::J_SBARY, $mercBary, $serr);
$earthBary = [];
$jpl->pleph($jdTt, JplConstants::J_EARTH, JplConstants::J_SBARY, $earthBary, $serr);
$sunBary = [];
$jpl->pleph($jdTt, JplConstants::J_SUN, JplConstants::J_SBARY, $sunBary, $serr);

// Convert to ecliptic
function eq2ecl($eq, $cosEps, $sinEps) {
    return [
        $eq[0],
        $eq[1] * $cosEps + $eq[2] * $sinEps,
        -$eq[1] * $sinEps + $eq[2] * $cosEps,
        $eq[3],
        $eq[4] * $cosEps + $eq[5] * $sinEps,
        -$eq[4] * $sinEps + $eq[5] * $cosEps,
    ];
}

$mercEcl = eq2ecl($mercBary, $cosEps, $sinEps);
$earthEcl = eq2ecl($earthBary, $cosEps, $sinEps);
$sunEcl = eq2ecl($sunBary, $cosEps, $sinEps);

echo "Mercury bary ecl: x=".sprintf("%.6f", $mercEcl[0]).", y=".sprintf("%.6f", $mercEcl[1]).", z=".sprintf("%.6f", $mercEcl[2])."\n";
echo "Earth bary ecl:   x=".sprintf("%.6f", $earthEcl[0]).", y=".sprintf("%.6f", $earthEcl[1]).", z=".sprintf("%.6f", $earthEcl[2])."\n";

// Store in SwedState
$swed = SwedState::getInstance();
$swed->pldat[SwephConstants::SEI_EARTH]->x = $earthEcl;
$swed->pldat[SwephConstants::SEI_SUNBARY]->x = $sunEcl;

// Check what pipeline sees
echo "\nSwedState after set:\n";
echo "  Earth->x: ".print_r($swed->pldat[SwephConstants::SEI_EARTH]->x, true);
echo "  SunBary->x: ".print_r($swed->pldat[SwephConstants::SEI_SUNBARY]->x, true);

// Now test pipeline manually
$iflag = 0;
$final = PlanetApparentPipeline::computeFinal($jdTt, Constants::SE_MERCURY, $iflag, $mercEcl);
printf("\nPipeline result: lon=%.6f, lat=%.6f, dist=%.6f\n", $final[0], $final[1], $final[2]);
