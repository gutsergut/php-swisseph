#!/usr/bin/env php
<?php
/**
 * Debug VSOP87 Calculator - проверка загрузки и вычислений Venus
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Domain\Vsop87\VsopSegmentedLoader;
use Swisseph\Domain\Vsop87\Vsop87Calculator;
use Swisseph\Domain\Vsop87\Types;

$venusDir = __DIR__ . '/../data/vsop87/venus';

echo "Loading Venus model from: $venusDir\n";

$loader = new VsopSegmentedLoader();
$model = $loader->loadPlanet($venusDir);

echo "L powers: " . implode(', ', $model->L->powers()) . "\n";
echo "B powers: " . implode(', ', $model->B->powers()) . "\n";
echo "R powers: " . implode(', ', $model->R->powers()) . "\n";

// Тест на J2000.0
$jd_tt = 2451545.0;
$t = Types::tMillennia($jd_tt);
echo "\nJ2000.0: JD=$jd_tt, t=$t\n";

$calc = new Vsop87Calculator();
[$Ldeg, $Bdeg, $Rau] = $calc->compute($model, $jd_tt);

echo "VSOP87 Result:\n";
echo "  L = $Ldeg °\n";
echo "  B = $Bdeg °\n";
echo "  R = $Rau AU\n";

// Ожидаемые значения из swetest64 для Venus J2000.0 (геоцентрические):
echo "\nExpected (geocentric from swetest64):\n";
echo "  lon = 241.561335°\n";
echo "  lat = 2.069926°\n";
echo "  dist = 1.137574429 AU\n";

echo "\nNote: VSOP87 gives heliocentric coordinates - need to convert to geocentric!\n";
