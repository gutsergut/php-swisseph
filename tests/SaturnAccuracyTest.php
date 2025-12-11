<?php

require __DIR__ . '/bootstrap.php';
use Swisseph\Constants;

// Simple accuracy harness for Saturn comparing against swetest64.exe reference values.
// We will query swetest externally later; for now embed placeholder expected values to be replaced.
// Structure: array of test cases with jd_tt and expected ecliptic/equatorial values.
// Replace EXPECT_* constants with actual swetest outputs for final validation.

// Load swetest references
$refFile = __DIR__ . '/saturn_refs.json';
if (!is_file($refFile)) {
    fwrite(STDERR, "Missing reference file: saturn_refs.json (run tests/generate_saturn_refs.php)\n");
    exit(2);
}
$data = json_decode(file_get_contents($refFile), true);
$cases = $data['refs'] ?? [];

$failed = false;
// Tolerances
$tolAngle = 0.01; // deg
$tolDist = 2e-5;  // AU absolute (≈ 3,000 km)

foreach ($cases as $c) {
    $xx = []; $serr = null;
    $ret = swe_calc($c['jd'], Constants::SE_SATURN, 0 | Constants::SEFLG_SPEED, $xx, $serr);
    if ($ret < 0) {
        fwrite(STDERR, "Saturn ecliptic calc error jd={$c['jd']} serr=$serr\n");
        $failed = true; continue;
    }
    [$lon, $lat, $dist, $dLon, $dLat, $dR] = $xx;
    $dLonAbs = abs($lon - $c['ecl']['lon']);
    $dLatAbs = abs($lat - $c['ecl']['lat']);
    $dDistAbs = abs($dist - $c['ecl']['r']);
    if ($dLonAbs > $tolAngle || $dLatAbs > $tolAngle || $dDistAbs > $tolDist) {
        fwrite(STDERR, sprintf("ECL mismatch jd=%.1f lon=%.5f vs %.5f (Δ=%.5f) lat=%.5f vs %.5f (Δ=%.5f) r=%.9f vs %.9f (Δ=%.9f)\n",
            $c['jd'], $lon, $c['ecl']['lon'], $dLonAbs, $lat, $c['ecl']['lat'], $dLatAbs, $dist, $c['ecl']['r'], $dDistAbs));
        $failed = true;
    }
    // Equatorial test
    $xxEq = []; $serrEq = null;
    $retEq = swe_calc($c['jd'], Constants::SE_SATURN, Constants::SEFLG_EQUATORIAL | Constants::SEFLG_SPEED, $xxEq, $serrEq);
    if ($retEq < 0) {
        fwrite(STDERR, "Saturn equatorial calc error jd={$c['jd']} serr=$serrEq\n");
        $failed = true; continue;
    }
    [$raDeg, $decDeg, $rEq, $dRa, $dDec, $dR2] = $xxEq;
    $dRaAbs = abs($raDeg - $c['equ']['ra']);
    $dDecAbs = abs($decDeg - $c['equ']['dec']);
    $dRAbs = abs($rEq - $c['equ']['r']);
    if ($dRaAbs > $tolAngle || $dDecAbs > $tolAngle || $dRAbs > $tolDist) {
        fwrite(STDERR, sprintf("EQ mismatch jd=%.1f RA=%.5f vs %.5f (Δ=%.5f) Dec=%.5f vs %.5f (Δ=%.5f) r=%.9f vs %.9f (Δ=%.9f)\n",
            $c['jd'], $raDeg, $c['equ']['ra'], $dRaAbs, $decDeg, $c['equ']['dec'], $dDecAbs, $rEq, $c['equ']['r'], $dRAbs));
        $failed = true;
    }
}

if ($failed) { exit(1); }

echo "Saturn accuracy harness (placeholder refs) OK\n";
