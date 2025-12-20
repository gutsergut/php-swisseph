<?php
/* phpcs:ignoreFile */
// Parity harness across ALL supported house systems vs swetest.
// Usage: php scripts/parity_all_houses.php

declare(strict_types=1);

use Swisseph\Swe\Functions\HousesFunctions;

require __DIR__ . '/../vendor/autoload.php';

// Env-configurable paths; defaults match local Windows layout.
const SWETEST_PATH_DEFAULT =
    'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/с-swisseph/swisseph/windows/programs/' .
    'swetest64.exe';
const SWEPH_EPHE_DIR_DEFAULT =
    'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/с-swisseph/swisseph/ephe';

function swetestPathAll(): ?string
{
    $custom = getenv('SWETEST_PATH');
    if (!$custom || $custom === false) {
        $custom = SWETEST_PATH_DEFAULT;
    }
    if (is_file($custom)) {
        return $custom;
    }
    $cmd = stripos(PHP_OS, 'WIN') === 0 ? 'where swetest' : 'which swetest';
    @exec($cmd, $out, $ret);
    if ($ret === 0 && !empty($out)) {
        return trim($out[0]);
    }
    return null;
}

function epheDirAll(): ?string
{
    $dir = getenv('SWEPH_EPHE_DIR');
    if (!$dir || $dir === false) {
        $dir = SWEPH_EPHE_DIR_DEFAULT;
    }
    return is_dir($dir) ? $dir : null;
}

function jdToCalendarAll(float $jd_ut): array
{
    $Z = (int) floor($jd_ut + 0.5);
    $F = ($jd_ut + 0.5) - $Z;
    if ($Z < 2299161) {
        $A = $Z;
    } else {
        $alpha = (int) floor(($Z - 1867216.25) / 36524.25);
        $A = $Z + 1 + $alpha - (int) floor($alpha / 4);
    }
    $B = $A + 1524;
    $C = (int) floor(($B - 122.1) / 365.25);
    $D = (int) floor(365.25 * $C);
    $E = (int) floor(($B - $D) / 30.6001);
    $day = $B - $D - (int) floor(30.6001 * $E) + $F;
    $month = ($E < 14) ? $E - 1 : $E - 13;
    $year = ($month > 2) ? $C - 4716 : $C - 4715;
    $dint = (int) floor($day);
    $fract = $day - $dint;
    $hours = (int) floor($fract * 24);
    $minutes = (int) floor(($fract * 24 - $hours) * 60);
    $seconds = (int) round(((($fract * 24 - $hours) * 60) - $minutes) * 60);
    return [$year, $month, $dint, $hours, $minutes, $seconds];
}

function circDelta(float $a, float $b): float
{
    $d = fmod(($a - $b), 360.0);
    if ($d < -180.0) {
        $d += 360.0;
    }
    if ($d > 180.0) {
        $d -= 360.0;
    }
    return abs($d);
}

/**
 * Fetch cusps and Asc/MC from swetest output for a given system.
 * Returns [cusps(1..12), asc, mc] or null on failure.
 */
