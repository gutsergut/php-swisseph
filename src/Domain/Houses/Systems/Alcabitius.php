<?php

namespace Swisseph\Domain\Houses\Systems;

use Swisseph\Domain\Houses\HouseSystem;
use Swisseph\Houses;
use Swisseph\Math;

/**
 * Система домов: Alcabitius (деление полу-дуг Asc→MC и Desc→IC на 3 части по часовому углу).
 */
final class Alcabitius implements HouseSystem
{
    /**
     * Куспы Alcabitius: деление дневной и ночной полу-дуги по часовому углу и обратная проекция на эклиптику.
     * @return array cusps[1..12] в радианах
     */
    public function cusps(float $armc_rad, float $geolat_rad, float $eps_rad, float $asc_rad = NAN, float $mc_rad = NAN): array
    {
        [$asc, $mc] = Houses::ascMcFromArmc($armc_rad, $geolat_rad, $eps_rad);
        $cusp = array_fill(0, 13, 0.0);
        $cusp[1] = $asc;
        $cusp[10] = $mc;
        $cusp[7] = Math::normAngleRad($asc + Math::PI);
        $cusp[4] = Math::normAngleRad($mc + Math::PI);

        $ce = cos($eps_rad);
        $raOf = function (float $lon_ecl) use ($eps_rad): float {
            [$ra, $dec] = \Swisseph\Coordinates::eclipticToEquatorialRad($lon_ecl, 0.0, 1.0, $eps_rad);
            return $ra;
        };
        $hourAngle = function (float $ra) use ($armc_rad): float {
            $H = Math::normAngleRad($armc_rad - $ra);
            if ($H > Math::PI) $H -= Math::TWO_PI;
            return $H;
        };
        $lambdaFromRa = function (float $ra) use ($ce): float {
            $s = sin($ra); $c = cos($ra);
            $lon = atan2($s, $c * $ce);
            return ($lon < 0) ? $lon + Math::TWO_PI : $lon;
        };

        // Day side: Asc -> MC
        $ra_asc = $raOf($asc);
        $Hasc = $hourAngle($ra_asc);
        $H12 = (2.0/3.0) * $Hasc;
        $H11 = (1.0/3.0) * $Hasc;
        $ra12 = Math::normAngleRad($armc_rad - $H12);
        $ra11 = Math::normAngleRad($armc_rad - $H11);
        $cusp[12] = $lambdaFromRa($ra12);
        $cusp[11] = $lambdaFromRa($ra11);

        // Night side: Desc -> IC
        $desc = $cusp[7];
        $ra_desc = $raOf($desc);
        $Hdesc = $hourAngle($ra_desc);
        if ($Hdesc < 0) { $Hdesc += Math::TWO_PI; }
        $ra_ic = Math::normAngleRad($raOf($cusp[4]));
        $Hic = $hourAngle($ra_ic);
        if ($Hic <= 0) { $Hic += Math::TWO_PI; }
        $H2 = $Hdesc + (1.0/3.0) * ($Hic - $Hdesc);
        $H3 = $Hdesc + (2.0/3.0) * ($Hic - $Hdesc);
        $ra2 = Math::normAngleRad($armc_rad - $H2);
        $ra3 = Math::normAngleRad($armc_rad - $H3);
        $cusp[2] = $lambdaFromRa($ra2);
        $cusp[3] = $lambdaFromRa($ra3);

        // Opposites
        $cusp[5] = Math::normAngleRad($cusp[11] + Math::PI);
        $cusp[6] = Math::normAngleRad($cusp[12] + Math::PI);
        $cusp[8] = Math::normAngleRad($cusp[2] + Math::PI);
        $cusp[9] = Math::normAngleRad($cusp[3] + Math::PI);
        return $cusp;
    }
}
