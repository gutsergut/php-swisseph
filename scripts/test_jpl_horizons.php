<?php

declare(strict_types=1);

namespace Swisseph;

require_once __DIR__ . '/../vendor/autoload.php';

echo "=== JPL Horizons Corrections Test ===\n\n";

// Test vector in ICRS coordinates
$xIcrs = [1.0, 0.5, 0.25];

// Test epochs
$epochs = [
    ['name' => '1962 (table start)', 'jd' => 2437846.5],
    ['name' => '1985 (mid-table)', 'jd' => 2446066.5],
    ['name' => 'J2000', 'jd' => 2451545.0],
    ['name' => '2012 (table end)', 'jd' => 2456109.5],
    ['name' => '2025 (beyond table)', 'jd' => 2460676.5],
];

echo "Initial ICRS position: [" . implode(', ', array_map(fn($v) => sprintf('%.6f', $v), $xIcrs)) . "]\n\n";

foreach ($epochs as $epoch) {
    echo "--- {$epoch['name']} (JD {$epoch['jd']}) ---\n";

    // Test WITHOUT JPL Horizons correction
    $iflagNoJpl = 0;
    $xNoJpl = Bias::apply($xIcrs, $epoch['jd'], $iflagNoJpl, Bias::MODEL_IAU_2006, false);
    echo "Without JPL Horizons: [" . implode(', ', array_map(fn($v) => sprintf('%.12f', $v), $xNoJpl)) . "]\n";

    // Test WITH JPL Horizons correction
    $iflagWithJpl = Constants::SEFLG_JPLHOR_APPROX;
    $xWithJpl = Bias::apply($xIcrs, $epoch['jd'], $iflagWithJpl, Bias::MODEL_IAU_2006, false);
    echo "With    JPL Horizons: [" . implode(', ', array_map(fn($v) => sprintf('%.12f', $v), $xWithJpl)) . "]\n";

    // Calculate difference
    $diff = [
        $xWithJpl[0] - $xNoJpl[0],
        $xWithJpl[1] - $xNoJpl[1],
        $xWithJpl[2] - $xNoJpl[2],
    ];
    $diffMag = sqrt($diff[0] ** 2 + $diff[1] ** 2 + $diff[2] ** 2);

    // Convert difference to arcseconds (approximate)
    // For small differences in ~1 AU: delta_angle ≈ delta_pos / distance
    $diffArcsec = ($diffMag / 1.0) * 206265.0; // radians to arcseconds

    echo "Difference magnitude:  " . sprintf('%.3e AU (~%.3f mas)', $diffMag, $diffArcsec * 1000) . "\n";

    // Round-trip test
    $xRoundtrip = Bias::apply($xWithJpl, $epoch['jd'], $iflagWithJpl, Bias::MODEL_IAU_2006, true);
    $rtError = sqrt(
        ($xRoundtrip[0] - $xIcrs[0]) ** 2 +
        ($xRoundtrip[1] - $xIcrs[1]) ** 2 +
        ($xRoundtrip[2] - $xIcrs[2]) ** 2
    );
    echo "Round-trip error:      " . sprintf('%.3e AU', $rtError) . "\n";
    echo "\n";
}

// Test JPL Horizons mode 2 (no correction)
echo "--- JPL Horizons Mode 2 (no correction) ---\n";
$tjd = 2451545.0;
$iflagMode2 = Constants::SEFLG_JPLHOR_APPROX;
$xMode2 = Bias::apply($xIcrs, $tjd, $iflagMode2, Bias::MODEL_IAU_2006, false, JplHorizonsApprox::SEMOD_JPLHORA_2);
$xNormal = Bias::apply($xIcrs, $tjd, 0, Bias::MODEL_IAU_2006, false);
$mode2Same = ($xMode2[0] === $xNormal[0] && $xMode2[1] === $xNormal[1] && $xMode2[2] === $xNormal[2]);
echo "Mode 2 same as no JPL flag: " . ($mode2Same ? "✓ YES" : "✗ NO") . "\n\n";

// Test correction magnitude across table
echo "--- Correction magnitude across years ---\n";
for ($year = 1962; $year <= 2012; $year += 10) {
    $jd = 2437846.5 + ($year - 1962) * 365.25;
    $corr = JplHorizonsApprox::apply($xIcrs, $jd, Constants::SEFLG_JPLHOR_APPROX, false);
    // The correction is applied, but we need to extract just the correction value
    // For display purposes, just note the year
    echo "$year: JD = " . sprintf('%.1f', $jd) . "\n";
}

echo "\n✅ JPL Horizons corrections test complete!\n";
