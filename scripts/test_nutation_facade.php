<?php

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Nutation;

// Test date: J2000.0
$jd = 2451545.0;

echo "Nutation Facade Test at J2000.0:\n";
echo str_repeat("=", 70) . "\n\n";

// Test default model
[$dpsi_default, $deps_default] = Nutation::calc($jd);
echo "Default model (" . Nutation::getModelName(Nutation::MODEL_DEFAULT) . "):\n";
echo sprintf("  Δψ: %12.9f rad = %10.6f\"\n", $dpsi_default, $dpsi_default * 206264.80624709636);
echo sprintf("  Δε: %12.9f rad = %10.6f\"\n\n", $deps_default, $deps_default * 206264.80624709636);

// Test all models via facade
foreach ([Nutation::MODEL_IAU_1980, Nutation::MODEL_IAU_2000A, Nutation::MODEL_IAU_2000B] as $model) {
    [$dpsi, $deps] = Nutation::calc($jd, $model);
    echo Nutation::getModelName($model) . ":\n";
    echo sprintf("  Δψ: %12.9f rad = %10.6f\"\n", $dpsi, $dpsi * 206264.80624709636);
    echo sprintf("  Δε: %12.9f rad = %10.6f\"\n\n", $deps, $deps * 206264.80624709636);
}

// Test convenience methods
echo "Convenience methods:\n";
echo str_repeat("-", 70) . "\n\n";

[$dpsi_1980, $deps_1980] = Nutation::calcIau1980($jd);
echo "calcIau1980():\n";
echo sprintf("  Δψ: %10.6f\"  Δε: %10.6f\"\n\n",
    $dpsi_1980 * 206264.80624709636,
    $deps_1980 * 206264.80624709636);

[$dpsi_2000a, $deps_2000a] = Nutation::calcIau2000A($jd);
echo "calcIau2000A():\n";
echo sprintf("  Δψ: %10.6f\"  Δε: %10.6f\"\n\n",
    $dpsi_2000a * 206264.80624709636,
    $deps_2000a * 206264.80624709636);

[$dpsi_2000b, $deps_2000b] = Nutation::calcIau2000B($jd);
echo "calcIau2000B():\n";
echo sprintf("  Δψ: %10.6f\"  Δε: %10.6f\"\n\n",
    $dpsi_2000b * 206264.80624709636,
    $deps_2000b * 206264.80624709636);

// Test at different epoch
$jd2024 = 2460310.5;
echo "\n" . str_repeat("=", 70) . "\n";
echo "Nutation at 2024-01-01 00:00 TT:\n";
echo str_repeat("=", 70) . "\n\n";

foreach ([Nutation::MODEL_IAU_1980, Nutation::MODEL_IAU_2000A, Nutation::MODEL_IAU_2000B] as $model) {
    [$dpsi, $deps] = Nutation::calc($jd2024, $model);
    echo sprintf("%-25s Δψ: %10.6f\"  Δε: %10.6f\"\n",
        Nutation::getModelName($model) . ':',
        $dpsi * 206264.80624709636,
        $deps * 206264.80624709636);
}