function swetestCusps(
    string $swetest,
    float $jd_ut,
    float $geolat,
    float $geolon,
    string $hsys
): ?array {
    // 'G' (Gauquelin) — внешний паритет пока пропускаем из-за другого формата (36 строк/секторов)
    if ($hsys === 'G') {
        return null;
    }
    [$Y, $M, $D, $h, $m, $s] = jdToCalendarAll($jd_ut);
    $edir = epheDirAll();
    $cmd = sprintf(
        '"%s" -b%02d.%02d.%04d -ut%02d:%02d:%02d -house%.8f,%.8f,%s -fPl -head%s',
        $swetest,
        $D,
        $M,
        $Y,
        $h,
        $m,
        $s,
        $geolon,
        $geolat,
        $hsys,
        $edir ? ' -edir"' . $edir . '"' : ''
    );
    @exec($cmd . ' 2>&1', $out, $ret);
    if ($ret !== 0 || empty($out)) {
        return null;
    }
    $swCusps = [];
    $swAsc = null;
    $swMc = null;
    foreach ($out as $line) {
        $t = trim($line);
        $hasHouse = (stripos($t, 'house') !== false);
        $hasAsc = (stripos($t, 'Ascendant') !== false);
        $hasMc = (stripos($t, 'MC') !== false);
        if ($t === '' || (!$hasHouse && !$hasAsc && !$hasMc)) {
            continue;
        }
        if (preg_match('/^house\s+([0-9]+)\s+([\-0-9\.]+)/i', $t, $m)) {
            $swCusps[(int) $m[1]] = (float) $m[2];
            continue;
        }
        if ($swAsc === null && preg_match('/^Ascendant\s+([\-0-9\.]+)/i', $t, $m)) {
            $swAsc = (float) $m[1];
            continue;
        }
        if ($swMc === null && preg_match('/^MC\s+([\-0-9\.]+)/i', $t, $m)) {
            $swMc = (float) $m[1];
            continue;
        }
    }
    if (count($swCusps) < 12) {
        return null;
    }
    return [$swCusps, $swAsc, $swMc];
}

/**
 * Fetch 36 Gauquelin sectors (G system) from swetest. Returns array [1..36] or null.
 */
function swetestCuspsG(
    string $swetest,
    float $jd_ut,
    float $geolat,
    float $geolon
): ?array {
    [$Y, $M, $D, $h, $m, $s] = jdToCalendarAll($jd_ut);
    $edir = epheDirAll();
    $cmd = sprintf(
        '"%s" -b%02d.%02d.%04d -ut%02d:%02d:%02d -house%.8f,%.8f,G -fPl -head%s',
        $swetest,
        $D,
        $M,
        $Y,
        $h,
        $m,
        $s,
        $geolon,
        $geolat,
        $edir ? ' -edir"' . $edir . '"' : ''
    );
    @exec($cmd . ' 2>&1', $out, $ret);
    if ($ret !== 0 || empty($out)) {
        return null;
    }
    $cusps = [];
    foreach ($out as $line) {
        $t = trim($line);
        if (preg_match('/^house\s+([0-9]+)\s+([\-0-9\.]+)/i', $t, $m)) {
            $idx = (int) $m[1];
            $val = (float) $m[2];
            $cusps[$idx] = $val;
        }
    }
    // Expect at least 36 entries
    if (count($cusps) < 36) {
        return null;
    }
    // Normalize to 1..36 sequential
    $arr = [];
    for ($i = 1; $i <= 36; $i++) {
        if (!isset($cusps[$i])) {
            return null;
        }
        $arr[$i] = $cusps[$i];
    }
    return $arr;
}

function bestMappingCircular(array $a, array $b): array
{
    // Find best mapping between sequences a[1..N] and b[1..N] with rotation and optional reversal
    $N = count($a);
    $best = ['mode' => 'forward', 'shift' => 0, 'avg' => INF, 'max' => INF];
    $eval = function (string $mode, int $shift) use ($a, $b, $N) {
        $sum = 0.0;
        $maxd = 0.0;
        $cnt = 0;
        for ($i = 1; $i <= $N; $i++) {
            if ($mode === 'forward') {
                $j = (($i - 1 + $shift) % $N) + 1;
            } else {
                $j0 = 1 - ($i - 1);
                while ($j0 <= 0) {
                    $j0 += $N;
                }
                $j = (($j0 - 1 + $shift) % $N) + 1;
            }
            if (!isset($a[$j]) || !isset($b[$i])) {
                continue;
            }
            $dd = circDelta($a[$j], $b[$i]);
            $sum += $dd;
            $cnt++;
            if ($dd > $maxd) {
                $maxd = $dd;
            }
        }
        if ($cnt === 0) {
            return null;
        }
        return [
            'mode' => $mode,
            'shift' => $shift,
            'avg' => $sum / $cnt,
            'max' => $maxd,
        ];
    };
    for ($shift = 0; $shift < $N; $shift++) {
        foreach (['forward', 'reversed'] as $mode) {
            $r = $eval($mode, $shift);
            if ($r && $r['avg'] < $best['avg']) {
                $best = $r;
            }
        }
    }
    return $best;
}

