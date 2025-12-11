<?php

require __DIR__ . '/bootstrap.php';

// This script calls swetest64.exe to produce geocentric apparent Saturn RA/Dec and distance
// for a set of dates/times and writes references into JSON for use in tests.

use Swisseph\Constants;

$swetest = realpath(__DIR__ . '/../tests/swetest64.exe');
if ($swetest === false || !is_file($swetest)) {
    fwrite(STDERR, "swetest64.exe not found in tests folder\n");
    exit(2);
}

$ephe = realpath(__DIR__ . '/../../eph/ephe');
if ($ephe === false || !is_dir($ephe)) {
    fwrite(STDERR, "Ephemeris path not found: " . (__DIR__ . '/../../eph/ephe') . "\n");
    exit(3);
}

// Cases: JD(TT) list — we convert to UT using swe_deltat
$cases = [
    2451545.0,  // J2000
    2453000.5,
    2448000.5,
    2460000.5,
];

// Helper: Convert JD(TT) → calendar date/time UT for swetest (-bD.M.Y -utHH:MM:SS)
function jdtt_to_utc_parts(float $jd_tt): array {
    // Iterate UT from TT: jd_ut = jd_tt - ΔT(jd_ut)
    $jd_ut = $jd_tt; // initial guess
    for ($k=0; $k<2; $k++) {
        $dt = swe_deltat($jd_ut); // days
        $jd_ut = $jd_tt - $dt;
    }
    [$y, $m, $d, $hour, $min, $sec] = swe_jd_to_utc($jd_ut, 1);
    // swetest prefers non-padded day/month
    $date = sprintf('%d.%d.%d', $d, $m, $y);
    $time = sprintf('%02d:%02d:%02d', $hour, $min, (int)round($sec));
    return [$date, $time];
}

// Run swetest and parse a single line (equatorial)
function run_swetest_equ(string $swetest, string $ephe, string $date, string $time): array {
    // -p6 Saturn, -fPTADR: P=name, T=date, A=RA, D=Dec, R=distance (geocentric)
    // (No -equ flag; RA/Dec included by format spec)
    $cmd = sprintf('cmd /c ""%s" -b%s -ut%s -p6 -fPTADR -head -eswe -edir%s"',
        $swetest, $date, $time, $ephe);
    $out = shell_exec($cmd);
    if (!is_string($out) || trim($out) === '') {
        throw new RuntimeException('swetest returned empty output');
    }
    // Expect: something like ".. 0h.. RA .. Dec .. R"
    // We'll capture RA (h m s), Dec (d m s with sign), and R
    $norm = preg_replace('/\s+/', ' ', trim($out));
    // Match RA h m s, Dec d m s, and trailing distance float
    if (!preg_match('/(\d+)h\s*(\d+)' . "'" . '\s*(\d+(?:\.\d+)?)\s+([\-+]?\d+)°\s*(\d+)' . "'" . '\s*(\d+(?:\.\d+)?)\s+(\d+\.\d+)/', $norm, $m)) {
        throw new RuntimeException('failed to parse swetest output: ' . $out);
    }
    $rah = (int)$m[1]; $ram = (int)$m[2]; $ras = (float)$m[3];
    $sign = strpos($m[4], '-') === 0 ? -1 : 1; $decdeg = abs((int)$m[4]); $decm = (int)$m[5]; $decs = (float)$m[6];
    $r = (float)$m[7];
    $ra_deg = ($rah + $ram/60.0 + $ras/3600.0) * 15.0;
    $dec_deg = $sign * ($decdeg + $decm/60.0 + $decs/3600.0);
    return ['ra' => $ra_deg, 'dec' => $dec_deg, 'r' => $r];
}

// Run swetest for ecliptic
function run_swetest_ecl(string $swetest, string $ephe, string $date, string $time): array {
    $cmd = sprintf('cmd /c ""%s" -b%s -ut%s -p6 -fLBR -head -eswe -edir%s"',
        $swetest, $date, $time, $ephe);
    $out = shell_exec($cmd);
    if (!is_string($out) || trim($out) === '') {
        throw new RuntimeException('swetest returned empty output');
    }
    // Capture Lon° m' s" Lat° m' s" Dist
    $norm = preg_replace('/\s+/', ' ', trim($out));
    if (!preg_match('/([\-+]?\d+)°\s*(\d+)' . "'" . '\s*(\d+(?:\.\d+)?)\s+([\-+]?\d+)°\s*(\d+)' . "'" . '\s*(\d+(?:\.\d+)?)\s+(\d+\.\d+)/', $norm, $m)) {
        throw new RuntimeException('failed to parse ecliptic output: ' . $out);
    }
    $lon = ((int)$m[1]) + ((int)$m[2])/60.0 + ((float)$m[3])/3600.0; if ($lon < 0) $lon = -$lon;
    $latsign = strpos($m[4], '-') === 0 ? -1 : 1; $latdeg = abs((int)$m[4]); $latmin = (int)$m[5]; $latsec = (float)$m[6];
    $lat = $latsign * ($latdeg + $latmin/60.0 + $latsec/3600.0);
    $r = (float)$m[7];
    return ['lon' => $lon, 'lat' => $lat, 'r' => $r];
}

$refs = [];
foreach ($cases as $jd) {
    [$date, $time] = jdtt_to_utc_parts($jd);
    $eq = run_swetest_equ($swetest, $ephe, $date, $time);
    $ec = run_swetest_ecl($swetest, $ephe, $date, $time);
    $refs[] = [
        'jd' => $jd,
        'equ' => $eq,
        'ecl' => $ec,
    ];
}

$outFile = __DIR__ . '/saturn_refs.json';
file_put_contents($outFile, json_encode(['refs' => $refs], JSON_PRETTY_PRINT));
echo "Written: $outFile\n";
