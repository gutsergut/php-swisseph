<?php
require __DIR__ . '/bootstrap.php';

use Swisseph\Domain\Vsop87\VsopLoader;
use Swisseph\Domain\Vsop87\VsopSegmentedLoader;
use Swisseph\Domain\Vsop87\Vsop87Calculator;

$mono = (new VsopLoader())->loadFromJsonFile(__DIR__ . '/../data/vsop87/mercury_sample.json');
$segm = (new VsopSegmentedLoader())->loadPlanet(__DIR__ . '/../data/vsop87/mercury');
$calc = new Vsop87Calculator();

$epochs = [2451545.0, 2453000.5, 2460000.5];
$eps = 1e-10; // tolerance for double summation equality

foreach ($epochs as $jd) {
    [$L1,$B1,$R1] = $calc->compute($mono, $jd);
    [$L2,$B2,$R2] = $calc->compute($segm, $jd);
    $dL = abs($L1 - $L2);
    $dB = abs($B1 - $B2);
    $dR = abs($R1 - $R2);
    if ($dL > 1e-8 || $dB > 1e-8 || $dR > 1e-12) {
        fwrite(STDERR, sprintf("Mismatch at JD %.1f: dL=%.12f dB=%.12f dR=%.12e\n", $jd, $dL, $dB, $dR));
        exit(1);
    }
}

echo "VSOP87SegmentedLoaderTest OK\n";