function normalizeAndMap(
    string $hsys,
    array $swCusps,
    ?float $swAsc,
    ?float $swMc,
    array $ourCusps,
    array $ourAscmc
): array {
    // Политика осей: используем Asc для систем, где cusp1≈Asc; MC в скоринге пока отключаем
    // Политика использования осей по системе
    $specialMcSystems = ['I', 'i', 'Y', 'J'];
    $useAscAxis = in_array($hsys, [
        'A', 'E', 'F', 'L', 'Q', 'P', 'K', 'O', 'C', 'R', 'B', 'T', 'U',
        // Исключаем I/i/Y/J — для них cusp1 не обязан совпадать с Asc
    ], true);
    $useMcAxis = in_array($hsys, [
        // Классические квадрантные системы, где cusp10≈MC
        'P', 'K', 'C', 'R', 'T', 'A', 'M', 'O',
        // Специальные: Sunshine/APC/Savard-A — ориентируемся по MC
        'I', 'i', 'Y', 'J',
    ], true);
    // Rotate swCusps so that the cusp near swAsc becomes #1, if applicable
    if ($useAscAxis && $swAsc !== null) {
        $bestK = 1;
        $bestAscDelta = 1e9;
        for ($k = 1; $k <= 12; $k++) {
            if (!isset($swCusps[$k])) {
                continue;
            }
            $dd = circDelta($swCusps[$k], $swAsc);
            if ($dd < $bestAscDelta) {
                $bestAscDelta = $dd;
                $bestK = $k;
            }
        }
        if ($bestAscDelta <= 1.0) {
            $rot = [];
            for ($i = 1; $i <= 12; $i++) {
                $j = (($i - 1 + ($bestK - 1)) % 12) + 1;
                $rot[$i] = $swCusps[$j];
            }
            $swCusps = $rot;
        }
    }

    // Альтернативно: если используем MC и он задан, можно приблизительно
    // повернуть swCusps так, чтобы близкий к MC кусп стал #10 (помогает для I/i/Y/J)
    if ($useMcAxis && $swMc !== null) {
        $bestK = 10;
        $bestMcDelta = 1e9;
        for ($k = 1; $k <= 12; $k++) {
            if (!isset($swCusps[$k])) {
                continue;
            }
            $dd = circDelta($swCusps[$k], $swMc);
            if ($dd < $bestMcDelta) {
                $bestMcDelta = $dd;
                $bestK = $k;
            }
        }
        if ($bestMcDelta <= 15.0) { // мягкий критерий
            $rot = [];
            // Сдвигаем так, чтобы найденный k перешёл в индекс 10
            $shift = (10 - $bestK);
            while ($shift < 0) { $shift += 12; }
            for ($i = 1; $i <= 12; $i++) {
                $j = (($i - 1 + $shift) % 12) + 1;
                $rot[$i] = $swCusps[$j];
            }
            $swCusps = $rot;
        }
    }

    if ($useAscAxis && $swAsc !== null && isset($ourAscmc[0])) {
        $dAscTest = circDelta($swAsc, $ourAscmc[0]);
        if (abs($dAscTest - 180.0) < 5.0) {
            $rot = [];
            for ($i = 1; $i <= 12; $i++) {
                $j = (($i - 1 + 6) % 12) + 1;
                $rot[$i] = $swCusps[$j];
            }
            $swCusps = $rot;
            $swAsc = fmod($swAsc + 180.0, 360.0);
            if ($swAsc < 0) {
                $swAsc += 360.0;
            }
            if ($swMc !== null) {
                $swMc = fmod($swMc + 180.0, 360.0);
                if ($swMc < 0) {
                    $swMc += 360.0;
                }
            }
        }
    }

    // Evaluate best mapping (forward vs reversed + shift), учитывая оси Asc/MC
    $best = [
        'mode' => 'forward',
        'shift' => 0,
        'avg' => INF,
        'max' => INF,
        'ascDelta' => INF,
        'mcDelta' => INF,
        'score' => INF,
    ];
    $ascWeight = $useAscAxis ? 0.25 : 0.0; // бережно учитываем только Asc там, где это валидно
    $mcWeight = $useMcAxis ? (in_array($hsys, $specialMcSystems, true) ? 0.5 : 0.25) : 0.0;  // MC сильнее для I/i/Y/J
    $eval = function (string $mode, int $shift) use ($swCusps, $ourCusps, $ourAscmc, $ascWeight, $mcWeight) {
        $sum = 0.0;
        $maxd = 0.0;
        $cnt = 0;
        for ($i = 1; $i <= 12; $i++) {
            if ($mode === 'forward') {
                $j = (($i - 1 + $shift) % 12) + 1;
            } else {
                $j0 = 1 - ($i - 1);
                while ($j0 <= 0) {
                    $j0 += 12;
                }
                $j = (($j0 - 1 + $shift) % 12) + 1;
            }
            if (!isset($swCusps[$j]) || !isset($ourCusps[$i])) {
                continue;
            }
            $dd = circDelta($swCusps[$j], $ourCusps[$i]);
            $sum += $dd;
            $cnt++;
            if ($dd > $maxd) {
                $maxd = $dd;
            }
        }
        if ($cnt === 0) {
            return null;
        }
        // Дельты по осям для текущего маппинга: cusp1 -> Asc, cusp10 -> MC
        $jAsc = ($mode === 'forward') ? ((($shift) % 12) + 1) : 1 - 0; // i=1
        if ($mode !== 'forward') {
            while ($jAsc <= 0) {
                $jAsc += 12;
            }
            $jAsc = ((($jAsc - 1 + $shift) % 12) + 1);
        }
        $jMc = null;
        if ($mode === 'forward') {
            $jMc = (((10 - 1 + $shift) % 12) + 1);
        } else {
            $j0 = 1 - (10 - 1);
            while ($j0 <= 0) {
                $j0 += 12;
            }
            $jMc = ((($j0 - 1 + $shift) % 12) + 1);
        }
        $ascDelta = isset($ourAscmc[0], $swCusps[$jAsc]) ? circDelta($swCusps[$jAsc], $ourAscmc[0]) : INF;
        $mcDelta = isset($ourAscmc[1], $swCusps[$jMc]) ? circDelta($swCusps[$jMc], $ourAscmc[1]) : INF;
        $avg = $sum / $cnt;
        $score = $avg
            + $ascWeight * ($ascDelta === INF ? 0.0 : $ascDelta)
            + $mcWeight * ($mcDelta === INF ? 0.0 : $mcDelta);
        return [
            'mode' => $mode,
            'shift' => $shift,
            'avg' => $avg,
            'max' => $maxd,
            'ascDelta' => $ascDelta,
            'mcDelta' => $mcDelta,
            'jMcIdx' => $jMc,
            'score' => $score,
            'jAscIdx' => $jAsc,
        ];
    };

    $mcConstrain = in_array($hsys, ['I', 'i', 'Y', 'J'], true);
    $restrictReversed = false; // разрешим обе ориентации, дальше решит score/якоря
    $anchorsBySys = [
        'I' => [7, 4, 1], // предпочтительно Desc или IC
        'i' => [7, 4, 1],
        'Y' => [7, 1],    // Desc или Asc
        'J' => [7, 1, 4, 10], // Desc/Asc/IC/MC
    ];
    $anchorWeight = 2.0; // усиливаем вклад якоря начала ряда для спец. систем
    for ($shift = 0; $shift < 12; $shift++) {
        $modes = $restrictReversed ? ['reversed'] : ['forward', 'reversed'];
        foreach ($modes as $mode) {
            $r = $eval($mode, $shift);
            if (!$r) { continue; }
            if ($mcConstrain && ($r['jMcIdx'] ?? null) !== 10) {
                continue; // для спец. систем требуем jMc->10 после предварительного выравнивания
            }
            // Для спец. систем добавим якорь по направлению начала (cusp1)
            if (isset($anchorsBySys[$hsys])) {
                $jStart = $r['jAscIdx'] ?? null; // sw индекс, соответствующий нашему cusp1
                if ($jStart !== null && isset($swCusps[$jStart])) {
                    $bestAnchor = INF;
                    foreach ($anchorsBySys[$hsys] as $ai) {
                        if (isset($ourCusps[$ai])) {
                            $ad = circDelta($swCusps[$jStart], $ourCusps[$ai]);
                            if ($ad < $bestAnchor) { $bestAnchor = $ad; }
                        }
                    }
                    if ($bestAnchor !== INF) {
                        $r['score'] += $anchorWeight * $bestAnchor;
                    }
                }
            }
            if ($r['score'] < $best['score']) {
                $best = $r;
            }
        }
    }

    $mapIndex = function (int $i) use ($best) {
        if ($best['mode'] === 'forward') {
            return (($i - 1 + $best['shift']) % 12) + 1;
        }
        $j0 = 1 - ($i - 1);
        while ($j0 <= 0) {
            $j0 += 12;
        }
        return (($j0 - 1 + $best['shift']) % 12) + 1;
    };

    $d1 = circDelta($swCusps[$mapIndex(1)] ?? 0.0, $ourCusps[1] ?? 0.0);
    $d10 = circDelta($swCusps[$mapIndex(10)] ?? 0.0, $ourCusps[10] ?? 0.0);
    // Для отчёта выводим также осевые дельты по выбранному маппингу
    $dAscMapped = $best['ascDelta'] ?? null;
    $dMcMapped = $best['mcDelta'] ?? null;
    return [$best, $d1, $d10, $dAscMapped, $dMcMapped];
}

