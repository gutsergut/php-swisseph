<?php

namespace Swisseph\Domain\Houses\Systems;

use Swisseph\Domain\Houses\HouseSystem;
use Swisseph\Houses;
use Swisseph\Math;

/**
 * Система домов: Porphyry (деление каждого квадранта по эклиптике на три равные дуги).
 */
final class Porphyry implements HouseSystem
{
    /**
     * Куспы Porphyry из Asc/MC и деления квадрантов по эклиптике.
     * @return array cusps[1..12] в радианах
     */
    public function cusps(float $armc_rad, float $geolat_rad, float $eps_rad, float $asc_rad = NAN, float $mc_rad = NAN): array
    {
        if (!is_finite($asc_rad) || !is_finite($mc_rad)) {
            [$asc, $mc] = Houses::ascMcFromArmc($armc_rad, $geolat_rad, $eps_rad);
        } else {
            $asc = $asc_rad; $mc = $mc_rad;
        }
        $asc = Math::normAngleRad($asc);
        $mc  = Math::normAngleRad($mc);
        $desc = Math::normAngleRad($asc + Math::PI);
        $ic   = Math::normAngleRad($mc + Math::PI);

        $fwd = function (float $to, float $from): float {
            $d = $to - $from;
            while ($d < 0) { $d += Math::TWO_PI; }
            while ($d >= Math::TWO_PI) { $d -= Math::TWO_PI; }
            return $d;
        };
        $stepAdd = function (float $start, float $delta): float {
            return Math::normAngleRad($start + $delta);
        };

        $cusp = array_fill(0, 13, 0.0);
        // Asc -> MC
        $cusp[1] = $asc;
        $d1 = $fwd($mc, $asc);
        $cusp[12] = $stepAdd($asc, $d1 / 3.0);
        $cusp[11] = $stepAdd($asc, 2.0 * $d1 / 3.0);
        $cusp[10] = $mc;
        // MC -> Desc
        $d2 = $fwd($desc, $mc);
        $cusp[9] = $stepAdd($mc, $d2 / 3.0);
        $cusp[8] = $stepAdd($mc, 2.0 * $d2 / 3.0);
        $cusp[7] = $desc;
        // Desc -> IC
        $d3 = $fwd($ic, $desc);
        $cusp[6] = $stepAdd($desc, $d3 / 3.0);
        $cusp[5] = $stepAdd($desc, 2.0 * $d3 / 3.0);
        $cusp[4] = $ic;
        // IC -> Asc
        $d4 = $fwd($asc, $ic);
        $cusp[3] = $stepAdd($ic, $d4 / 3.0);
        $cusp[2] = $stepAdd($ic, 2.0 * $d4 / 3.0);
        return $cusp;
    }
}
