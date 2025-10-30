<?php

require_once __DIR__ . '/vendor/autoload.php';

use Swisseph\SwephFile\FilenameGenerator;
use Swisseph\SwephFile\SwephConstants;

echo "Testing FilenameGenerator (port of C swi_gen_filename)\n";
echo "======================================================\n\n";

// Test cases matching C behavior
$tests = [
    // [Julian Day, Planet Index, Expected Filename]
    [2451545.0, SwephConstants::SEI_JUPITER, 'sepl_18.se1'],  // J2000 = 2000 AD → 1800-2399 century
    [2451545.0, SwephConstants::SEI_SUNBARY, 'sepl_18.se1'],  // Sun barycenter uses planet file
    [2451545.0, SwephConstants::SEI_MOON, 'semo_18.se1'],     // Moon has separate file
    [2451545.0, SwephConstants::SEI_CERES, 'seas_18.se1'],    // Ceres uses asteroid file
    [2415020.5, SwephConstants::SEI_JUPITER, 'sepl_18.se1'],  // 1900 AD
    [2305447.5, SwephConstants::SEI_JUPITER, 'sepl_12.se1'],  // 1600 AD
    [1721425.5, SwephConstants::SEI_JUPITER, 'sepl_00.se1'],  // 1 AD (year 1)
    [1721060.0, SwephConstants::SEI_JUPITER, 'sepl_00.se1'],  // 1 BC (year 0 astronomical)
    [1684900.5, SwephConstants::SEI_JUPITER, 'seplm06.se1'],  // 100 BC (year -99)
];

echo "Testing planet file generation:\n";
foreach ($tests as [$jd, $ipli, $expected]) {
    $result = FilenameGenerator::generate($jd, $ipli);
    $status = $result === $expected ? '✓' : '✗';
    $planetName = array_search($ipli, (new \ReflectionClass(SwephConstants::class))->getConstants());
    printf("  %s JD %.1f, %s → %s (expected: %s)\n",
        $status, $jd, $planetName, $result, $expected);
}

echo "\nTesting asteroid file generation:\n";
$asteroidTests = [
    [433, 'ast0/se00433.se1'],   // Eros
    [1, 'ast0/se00001.se1'],      // Ceres (but as numbered asteroid)
    [1234, 'ast1/se01234.se1'],   // Asteroid 1234
    [99999, 'ast99/se99999.se1'], // Last 5-digit
    [100000, 'ast100/s100000.se1'], // First 6-digit (note: 's' prefix, not 'se')
];

foreach ($asteroidTests as [$astNum, $expected]) {
    $ipli = \Swisseph\Constants::SE_AST_OFFSET + $astNum;
    $result = FilenameGenerator::generate(2451545.0, $ipli);
    $status = $result === $expected ? '✓' : '✗';
    printf("  %s Asteroid %d → %s (expected: %s)\n",
        $status, $astNum, $result, $expected);
}

echo "\nTesting planetary moons:\n";
$moonNum = \Swisseph\Constants::SE_PLMOON_OFFSET + 1;
$result = FilenameGenerator::generate(2451545.0, $moonNum);
printf("  Planetary moon %d → %s\n", $moonNum, $result);

echo "\n✓ FilenameGenerator tests completed!\n";
