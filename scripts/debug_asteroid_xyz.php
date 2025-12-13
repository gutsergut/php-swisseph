<?php
/**
 * Debug script to trace raw asteroid coordinates
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;
use Swisseph\SwephFile\SwephReader;
use Swisseph\SwephFile\SwephCalculator;

$ephePath = __DIR__ . '/../../eph/ephe';
\Swisseph\State::setEphePath($ephePath);

$swed = SwedState::getInstance();
$swed->ephepath = $ephePath;

$jd = 2460000.5;
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;
$ifno = SwephConstants::SEI_FILE_MAIN_AST;

echo "Testing raw asteroid coordinates at JD $jd\n";
echo str_repeat("=", 60) . "\n\n";

// Reference values from swetest (ecliptic longitude)
$reference = [
    'Chiron' => ['lon' => 13.64664692, 'lat' => 1.63127125, 'dist' => 19.577859095],
    'Pholus' => ['lon' => 278.4755978, 'lat' => 9.9411965, 'dist' => 30.14972036],
    'Ceres'  => ['lon' => 185.3528945, 'lat' => 16.2879155, 'dist' => 1.673828157],
    'Pallas' => ['lon' => 100.7999382, 'lat' => -40.1074536, 'dist' => 1.507360256],
    'Juno'   => ['lon' => 21.8129822, 'lat' => -8.1383841, 'dist' => 2.549714074],
    'Vesta'  => ['lon' => 7.4579634, 'lat' => -5.3827112, 'dist' => 3.252325689],
];

$planets = [
    [12, 'Chiron'],
    [13, 'Pholus'],
    [14, 'Ceres'],
    [15, 'Pallas'],
    [16, 'Juno'],
    [17, 'Vesta'],
];

foreach ($planets as [$ipli, $name]) {
    $xpret = [];
    $serr = null;

    // Clear cache to force fresh calculation
    $pdp = &$swed->pldat[$ipli];
    $pdp->teval = 0.0;
    $pdp->segp = null;

    $retc = SwephCalculator::calculate(
        $jd,
        $ipli,
        $ifno,
        $iflag,
        null,
        true,
        $xpret,
        $serr
    );

    if ($retc != Constants::SE_OK) {
        echo "$name: ERROR: $serr\n";
        continue;
    }

    // Convert to spherical
    $x = $xpret[0];
    $y = $xpret[1];
    $z = $xpret[2];
    $dist = sqrt($x*$x + $y*$y + $z*$z);

    // RA/Dec from cartesian
    $ra = atan2($y, $x) * 180.0 / M_PI;
    if ($ra < 0) $ra += 360;
    $dec = asin($z / $dist) * 180.0 / M_PI;

    echo "$name (ipli=$ipli):\n";
    echo sprintf("  Raw XYZ: [%.12f, %.12f, %.12f] AU\n", $x, $y, $z);
    echo sprintf("  Dist: %.9f AU\n", $dist);
    echo sprintf("  RA: %.6f° Dec: %.6f° (equatorial, raw)\n", $ra, $dec);

    // pdp info
    echo sprintf("  pdp: ibdy=%d, lndx0=%d, iflg=0x%02X, dseg=%.1f\n",
        $pdp->ibdy, $pdp->lndx0, $pdp->iflg, $pdp->dseg);
    echo sprintf("  Expected: lon=%.6f° lat=%.6f° dist=%.6f AU\n",
        $reference[$name]['lon'], $reference[$name]['lat'], $reference[$name]['dist']);

    // Check distance
    $dist_expected = $reference[$name]['dist'];
    $dist_diff = abs($dist - $dist_expected);
    if ($dist_diff > 0.1) {
        echo sprintf("  *** DISTANCE MISMATCH: diff=%.6f AU ***\n", $dist_diff);
    }
    echo "\n";
}

echo "Done.\n";
