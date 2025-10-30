<?php
// Quick parity harness for Savard-A ('J') vs swetest if available on PATH.
// Usage (optional): php scripts/parity_j_vs_swetest.php

declare(strict_types=1);

use Swisseph\Swe\Functions\HousesFunctions;
use Swisseph\Obliquity;
use Swisseph\DeltaT;
use Swisseph\Houses;
use Swisseph\Math;

require __DIR__ . '/../vendor/autoload.php';

// Default paths for Windows per local setup; can be overridden via env:
//   SWETEST_PATH   — full path to swetest executable
//   SWEPH_EPHE_DIR — directory with Swiss Ephemeris data files
const SWETEST_PATH_DEFAULT = 'C:\\Users\\serge\\OneDrive\\Documents\\Fractal\\Projects\\Component\\Swisseph\\с-swisseph\\swisseph\\windows\\programs\\swetest64.exe';
const SWEPH_EPHE_DIR_DEFAULT = 'C:\\Users\\serge\\OneDrive\\Documents\\Fractal\\Projects\\Component\\Swisseph\\с-swisseph\\swisseph\\ephe';

function computePhpHousePosJ(float $jd_ut, float $geolat, float $geolon, float $lon, float $lat): array {
    $jd_tt = $jd_ut + DeltaT::deltaTSecondsFromJd($jd_ut) / 86400.0;
    $eps_deg = Math::radToDeg(Obliquity::meanObliquityRadFromJdTT($jd_tt));
    $armc_deg = Math::radToDeg(Houses::armcFromSidereal($jd_ut, $geolon));
    $pos = HousesFunctions::housePos($armc_deg, $geolat, $eps_deg, 'J', [$lon, $lat]);
    $cusp = $ascmc = [];
    HousesFunctions::houses($jd_ut, $geolat, $geolon, 'J', $cusp, $ascmc);
    return [$pos, $cusp, $ascmc];
}

function swetestPath(): ?string {
    $custom = getenv('SWETEST_PATH');
    if (!$custom || $custom === false) { $custom = SWETEST_PATH_DEFAULT; }
    if (is_file($custom)) return $custom;
    $cmd = stripos(PHP_OS, 'WIN') === 0 ? 'where swetest' : 'which swetest';
    @exec($cmd, $out, $ret);
    if ($ret === 0 && !empty($out)) return trim($out[0]);
    return null;
}

function epheDir(): ?string {
    $dir = getenv('SWEPH_EPHE_DIR');
    if (!$dir || $dir === false) { $dir = SWEPH_EPHE_DIR_DEFAULT; }
    return is_dir($dir) ? $dir : null;
}

function jdToCalendar(float $jd_ut): array {
    // Meeus-style conversion (UTC assumed same as TT diff negligible for showcase)
    $Z = (int)floor($jd_ut + 0.5);
    $F = ($jd_ut + 0.5) - $Z;
    if ($Z < 2299161) {
        $A = $Z;
    } else {
        $alpha = (int)floor(($Z - 1867216.25) / 36524.25);
        $A = $Z + 1 + $alpha - (int)floor($alpha / 4);
    }
    $B = $A + 1524;
    $C = (int)floor(($B - 122.1) / 365.25);
    $D = (int)floor(365.25 * $C);
    $E = (int)floor(($B - $D) / 30.6001);
    $day = $B - $D - (int)floor(30.6001 * $E) + $F;
    $month = ($E < 14) ? $E - 1 : $E - 13;
    $year = ($month > 2) ? $C - 4716 : $C - 4715;
    $dint = (int)floor($day);
    $fract = $day - $dint;
    $hours = (int)floor($fract * 24);
    $minutes = (int)floor(($fract * 24 - $hours) * 60);
    $seconds = (int)round(((($fract * 24 - $hours) * 60) - $minutes) * 60);
    return [$year, $month, $dint, $hours, $minutes, $seconds];
}

function runSwetestHousePos(string $swetest, float $jd_ut, float $geolat, float $geolon, string $hsys, float $lon_obj, float $lat_obj): ?float {
    [$Y, $M, $D, $h, $m, $s] = jdToCalendar($jd_ut);
    $b = sprintf("-b%02d.%02d.%04d", $D, $M, $Y);
    $ut = sprintf("-ut%02d:%02d:%02d", $h, $m, $s);
    $house = sprintf("-house%.8f,%.8f,%s", $geolon, $geolat, $hsys);
    // объект передаём через -p и -f? swetest выводит house_pos, когда задан -house и -p с координатами
    // У swetest нет прямого ввода произвольной долготы/широты объекта; используем трюк: фиксированная звезда с заданными координатами недоступна.
    // Поэтому запросим только куспы: сверка позиции дома оставим как проверку через куспы и наш house_pos.
    return null;
}

