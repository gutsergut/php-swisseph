<?php

namespace Swisseph\Domain\Houses\Systems;

use Swisseph\Domain\Houses\HouseSystem;
use Swisseph\Houses;
use Swisseph\Math;

/**
 * Система домов: Pullen SR (Sinusoidal Ratio), код 'Q'.
 * Деление каждого квадранта по эклиптике в пропорциях синусоиды:
 * веса для трёх домов квадранта ~ sin(30°), sin(60°), sin(30°).
 * Суммарной дугой квадранта делятся эти веса, чтобы получить ширины домов.
 */
final class PullenSR implements HouseSystem
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

        // Синусоидальные веса для трёх домов квадранта
        $w1 = sin(Math::degToRad(30.0));
        $w2 = sin(Math::degToRad(60.0));
        $w3 = sin(Math::degToRad(30.0));
        $wsum = $w1 + $w2 + $w3; // = 0.5 + 0.8660 + 0.5 = 1.8660

        $cusp = array_fill(0, 13, 0.0);
        $cusp[1] = $asc; $cusp[10] = $mc; $cusp[7] = $desc; $cusp[4] = $ic;

        // Кв. Asc -> MC: дома 12,11,10
        $q1 = $fwdDeg($mc, $asc);
        $d12 = $q1 * ($w1 / $wsum);
        $d11 = $q1 * ($w2 / $wsum);
        $cusp[12] = $stepAdd($asc, $d12);
        $cusp[11] = $stepAdd($cusp[12], $d11);

        // Кв. MC -> Desc: дома 9,8,7
        $q2 = $fwdDeg($desc, $mc);
        $d9 = $q2 * ($w1 / $wsum);
        $d8 = $q2 * ($w2 / $wsum);
        $cusp[9] = $stepAdd($mc, $d9);
        $cusp[8] = $stepAdd($cusp[9], $d8);

        // Кв. Desc -> IC: дома 6,5,4
        $q3 = $fwdDeg($ic, $desc);
        $d6 = $q3 * ($w1 / $wsum);
        $d5 = $q3 * ($w2 / $wsum);
        $cusp[6] = $stepAdd($desc, $d6);
        $cusp[5] = $stepAdd($cusp[6], $d5);

        // Кв. IC -> Asc: дома 3,2,1
        $q4 = $fwdDeg($asc, $ic);
        $d3 = $q4 * ($w1 / $wsum);
        $d2 = $q4 * ($w2 / $wsum);
        $cusp[3] = $stepAdd($ic, $d3);
        $cusp[2] = $stepAdd($cusp[3], $d2);

        return $cusp;
    }
}
