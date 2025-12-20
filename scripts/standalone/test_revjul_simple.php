<?php

require_once __DIR__ . '/vendor/autoload.php';

// Test revjul() with correct JD for 1 BC
$jd = 1721060.0; // 1 BC Jan 1, 12:00 UT (year 0 astronomical)

// Simplified revjul based on C swe_revjul
function revjul_simple(float $jd, int $gregflag = 1): array {
    $u0 = $jd + 32082.5;
    if ($gregflag == 1) { // SE_GREG_CAL
        $u1 = $u0 + floor($u0/36525.0) - floor($u0/146100.0) - 38.0;
        if ($jd >= 1830691.5) $u1 += 1;
        $u0 = $u0 + floor($u1/36525.0) - floor($u1/146100.0) - 38.0;
    }
    $u2 = floor($u0 + 123.0);
    $u3 = floor(($u2 - 122.2) / 365.25);
    $u4 = floor(($u2 - floor(365.25 * $u3)) / 30.6001);
    $jmon = (int)($u4 - 1.0);
    if ($jmon > 12) $jmon -= 12;
    $jday = (int)($u2 - floor(365.25 * $u3) - floor(30.6001 * $u4));
    $jyear = (int)($u3 + floor(($u4 - 2.0) / 12.0) - 4800);
    $jut = ($jd - floor($jd + 0.5) + 0.5) * 24.0;

    return [$jyear, $jmon, $jday, $jut];
}

echo "Testing revjul() for 1 BC:\n";
echo "==========================\n\n";

list($year, $month, $day, $ut) = revjul_simple($jd);
printf("JD %.1f -> Date: %d-%02d-%02d %.2f UT\n", $jd, $year, $month, $day, $ut);
echo "Expected: 0-01-01 12.00 UT (year 0 = 1 BC)\n\n";

// Test file generation
$century = intval($year / 100);
$file_century = intval($century / 6) * 6;
printf("Century: %d, File century: %d\n", $century, $file_century);
printf("Expected filename: sepl_%s%02d.se1\n", $file_century < 0 ? 'm' : '', abs($file_century));
