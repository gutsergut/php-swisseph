<?php

namespace Swisseph\Domain\Houses\Systems;

use Swisseph\Domain\Houses\HouseSystem;
use Swisseph\Houses;
use Swisseph\Math;

/**
 * Система домов: Pullen SD (Sinusoidal Delta), код 'L'.
 * Описание: как Porphyry (деление по эклиптике в пределах квадранта), но
 * ширины домов внутри каждого квадранта распределяются по "синусоидальному"
 * правилу: пусть размер квадранта q (в градусах), d = q - 90°.
 * Тогда три дома в квадранте имеют ширины: 30°+d/4, 30°+d/2, 30°+d/4
 * (средний дом квадранта получает половину отклонения, крайние — по четверти).
 */
final class PullenSD implements HouseSystem
{
    public function cusps(
        float $armc_rad,
        float $geolat_rad,
        float $eps_rad,
        float $asc_rad = NAN,
        float $mc_rad = NAN
    ): array {
        if (!is_finite($asc_rad) || !is_finite($mc_rad)) {
            [$asc, $mc] = Houses::ascMcFromArmc($armc_rad, $geolat_rad, $eps_rad);
        } else {
            $asc = $asc_rad; $mc = $mc_rad;
        }
        $asc = Math::normAngleRad($asc);
        $mc  = Math::normAngleRad($mc);

        $desc = Math::normAngleRad($asc + Math::PI);
        $ic   = Math::normAngleRad($mc + Math::PI);

        $fwdDeg = function (float $to, float $from): float {
            $d = Math::radToDeg($to - $from);
            while ($d < 0) { $d += 360.0; }
            while ($d >= 360.0) { $d -= 360.0; }
            return $d;
        };
        $stepAdd = function (float $start, float $delta_deg): float {
            return Math::normAngleRad($start + Math::degToRad($delta_deg));
        };

        $cusp = array_fill(0, 13, 0.0);
        $cusp[1] = $asc;
        $cusp[10] = $mc;
        $cusp[7] = $desc;
        $cusp[4] = $ic;

        // Кв.1: Asc -> MC: дома 12, 11, 10 (средний — 11)
        $q1 = $fwdDeg($mc, $asc); $d1 = $q1 - 90.0;
        $w12 = 30.0 + $d1 / 4.0;
        $w11 = 30.0 + $d1 / 2.0;
        // $w10 = 30.0 + $d1 / 4.0; // завершается MC
        $cusp[12] = $stepAdd($asc, $w12);
        $cusp[11] = $stepAdd($cusp[12], $w11);

        // Кв.2: MC -> Desc: дома 9, 8, 7 (средний — 8)
        $q2 = $fwdDeg($desc, $mc); $d2 = $q2 - 90.0;
        $w9 = 30.0 + $d2 / 4.0;
        $w8 = 30.0 + $d2 / 2.0;
        $cusp[9] = $stepAdd($mc, $w9);
        $cusp[8] = $stepAdd($cusp[9], $w8);

        // Кв.3: Desc -> IC: дома 6, 5, 4 (средний — 5)
        $q3 = $fwdDeg($ic, $desc); $d3 = $q3 - 90.0;
        $w6 = 30.0 + $d3 / 4.0;
        $w5 = 30.0 + $d3 / 2.0;
        $cusp[6] = $stepAdd($desc, $w6);
        $cusp[5] = $stepAdd($cusp[6], $w5);

        // Кв.4: IC -> Asc: дома 3, 2, 1 (средний — 2)
        $q4 = $fwdDeg($asc, $ic); $d4 = $q4 - 90.0;
        $w3 = 30.0 + $d4 / 4.0;
        $w2 = 30.0 + $d4 / 2.0;
        $cusp[3] = $stepAdd($ic, $w3);
        $cusp[2] = $stepAdd($cusp[3], $w2);

        return $cusp;
    }
}