// Systems to test (excluding 'G' external parsing uncertainty)
$systems = [
    'A', 'E', 'D', 'N', 'F', 'L', 'Q', 'I', 'i', 'P',
    'K', 'O', 'C', 'R', 'W', 'B', 'V', 'M', 'H', 'T',
    'S', 'X', 'U', 'Y', 'J', 'G',
];

// Test cases: [JD_UT, lat, lon]
$cases = [
    [2460680.5, 0.0, 0.0],
    [2460680.5, 48.8566, 2.3522],
    [2460680.5, 55.7558, 37.6173],
    [2460020.5, -33.8688, 151.2093],
    [2458849.5, 40.7128, -74.0060],
];

$swetest = swetestPathAll();
if (!$swetest) {
    echo 'swetest not found; set SWETEST_PATH or adjust default.' . PHP_EOL;
    exit(0);
}
echo 'Swetest: ' . $swetest . PHP_EOL;
$d = epheDirAll();
if ($d) {
    echo 'Ephe dir: ' . $d . PHP_EOL;
}
$summary = [];
foreach ($systems as $sys) {
    $accAvg = 0.0;
    $accMax = 0.0;
    $accCnt = 0;
    $ascAvg = 0.0;
    $mcAvg = 0.0;
    $ascCnt = 0;
    $mcCnt = 0;
    echo PHP_EOL . 'System ' . $sys . PHP_EOL;
    foreach ($cases as $case) {
        [$jd, $lat, $lon] = $case;
        if ($sys === 'G') {
            // 36 sectors mapping
            $cusp = [];
            $asc = [];
            $rc = HousesFunctions::houses($jd, $lat, $lon, $sys, $cusp, $asc);
            if ($rc !== 0) {
                echo sprintf('  PHP houses error (G) at lat=%.4f lon=%.4f' . PHP_EOL, $lat, $lon);
                continue;
            }
            $sw36 = swetestCuspsG($swetest, $jd, $lat, $lon);
            if ($sw36 === null) {
                echo sprintf('  swetest parse fail (G) at lat=%.4f lon=%.4f' . PHP_EOL, $lat, $lon);
                continue;
            }
            // Our cusp in G is 1..36 as well
            $our36 = [];
            for ($i = 1; $i <= 36; $i++) {
                if (!isset($cusp[$i])) {
                    echo "  PHP cusp[{$i}] missing for G" . PHP_EOL;
                    continue 2;
                }
                $our36[$i] = $cusp[$i];
            }
            $best = bestMappingCircular($sw36, $our36);
            echo sprintf(
                '  lat=%.4f lon=%.4f | G36 map=%s shift=%d avgΔ=%.6g maxΔ=%.6g',
                $lat,
                $lon,
                $best['mode'],
                $best['shift'],
                $best['avg'],
                $best['max']
            ) . PHP_EOL;
            $accAvg += $best['avg'];
            if ($best['max'] > $accMax) {
                $accMax = $best['max'];
            }
            $accCnt++;
        } else {
            $cusp = [];
            $asc = [];
            $rc = HousesFunctions::houses($jd, $lat, $lon, $sys, $cusp, $asc);
            if ($rc !== 0) {
                echo sprintf(
                    '  PHP houses error at lat=%.4f lon=%.4f (serr suppressed)' . PHP_EOL,
                    $lat,
                    $lon
                );
                continue;
            }
            $sw = swetestCusps($swetest, $jd, $lat, $lon, $sys);
            if ($sw === null) {
                echo sprintf(
                    '  swetest unavailable/parse fail for sys=%s at lat=%.4f lon=%.4f' . PHP_EOL,
                    $sys,
                    $lat,
                    $lon
                );
                continue;
            }
            [$swCusps, $swAsc, $swMc] = $sw;
            [$best, $d1, $d10, $dAsc, $dMc] = normalizeAndMap($sys, $swCusps, $swAsc, $swMc, $cusp, $asc);
            echo sprintf(
                '  lat=%.4f lon=%.4f | map=%s shift=%d avgΔ=%.6g maxΔ=%.6g | Δ1=%.6g Δ10=%.6g',
                $lat,
                $lon,
                $best['mode'],
                $best['shift'],
                $best['avg'],
                $best['max'],
                $d1,
                $d10
            );
            if ($dAsc !== null) {
                echo sprintf(' | ΔAsc=%.6g', $dAsc);
                $ascAvg += $dAsc;
                $ascCnt++;
            }
            if ($dMc !== null) {
                echo sprintf(' | ΔMC=%.6g', $dMc);
                $mcAvg += $dMc;
                $mcCnt++;
            }
            echo PHP_EOL;
            $accAvg += $best['avg'];
            if ($best['max'] > $accMax) {
                $accMax = $best['max'];
            }
            $accCnt++;
        }
    }
    if ($accCnt > 0) {
        $summary[$sys] = [
            'avg' => $accAvg / $accCnt,
            'max' => $accMax,
            'asc' => $ascCnt ? $ascAvg / $ascCnt : null,
            'mc' => $mcCnt ? $mcAvg / $mcCnt : null,
        ];
    }
}

echo PHP_EOL . '=== Summary (avg of avgΔ across cases; max of maxΔ) ===' . PHP_EOL;
foreach ($summary as $sys => $m) {
    $line = sprintf('%s: avg(avgΔ)=%.6g, max(maxΔ)=%.6g', $sys, $m['avg'], $m['max']);
    if ($m['asc'] !== null) {
        $line .= sprintf(', avg(ΔAsc)=%.6g', $m['asc']);
    }
    if ($m['mc'] !== null) {
        $line .= sprintf(', avg(ΔMC)=%.6g', $m['mc']);
    }
    echo $line . PHP_EOL;
}

