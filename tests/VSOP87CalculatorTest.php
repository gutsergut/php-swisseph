<?php
require __DIR__ . '/bootstrap.php';

use Swisseph\Domain\Vsop87\VsopLoader;
use Swisseph\Domain\Vsop87\Vsop87Calculator;
use Swisseph\Domain\Vsop87\Types; // kept for possible direct t conversions later

$loader = new VsopLoader();
$model = $loader->loadFromJsonFile(__DIR__ . '/../data/vsop87/mercury_sample.json');
$calc = new Vsop87Calculator();

// Test a few epochs
$testJds = [2451545.0, 2453000.5];
foreach ($testJds as $jd) {
    [$Ldeg, $Bdeg, $R] = $calc->compute($model, $jd);
    // Basic sanity: ranges
    if ($Ldeg < 0 || $Ldeg >= 360) {
        fwrite(STDERR, "Longitude out of range for JD $jd: $Ldeg\n");
        exit(1);
    }
    if (abs($Bdeg) > 10) {
        fwrite(STDERR, "Latitude suspicious for JD $jd: $Bdeg\n");
        exit(1);
    }
    if ($R < 0.1 || $R > 1.0) {
        fwrite(STDERR, "Radius suspicious for JD $jd: $R\n");
        exit(1);
    }
    echo sprintf("JD %.1f L=%.6f B=%.6f R=%.9f\n", $jd, $Ldeg, $Bdeg, $R);
}

echo "VSOP87CalculatorTest OK\n";
