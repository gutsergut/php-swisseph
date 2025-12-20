#!/usr/bin/env php
<?php
/**
 * Debug Mercury VSOP87 - проверка гелиоцентрических координат
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Domain\Vsop87\VsopSegmentedLoader;
use Swisseph\Domain\Vsop87\Vsop87Calculator;

$mercuryDir = __DIR__ . '/../data/vsop87/mercury';

echo "Loading Mercury model...\n";
$loader = new VsopSegmentedLoader();
$model = $loader->loadPlanet($mercuryDir);

echo "L powers: " . implode(', ', $model->L->powers()) . "\n";
echo "B powers: " . implode(', ', $model->B->powers()) . "\n";
echo "R powers: " . implode(', ', $model->R->powers()) . "\n\n";

$calc = new Vsop87Calculator();

// J2000.0
$jd_tt = 2451545.0;
[$Ldeg, $Bdeg, $Rau] = $calc->compute($model, $jd_tt);

echo "Mercury J2000.0 (heliocentric VSOP87):\n";
echo "  L = $Ldeg °\n";
echo "  B = $Bdeg °\n";
echo "  R = $Rau AU\n\n";

// Эталонные гелиоцентрические координаты Mercury J2000.0 из swetest64 -hel
echo "Expected heliocentric from swetest64 -hel:\n";
echo "Check with: swetest64.exe -p2 -b1.1.2000 -hel\n";
