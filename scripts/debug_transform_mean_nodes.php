<?php
require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Swe\Functions\NodesApsidesFunctions;
use Swisseph\Constants;

$jd = 2451545.0; // J2000.0
$planet = 4; // Mars

echo "Testing Mars mean nodes transformation at J2000.0\n\n";

// Enable debug in transformation
class DebugNodesApsidesFunctions extends NodesApsidesFunctions {
    public static function debugTransform(float $jd, int $planet): void {
        $xna = [49.558093, 0.0, 1.5, 0.0, 0.0, 0.0]; // Manual Mars ascending node
        $xnd = [229.558093, 0.0, 1.5, 0.0, 0.0, 0.0];
        $xpe = [336.060234, 0.0, 1.38, 0.0, 0.0, 0.0];
        $xap = [156.060234, 0.0, 1.67, 0.0, 0.0, 0.0];

        echo "BEFORE transformation:\n";
        echo "  xna[0] = {$xna[0]}° (heliocentric ecliptic)\n\n";

        $xx = [$xna, $xnd, $xpe, $xap];

        // Call transformation
        $iflag = Constants::SEFLG_SWIEPH;
        self::applyFinalNodApsTransformations($xx, $jd, $iflag, $planet);

        echo "\nAFTER transformation:\n";
        echo "  xx[0][0] = {$xx[0][0]}° (geocentric ecliptic)\n";
        echo "\nExpected: 7.6738357°\n";
    }
}

// This won't work because applyFinalNodApsTransformations is private
// Need to add echo inside the function instead

// Just run normal test
$xnasc = [];
$xndsc = [];
$xperi = [];
$xaphe = [];
$serr = '';
NodesApsidesFunctions::nodAps(
    $jd,
    $planet,
    Constants::SEFLG_SWIEPH,
    Constants::SE_NODBIT_MEAN,
    $xnasc,
    $xndsc,
    $xperi,
    $xaphe,
    $serr
);

echo "Result: {$xnasc[0]}°\n";
echo "Expected: 7.6738357°\n";
echo "\nDifference: " . ($xnasc[0] - 7.6738357) . "°\n";
echo "\nThis suggests the transformation is NOT being applied at all!\n";
echo "Adding debug output to applyFinalNodApsTransformations would help...\n";
