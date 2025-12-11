<?php
/**
 * Test suite for centisec utility functions
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "=== Centisec Utility Functions Test ===\n\n";

// Test 1: swe_csnorm - normalize to [0..360[
echo "--- Test 1: swe_csnorm (normalize to [0..360°[) ---\n";
$tests = [
    [0, 0],
    [360000 * 360, 0],  // 360° → 0°
    [360000 * 450, 360000 * 90],  // 450° → 90°
    [-360000 * 90, 360000 * 270],  // -90° → 270°
    [360000 * 720, 0],  // 720° → 0°
];

foreach ($tests as [$input, $expected]) {
    $result = swe_csnorm($input);
    $ok = ($result === $expected) ? '✓' : '✗';
    $inputDeg = $input / 360000;
    $resultDeg = $result / 360000;
    $expectedDeg = $expected / 360000;
    echo sprintf("  %s %.2f° → %.2f° (expected %.2f°)\n", $ok, $inputDeg, $resultDeg, $expectedDeg);
}
echo "\n";

// Test 2: swe_difcsn - difference [0..360[
echo "--- Test 2: swe_difcsn (difference in [0..360°[) ---\n";
$tests = [
    [360000 * 90, 360000 * 45, 360000 * 45],  // 90° - 45° = 45°
    [360000 * 45, 360000 * 90, 360000 * 315],  // 45° - 90° = -45° → 315°
    [360000 * 10, 360000 * 350, 360000 * 20],  // 10° - 350° = -340° → 20°
];

foreach ($tests as [$p1, $p2, $expected]) {
    $result = swe_difcsn($p1, $p2);
    $ok = ($result === $expected) ? '✓' : '✗';
    echo sprintf("  %s %.0f° - %.0f° = %.0f° (expected %.0f°)\n",
        $ok, $p1/360000, $p2/360000, $result/360000, $expected/360000);
}
echo "\n";

// Test 3: swe_difcs2n - difference [-180..180[
echo "--- Test 3: swe_difcs2n (difference in [-180..180°[) ---\n";
$tests = [
    [360000 * 90, 360000 * 45, 360000 * 45],  // 90° - 45° = 45°
    [360000 * 45, 360000 * 90, -360000 * 45],  // 45° - 90° = -45°
    [360000 * 10, 360000 * 350, 360000 * 20],  // 10° - 350° = 20° (shortest)
    [360000 * 350, 360000 * 10, -360000 * 20],  // 350° - 10° = -20° (shortest)
];

foreach ($tests as [$p1, $p2, $expected]) {
    $result = swe_difcs2n($p1, $p2);
    $ok = ($result === $expected) ? '✓' : '✗';
    echo sprintf("  %s %.0f° - %.0f° = %+.0f° (expected %+.0f°)\n",
        $ok, $p1/360000, $p2/360000, $result/360000, $expected/360000);
}
echo "\n";

// Test 4: swe_difdeg2n - same but in degrees
echo "--- Test 4: swe_difdeg2n (degrees, [-180..180°[) ---\n";
$tests = [
    [90.0, 45.0, 45.0],
    [45.0, 90.0, -45.0],
    [10.0, 350.0, 20.0],  // Shortest path
    [350.0, 10.0, -20.0],
];

foreach ($tests as [$p1, $p2, $expected]) {
    $result = swe_difdeg2n($p1, $p2);
    $ok = (abs($result - $expected) < 0.0001) ? '✓' : '✗';
    echo sprintf("  %s %.1f° - %.1f° = %+.1f° (expected %+.1f°)\n",
        $ok, $p1, $p2, $result, $expected);
}
echo "\n";

// Test 5: swe_difrad2n - in radians
echo "--- Test 5: swe_difrad2n (radians, [-π..π[) ---\n";
$pi = M_PI;
$tests = [
    [$pi/2, $pi/4, $pi/4],  // 90° - 45° = 45°
    [$pi/4, $pi/2, -$pi/4],  // 45° - 90° = -45°
    [0.1, 2*$pi - 0.1, 0.2],  // Shortest path
];

foreach ($tests as [$p1, $p2, $expected]) {
    $result = swe_difrad2n($p1, $p2);
    $ok = (abs($result - $expected) < 0.0001) ? '✓' : '✗';
    echo sprintf("  %s %.4f - %.4f = %+.4f (expected %+.4f)\n",
        $ok, $p1, $p2, $result, $expected);
}
echo "\n";

// Test 6: swe_csroundsec - rounding to seconds
echo "--- Test 6: swe_csroundsec (round to seconds) ---\n";
// Note: 1° = 360000 centisec, 1' = 6000 centisec, 1" = 100 centisec
$tests = [
    [360000 * 29 + 6000 * 59 + 59 * 100 + 40, 360000 * 29 + 6000 * 59 + 59 * 100],  // 29°59'59.40" → 29°59'59" (round down at sign boundary)
    [360000 * 29 + 6000 * 59 + 59 * 100 + 60, 360000 * 29 + 6000 * 59 + 59 * 100],  // 29°59'59.60" → 29°59'59" (special case)
    [360000 * 15 + 6000 * 30 + 45 * 100 + 49, 360000 * 15 + 6000 * 30 + 45 * 100],  // 15°30'45.49" → 15°30'45"
    [360000 * 15 + 6000 * 30 + 45 * 100 + 50, 360000 * 15 + 6000 * 30 + 46 * 100],  // 15°30'45.50" → 15°30'46"
];

foreach ($tests as [$input, $expected]) {
    $result = swe_csroundsec($input);
    $ok = ($result === $expected) ? '✓' : '✗';

    // Convert to DMS for display
    $in_sec = intdiv($input, 100);
    $in_d = intdiv($in_sec, 3600);
    $in_m = intdiv($in_sec % 3600, 60);
    $in_s = $in_sec % 60;
    $in_cs = $input % 100;

    $out_sec = intdiv($result, 100);
    $out_d = intdiv($out_sec, 3600);
    $out_m = intdiv($out_sec % 3600, 60);
    $out_s = $out_sec % 60;

    echo sprintf("  %s %d°%02d'%02d.%02d\" → %d°%02d'%02d\"\n",
        $ok, $in_d, $in_m, $in_s, $in_cs, $out_d, $out_m, $out_s);
}
echo "\n";

// Test 7: swe_cs2timestr - time formatting
echo "--- Test 7: swe_cs2timestr (time string HH:MM:SS) ---\n";
// Note: For time - 1h = 3600 seconds = 360000 centisec, 1m = 60s = 6000 centisec, 1s = 100 centisec
$tests = [
    [360000 * 12 + 6000 * 30 + 45 * 100, '12:30:45'],  // 12:30:45
    [360000 * 8 + 6000 * 5, '08:05:00'],  // 08:05:00
    [360000 * 23 + 6000 * 59 + 59 * 100, '23:59:59'],  // 23:59:59
];

foreach ($tests as [$input, $expected]) {
    $result = swe_cs2timestr($input);
    $ok = ($result === $expected) ? '✓' : '✗';
    echo sprintf("  %s %s (expected %s)\n", $ok, $result, $expected);
}

// Test suppress zero
$result = swe_cs2timestr(360000 * 12 + 6000 * 30, 58, true);
echo sprintf("  %s %s (suppress zero: expected 12:30)\n",
    $result === '12:30' ? '✓' : '✗', $result);
echo "\n";

// Test 8: swe_cs2lonlatstr - longitude/latitude
echo "--- Test 8: swe_cs2lonlatstr (lon/lat format) ---\n";
$tests = [
    [360000 * 45 + 6000 * 30 + 15 * 100, 'E', 'W', '45E30\'15"'],  // 45°30'15" E
    [-(360000 * 12 + 6000 * 10), 'E', 'W', '12W10\''],  // -12°10' = 12W10'
    [360000 * 180, 'N', 'S', '180N00\''],  // 180°N
];

foreach ($tests as [$input, $pchar, $mchar, $expected]) {
    $result = swe_cs2lonlatstr($input, $pchar, $mchar);
    $ok = ($result === $expected) ? '✓' : '✗';
    echo sprintf("  %s %s (expected %s)\n", $ok, $result, $expected);
}
echo "\n";

// Test 9: swe_cs2degstr - zodiac degree format
echo "--- Test 9: swe_cs2degstr (zodiac degree DD°mm'ss\") ---\n";
$tests = [
    [360000 * 15 + 6000 * 30 + 45 * 100, "15°30'45\""],  // 15°30'45"
    [360000 * 0 + 6000 * 0 + 0 * 100, " 0°00'00\""],  // 0°0'0"
    [360000 * 29 + 6000 * 59 + 59 * 100, "29°59'59\""],  // 29°59'59" (last in sign)
];

foreach ($tests as [$input, $expected]) {
    $result = swe_cs2degstr($input);
    $ok = ($result === $expected) ? '✓' : '✗';
    echo sprintf("  %s %s (expected %s)\n", $ok, $result, $expected);
}
echo "\n";

echo "=== All centisec tests completed ===\n";