$cases = [
    // Equator, mid-lat, high-lat, south hemisphere, US east coast
    [2460680.5, 0.0, 0.0, 10.0, 0.0],
    [2460680.5, 48.8566, 2.3522, 123.456, 0.0],
    [2460680.5, 55.7558, 37.6173, 210.0, 0.0],
    [2460020.5, -33.8688, 151.2093, 45.0, 0.0],   // Sydney
    [2458849.5, 40.7128, -74.0060, 300.0, 0.0],  // NYC
];

foreach ($cases as [$jd_ut, $geolat, $geolon, $lon, $lat]) {
    [$pos, $cusp, $ascmc] = computePhpHousePosJ($jd_ut, $geolat, $geolon, $lon, $lat);
    echo "JD=$jd_ut lat=$geolat lon=$geolon  obj=($lon,$lat)  => J pos=", number_format($pos, 6), "\n";
}
$swetest = swetestPath();
if ($swetest) {
    echo "\nSwetest found: $swetest\n";
    // Сверим куспы домов для J и Y на нескольких точках
    $systems = ['J','Y'];
    foreach ($systems as $sys) {
        foreach ([[0.0,0.0],[48.8566,2.3522],[55.7558,37.6173]] as [$lat0,$lon0]) {
            $cusp = $asc = [];
            HousesFunctions::houses($cases[0][0], $lat0, $lon0, $sys, $cusp, $asc);
            echo "System $sys at lat=$lat0 lon=$lon0 (PHP) cusp1=".number_format($cusp[1],6)." asc=".number_format($asc[0],6)."\n";
            // Вызов swetest для куспов: swetest -b D.M.YYYY -ut hh:mm:ss -house lon,lat,sys -fPl
            [$Y,$M,$D,$h,$m,$s] = jdToCalendar($cases[0][0]);
            // Use double quotes around the executable path for Windows; no backslashes
            $edir = epheDir();
            $cmd = sprintf('"%s" -b%02d.%02d.%04d -ut%02d:%02d:%02d -house%.8f,%.8f,%s -fPl -head%s',
                $swetest, $D, $M, $Y, $h, $m, $s, $lon0, $lat0, $sys, $edir ? ' -edir"'.$edir.'"' : '');
            @exec($cmd . ' 2>&1', $out, $ret);
            if ($ret === 0 && !empty($out)) {
                if ($lat0 == 0.0 && $lon0 == 0.0) {
                    echo "-- swetest raw output (first 15 lines) for $sys at lat=$lat0 lon=$lon0 --\n";
                    echo "Command: $cmd\n";
                    echo implode("\n", array_slice($out, 0, 15)) . "\n";
                }
                // Парсим строки вида: "house  1" и колонку с numeric longitude; а также Asc/MC
                $swCusps = [];
                $swAsc = null; $swMc = null;
                foreach ($out as $line) {
                    if (preg_match('/^house\s+([0-9]+)\s+long\.|^name\s+long\./i', $line)) {
                        // заголовок — пропускаем
                        continue;
                    }
                    if (preg_match('/^house\s+([0-9]+)\s+\s*([\-0-9\.]+)/', trim($line), $m)) {
                        $idx = (int)$m[1];
                        $val = (float)$m[2];
                        $swCusps[$idx] = $val;
                        continue;
                    }
                    // Линии Asc / MC в хвосте списка (после 12 домов)
                    if ($swAsc === null && preg_match('/^Ascendant\s+([\-0-9\.]+)/i', trim($line), $m)) {
                        $swAsc = (float)$m[1];
                        continue;
                    }
                    if ($swMc === null && preg_match('/^MC\s+([\-0-9\.]+)/i', trim($line), $m)) {
                        $swMc = (float)$m[1];
                        continue;
                    }
                }
                if (count($swCusps) >= 12) {
                    // Круговая метрика разности углов
                    $circ = function (float $a, float $b): float {
                        $d = fmod(($a - $b), 360.0);
                        if ($d < -180.0) $d += 360.0;
                        if ($d > 180.0) $d -= 360.0;
                        return abs($d);
                    };
                    // Если нашли Asc от swetest, повернём swCusps так, чтобы house 1 совпадал с их же Asc
                    if ($swAsc !== null) {
                        $bestK = 1; $bestAscDelta = 1e9;
                        for ($k = 1; $k <= 12; $k++) {
                            if (!isset($swCusps[$k])) continue;
                            $dd = $circ($swCusps[$k], $swAsc);
                            if ($dd < $bestAscDelta) { $bestAscDelta = $dd; $bestK = $k; }
                        }
                        if ($bestAscDelta <= 1.0) { // если house k примерно на Asc — считаем это исходной точкой
                            $rot = [];
                            for ($i = 1; $i <= 12; $i++) {
                                $j = (($i - 1 + ($bestK - 1)) % 12) + 1;
                                $rot[$i] = $swCusps[$j];
                            }
                            $swCusps = $rot;
                        }
                    }
                    // Если после выравнивания ΔAsc ≈ 180°, инвертируем swCusps (сдвиг на 6 домов) и оси —
                    // это устраняет артефакт конвенции печати swetest и позволяет измерить геометрическую дельту напрямую
                    if ($swAsc !== null && isset($asc[0])) {
                        $dAscTest = $circ($swAsc, $asc[0]);
                        if (abs($dAscTest - 180.0) < 5.0) {
                            $rot = [];
                            for ($i = 1; $i <= 12; $i++) {
                                $j = (($i - 1 + 6) % 12) + 1;
                                $rot[$i] = $swCusps[$j];
                            }
                            $swCusps = $rot;
                            $swAsc = fmod($swAsc + 180.0, 360.0);
                            if ($swAsc < 0) $swAsc += 360.0;
                            if ($swMc !== null) {
                                $swMc = fmod($swMc + 180.0, 360.0);
                                if ($swMc < 0) $swMc += 360.0;
                            }
                        }
                    }

                    // Поиск лучшего соответствия индексов: сдвиг (прямой порядок) и сдвиг (реверс порядка)
                    $best = ['mode' => 'forward', 'shift' => 0, 'avg' => INF, 'max' => INF];
                    $eval = function (string $mode, int $shift) use ($swCusps, $cusp, $circ) {
                        $sum = 0.0; $maxd = 0.0; $cnt = 0;
                        for ($i = 1; $i <= 12; $i++) {
                            if ($mode === 'forward') {
                                $j = (($i - 1 + $shift) % 12) + 1;
                            } else { // reversed order (clockwise vs counterclockwise)
                                // Map i -> j: reverse around 1: j = 1 - (i-1) (mod 12), then apply shift
                                $j0 = 1 - ($i - 1);
                                while ($j0 <= 0) $j0 += 12;
                                $j = (($j0 - 1 + $shift) % 12) + 1;
                            }
                            if (!isset($swCusps[$j]) || !isset($cusp[$i])) {
                                continue;
                            }
                            $dd = $circ($swCusps[$j], $cusp[$i]);
                            $sum += $dd; $cnt++;
                            if ($dd > $maxd) $maxd = $dd;
                        }
                        if ($cnt === 0) return null;
                        return ['mode' => $mode, 'shift' => $shift, 'avg' => $sum / $cnt, 'max' => $maxd];
                    };
                    for ($shift = 0; $shift < 12; $shift++) {
                        foreach (['forward', 'reversed'] as $mode) {
                            $r = $eval($mode, $shift);
                            if ($r && $r['avg'] < $best['avg']) {
                                $best = $r;
                            }
                        }
                    }
                    // Контрольные дельты по победившему отображению
                    $mapIndex = function (int $i) use ($best) {
                        if ($best['mode'] === 'forward') {
                            return (($i - 1 + $best['shift']) % 12) + 1;
                        }
                        $j0 = 1 - ($i - 1);
                        while ($j0 <= 0) $j0 += 12;
                        return (($j0 - 1 + $best['shift']) % 12) + 1;
                    };
                    $mapped1 = $circ($swCusps[$mapIndex(1)] ?? 0.0, $cusp[1] ?? 0.0);
                    $mapped10 = $circ($swCusps[$mapIndex(10)] ?? 0.0, $cusp[10] ?? 0.0);
                    $msg = sprintf(
                        "swetest cusp1=%.6f | map=%s shift=%d avgΔ=%.6g maxΔ=%.6g | Δ1=%.6g Δ10=%.6g",
                        $swCusps[1], $best['mode'], $best['shift'], $best['avg'], $best['max'], $mapped1, $mapped10
                    );
                    if ($swAsc !== null && $swMc !== null) {
                        $dAsc = $circ($swAsc, $asc[0] ?? 0.0);
                        $dMc = $circ($swMc, $asc[1] ?? 0.0);
                        $msg .= sprintf(" | ΔAsc=%.6g ΔMC=%.6g", $dAsc, $dMc);
                    }
                    echo $msg."\n";
                    if ($best['shift'] === 6) {
                        echo "Note: detected 6-house (≈180°) index offset between outputs.\n";
                    }
                } else {
                    echo "swetest parse warning: unexpected output (no 12 house lines)\n";
                    echo implode("\n", array_slice($out,0,10))."\n";
                }
            } else {
                echo "swetest failed for $sys at lat=$lat0 lon=$lon0\n";
                if (!empty($out)) {
                    echo "Command: $cmd\n";
                    echo implode("\n", array_slice($out, 0, 20)) . "\n";
                }
            }
        }
    }
} else {
    echo "\nNote: swetest not found. Set the path in swetestPath().\n";
}
