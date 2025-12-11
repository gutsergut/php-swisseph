<?php

require __DIR__ . '/bootstrap.php';
use Swisseph\Constants;

$refFile = __DIR__ . '/jupiter_refs.json';
if (!is_file($refFile)) {
    fwrite(STDERR, "Missing reference file: jupiter_refs.json (run tests/generate_planet_refs.php 5)\n");
    exit(2);
}

$data = json_decode(file_get_contents($refFile), true);
$cases = $data['refs'] ?? [];

$tolAngle = 0.01; // deg
$tolDist = 2e-5;  // AU

$failed = false;
foreach ($cases as $c) {
    // Ecliptic
    $xx = [];
    $serr = null;
    $ret = swe_calc($c['jd'], Constants::SE_JUPITER, 0 | Constants::SEFLG_SPEED, $xx, $serr);
    if ($ret < 0) {
        fwrite(STDERR, "Jupiter ecliptic error jd={$c['jd']} serr=$serr\n");
        $failed = true;
        continue;
    }

    [$lon, $lat, $r] = $xx;
    $dLon = abs($lon - $c['ecl']['lon']);
    $dLat = abs($lat - $c['ecl']['lat']);
    $dR = abs($r - $c['ecl']['r']);
    if ($dLon > $tolAngle || $dLat > $tolAngle || $dR > $tolDist) {
        $msg = sprintf(
            'ECL mismatch jd=%.1f lon %.5f vs %.5f (Δ=%.5f), ' .
            'lat %.5f vs %.5f (Δ=%.5f), r %.9f vs %.9f (Δ=%.9f)' . PHP_EOL,
            $c['jd'],
            $lon,
            $c['ecl']['lon'],
            $dLon,
            $lat,
            $c['ecl']['lat'],
            $dLat,
            $r,
            $c['ecl']['r'],
            $dR
        );
        fwrite(STDERR, $msg);
        $failed = true;
    }

    // Equatorial
    $xx = [];
    $serr = null;
    $ret = swe_calc($c['jd'], Constants::SE_JUPITER, Constants::SEFLG_EQUATORIAL | Constants::SEFLG_SPEED, $xx, $serr);
    if ($ret < 0) {
        fwrite(STDERR, "Jupiter equatorial error jd={$c['jd']} serr=$serr\n");
        $failed = true;
        continue;
    }

    [$ra, $dec, $r2] = $xx;
    $dRa = abs($ra - $c['equ']['ra']);
    $dDec = abs($dec - $c['equ']['dec']);
    $dR2 = abs($r2 - $c['equ']['r']);
    if ($dRa > $tolAngle || $dDec > $tolAngle || $dR2 > $tolDist) {
        $msg = sprintf(
            'EQ mismatch jd=%.1f RA %.5f vs %.5f (Δ=%.5f), ' .
            'Dec %.5f vs %.5f (Δ=%.5f), r %.9f vs %.9f (Δ=%.9f)' . PHP_EOL,
            $c['jd'],
            $ra,
            $c['equ']['ra'],
            $dRa,
            $dec,
            $c['equ']['dec'],
            $dDec,
            $r2,
            $c['equ']['r'],
            $dR2
        );
        fwrite(STDERR, $msg);
        $failed = true;
    }
}

if ($failed) {
    exit(1);
}

echo "Jupiter accuracy OK\n";
