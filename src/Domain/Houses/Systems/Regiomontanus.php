<?php

namespace Swisseph\Domain\Houses\Systems;

use Swisseph\Domain\Houses\HouseSystem;
use Swisseph\Houses;
use Swisseph\Domain\Houses\Support\CuspPostprocessor;
use Swisseph\Math;

/**
 * Система домов: Regiomontanus (равные часовые углы на небесном экваторе, проекция на эклиптику).
 */
final class Regiomontanus implements HouseSystem
{
    /**
     * Куспы Regiomontanus, с принудительным выравниванием cusp1=Asc и cusp10=MC.
     * @return array cusps[1..12] в радианах
     */
    public function cusps(float $armc_rad, float $geolat_rad, float $eps_rad, float $asc_rad = NAN, float $mc_rad = NAN): array
    {
        [$asc, $mc] = is_finite($asc_rad) && is_finite($mc_rad)
            ? [$asc_rad, $mc_rad]
            : Houses::ascMcFromArmc($armc_rad, $geolat_rad, $eps_rad);
        $asc_deg = Math::normAngleDeg(Math::radToDeg($asc));
        $mc_deg  = Math::normAngleDeg(Math::radToDeg($mc));
        $desc_deg = Math::normAngleDeg($asc_deg + 180.0);
        $ic_deg   = Math::normAngleDeg($mc_deg + 180.0);

        $lst = Math::normAngleRad($armc_rad);
        $ce = cos($eps_rad); $se = sin($eps_rad);
        $n_ecl = [0.0, -$se, $ce];
        $toRad = fn($d) => Math::degToRad(Math::normAngleDeg($d));
        $fwdDeg = function (float $to, float $from): float {
            $d = $to - $from;
            while ($d < 0) { $d += 360.0; }
            while ($d >= 360.0) { $d -= 360.0; }
            return $d;
        };
        $offsets = [
            10 => 0.0, 11 => 30.0, 12 => 60.0, 1  => 90.0,
            2  => 120.0, 3  => 150.0, 4  => 180.0, 5  => 210.0,
            6  => 240.0, 7  => 270.0, 8  => 300.0, 9  => 330.0,
        ];
        $order = [1,12,11,10,9,8,7,6,5,4,3,2];
        $c = array_fill(0, 13, 0.0);
        $c[1] = Math::normAngleRad($asc);
        $c[10] = Math::normAngleRad($mc);
        foreach ($order as $idx) {
            if ($idx === 1 || $idx === 10) { continue; }
            $alpha = $lst + Math::degToRad($offsets[$idx]);
            $ca = cos($alpha); $sa = sin($alpha);
            $n = [-$sa, $ca, 0.0];
            $l = [
                $n[1]*$n_ecl[2] - $n[2]*$n_ecl[1],
                $n[2]*$n_ecl[0] - $n[0]*$n_ecl[2],
                $n[0]*$n_ecl[1] - $n[1]*$n_ecl[0],
            ];
            $x = $l[0];
            $y =  $l[1]*$ce + $l[2]*$se;
            $lon = atan2($y, $x);
            if ($lon < 0) { $lon += Math::TWO_PI; }
            $lon_deg = Math::radToDeg($lon);
            $lon_op_deg = Math::normAngleDeg($lon_deg + 180.0);
            if (in_array($idx, [12,11], true)) {
                $span = $fwdDeg($mc_deg, $asc_deg);
                $d1 = $fwdDeg($lon_deg, $asc_deg);
                $use = ($d1 <= $span) ? $lon_deg : $lon_op_deg;
            } elseif (in_array($idx, [9,8], true)) {
                $span = $fwdDeg($desc_deg, $mc_deg);
                $d1 = $fwdDeg($lon_deg, $mc_deg);
                $use = ($d1 <= $span) ? $lon_deg : $lon_op_deg;
            } elseif (in_array($idx, [6,5], true)) {
                $span = $fwdDeg($ic_deg, $desc_deg);
                $d1 = $fwdDeg($lon_deg, $desc_deg);
                $use = ($d1 <= $span) ? $lon_deg : $lon_op_deg;
            } else {
                $span = $fwdDeg($asc_deg, $ic_deg);
                $d1 = $fwdDeg($lon_deg, $ic_deg);
                $use = ($d1 <= $span) ? $lon_deg : $lon_op_deg;
            }
            $c[$idx] = Math::degToRad($use);
        }
        $c[7] = Math::normAngleRad($c[1] + Math::PI);
        $c[4] = Math::normAngleRad($c[10] + Math::PI);
        $c[8] = Math::normAngleRad($c[2] + Math::PI);
        $c[9] = Math::normAngleRad($c[3] + Math::PI);
        $c[5] = Math::normAngleRad($c[11] + Math::PI);
        $c[6] = Math::normAngleRad($c[12] + Math::PI);
        // Дополнительно гарантируем базовые куспы
        $c = CuspPostprocessor::forceAscMc($c, $asc, $mc);
        return $c;
    }
}
