<?php
/**
 * Debug JPL ipt values
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Swe\Jpl\JplEphemeris;
use Swisseph\Swe\Jpl\JplConstants;

// Access private properties via reflection
$jpl = JplEphemeris::getInstance();

$ss = [];
$serr = null;
$result = $jpl->open($ss, 'de406e.eph',
    'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/data/ephemerides/jpl',
    $serr);

if ($result !== JplConstants::OK) {
    echo "ERROR: $serr\n";
    exit(1);
}

// Use reflection to access private properties
$ref = new ReflectionClass($jpl);

$ipt = $ref->getProperty('ehIpt');
$ipt->setAccessible(true);
$iptVal = $ipt->getValue($jpl);

$ncoeffs = $ref->getProperty('ncoeffs');
$ncoeffs->setAccessible(true);
$ncoeffsVal = $ncoeffs->getValue($jpl);

$irecsz = $ref->getProperty('irecsz');
$irecsz->setAccessible(true);
$irecszVal = $irecsz->getValue($jpl);

echo "ncoeffs = $ncoeffsVal\n";
echo "irecsz = $irecszVal bytes\n\n";

$bodyNames = [
    'Mercury', 'Venus', 'EMB', 'Mars', 'Jupiter',
    'Saturn', 'Uranus', 'Neptune', 'Pluto', 'Moon',
    'SunBary', 'Nut', 'Libr'
];

echo "IPT values (ptr, ncf, na):\n";
for ($i = 0; $i < 13; $i++) {
    $ptr = $iptVal[$i * 3];
    $ncf = $iptVal[$i * 3 + 1];
    $na = $iptVal[$i * 3 + 2];
    $ncm = ($i == 11) ? 2 : 3;  // nutation has 2 components
    $nbytes = $ncf * $ncm * $na * 8;
    printf("  %8s: ptr=%4d, ncf=%2d, na=%2d, ncm=%d => %4d doubles\n",
        $bodyNames[$i], $ptr, $ncf, $na, $ncm, $ncf * $ncm * $na);
}

echo "\nVerifying that pointers are consecutive:\n";
for ($i = 0; $i < 12; $i++) {
    $ptr = $iptVal[$i * 3];
    $ncf = $iptVal[$i * 3 + 1];
    $na = $iptVal[$i * 3 + 2];
    $ncm = ($i == 11) ? 2 : 3;
    $expectedNext = $ptr + $ncf * $ncm * $na;
    $actualNext = $iptVal[($i + 1) * 3];
    $ok = ($expectedNext == $actualNext) ? "OK" : "MISMATCH! expected $expectedNext";
    printf("  After %8s: end=%4d, next ptr=%4d : %s\n",
        $bodyNames[$i], $expectedNext, $actualNext, $ok);
}

$jpl->close();
