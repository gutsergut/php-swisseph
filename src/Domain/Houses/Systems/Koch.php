<?php

namespace Swisseph\Domain\Houses\Systems;

use Swisseph\Domain\Houses\HouseSystem;
use Swisseph\Houses;
use Swisseph\Math;
use Swisseph\Domain\Houses\Support\LatitudeGuard;

/**
 * Система домов: Koch (по равным часовым дугам Asc, решается через ARMC).
 * Не определена на высоких широтах — возвращает нули и ошибку на уровне фасада.
 */
final class Koch implements HouseSystem
{
    /**
     * Куспы Koch через обратное вычисление ARMC по целевому Asc и деление дуг.
     * @return array cusps[1..12] в радианах или нули при недопустимой широте
     */
    public function cusps(float $armc_rad, float $geolat_rad, float $eps_rad, float $asc_rad = NAN, float $mc_rad = NAN): array
    {
        if (!LatitudeGuard::isKochDefined($geolat_rad)) {
            return array_fill(0, 13, 0.0);
        }
        $ascFromArmc = function (float $a) use ($geolat_rad, $eps_rad): float {
            [$asc, ] = Houses::ascMcFromArmc(Math::normAngleRad($a), $geolat_rad, $eps_rad);
            return $asc;
        };
        $mcFromArmc = function (float $a) use ($geolat_rad, $eps_rad): float {
            [, $mc] = Houses::ascMcFromArmc(Math::normAngleRad($a), $geolat_rad, $eps_rad);
            return $mc;
        };
        $angleDiffSym = function (float $a, float $b): float {
            $d = $a - $b;
            while ($d > Math::PI) { $d -= Math::TWO_PI; }
            while ($d < -Math::PI) { $d += Math::TWO_PI; }
            return $d;
        };
        $rootAscEq = function (float $a0, float $a1, float $target) use ($ascFromArmc, $angleDiffSym): ?float {
            $f0 = $angleDiffSym($ascFromArmc($a0), $target);
            $f1 = $angleDiffSym($ascFromArmc($a1), $target);
            if (!is_finite($f0) || !is_finite($f1)) return null;
            if ($f0 * $f1 > 0) {
                $N = 180;
                $step = ($a1 - $a0) / $N;
                $pa = $a0; $pf = $f0;
                for ($i=1; $i<=$N; $i++) {
                    $x = $a0 + $i*$step;
                    $fx = $angleDiffSym($ascFromArmc($x), $target);
                    if ($pf * $fx <= 0) { $a0 = $pa; $a1 = $x; $f0 = $pf; $f1 = $fx; break; }
                    $pa = $x; $pf = $fx;
                }
                if ($pf * $f1 > 0 && $f0 * $f1 > 0) {
                    return null;
                }
            }
            $lo = $a0; $hi = $a1; $flo = $f0; $fhi = $f1;
            for ($i=0; $i<40; $i++) {
                $mi = 0.5 * ($lo + $hi);
                $fmi = $angleDiffSym($ascFromArmc($mi), $target);
                if (abs($fmi) < 1e-10) return Math::normAngleRad($mi);
                if ($flo * $fmi <= 0) { $hi = $mi; $fhi = $fmi; } else { $lo = $mi; $flo = $fmi; }
            }
            return Math::normAngleRad(0.5 * ($lo + $hi));
        };
        $a0 = Math::normAngleRad($armc_rad);
        $asc0 = $ascFromArmc($a0);
        $mc0  = $mcFromArmc($a0);
        $ic0  = Math::normAngleRad($mc0 + Math::PI);
        $range = Math::PI;
        $a_prev = $rootAscEq($a0 - $range, $a0 - 1e-6, $mc0);
        if ($a_prev === null) {
            return array_fill(0, 13, 0.0);
        }
        $a_next = $rootAscEq($a0 + 1e-6, $a0 + $range, $ic0);
        if ($a_next === null) {
            return array_fill(0, 13, 0.0);
        }
        $S_mc = Math::normAngleRad($a0 - $a_prev);
        if ($S_mc <= 0) { $S_mc += Math::TWO_PI; }
        if ($S_mc > Math::PI) { $S_mc = Math::TWO_PI - $S_mc; }
        $S_ic = Math::normAngleRad($a_next - $a0);
        if ($S_ic <= 0) { $S_ic += Math::TWO_PI; }
        if ($S_ic > Math::PI) { $S_ic = Math::TWO_PI - $S_ic; }
        $a11 = $a_prev + $S_mc / 3.0;
        $a12 = $a_prev + 2.0 * $S_mc / 3.0;
        $a2  = $a0 + $S_ic / 3.0;
        $a3  = $a0 + 2.0 * $S_ic / 3.0;
        $cusp = array_fill(0, 13, 0.0);
        $cusp[1] = $asc0;
        $cusp[10] = $mc0;
        $cusp[7] = Math::normAngleRad($cusp[1] + Math::PI);
        $cusp[4] = Math::normAngleRad($cusp[10] + Math::PI);
        $cusp[12] = $ascFromArmc($a12);
        $cusp[11] = $ascFromArmc($a11);
        $cusp[2]  = $ascFromArmc($a2);
        $cusp[3]  = $ascFromArmc($a3);
        $cusp[5] = Math::normAngleRad($cusp[11] + Math::PI);
        $cusp[6] = Math::normAngleRad($cusp[12] + Math::PI);
        $cusp[8] = Math::normAngleRad($cusp[2] + Math::PI);
        $cusp[9] = Math::normAngleRad($cusp[3] + Math::PI);
        return $cusp;
    }
}
