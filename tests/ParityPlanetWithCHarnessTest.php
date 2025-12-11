<?php

require __DIR__ . '/bootstrap.php';
use Swisseph\Constants;

$harness = __DIR__ . '/planet_harness.exe';
if (!is_file($harness)) {
    fwrite(STDERR, "planet_harness.exe not found. Build it first (see comment header in planet_harness.c)\n");
    exit(2);
}

$ephe = realpath(__DIR__ . '/../../eph/ephe');
if ($ephe === false) {
    fwrite(STDERR, "Ephemeris path not found\n");
    exit(3);
}

$json = shell_exec('"' . $harness . '" ' . escapeshellarg($ephe));
if (!$json) {
    fwrite(STDERR, "harness execution failed\n");
    exit(4);
}

$payload = json_decode($json, true);
if (!is_array($payload)) {
    fwrite(STDERR, "invalid JSON from harness\n");
    echo $json, "\n";
    exit(5);
}

$tolAngle = 0.01; // deg
$tolDist = 2e-5;  // AU

$failed = false;

foreach ([Constants::SE_JUPITER => 'jupiter', Constants::SE_SATURN => 'saturn'] as $ipl => $name) {
    $cases = $payload['planets'][$name] ?? [];
    foreach ($cases as $c) {
        $jd = $c['jd'];
        // Ecliptic
        $xx = [];
        $serr = null;
        $ret = swe_calc($jd, $ipl, 0 | Constants::SEFLG_SPEED, $xx, $serr);
        if ($ret < 0) {
            fwrite(STDERR, "PHP ecliptic error jd={$jd} serr=$serr\n");
            $failed = true;
            continue;
        }

        [$lon, $lat, $r] = $xx;
        $dLon = abs($lon - $c['ecl']['lon']);
        $dLat = abs($lat - $c['ecl']['lat']);
        $dR = abs($r - $c['ecl']['r']);
        if ($dLon > $tolAngle || $dLat > $tolAngle || $dR > $tolDist) {
            $msg = sprintf(
                '%s ECL jd=%.1f Δlon=%.5f Δlat=%.5f Δr=%.9f' . PHP_EOL,
                strtoupper($name),
                $jd,
                $dLon,
                $dLat,
                $dR
            );
            fwrite(STDERR, $msg);
            $failed = true;
        }

        // Equatorial
        $xx = [];
        $serr = null;
        $ret = swe_calc($jd, $ipl, Constants::SEFLG_EQUATORIAL | Constants::SEFLG_SPEED, $xx, $serr);
        if ($ret < 0) {
            fwrite(STDERR, "PHP equatorial error jd={$jd} serr=$serr\n");
            $failed = true;
            continue;
        }

        [$ra, $dec, $r2] = $xx;
        $dRa = abs($ra - $c['equ']['ra']);
        $dDec = abs($dec - $c['equ']['dec']);
        $dR2 = abs($r2 - $c['equ']['r']);
        if ($dRa > $tolAngle || $dDec > $tolAngle || $dR2 > $tolDist) {
            $msg = sprintf(
                '%s EQ jd=%.1f ΔRA=%.5f ΔDec=%.5f Δr=%.9f' . PHP_EOL,
                strtoupper($name),
                $jd,
                $dRa,
                $dDec,
                $dR2
            );
            fwrite(STDERR, $msg);
            $failed = true;
        }
    }
}

if ($failed) {
    exit(1);
}

echo "C harness parity OK (Jupiter, Saturn)\n";
