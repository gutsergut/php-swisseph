<?php

namespace Swisseph\Domain\Houses\Systems;

use Swisseph\Domain\Houses\HouseSystem;
use Swisseph\Houses;
use Swisseph\Math;
use Swisseph\Domain\Houses\Support\LatitudeGuard;

/**
 * Система домов: Placidus (деление полу-дуг восхождения/захода, численно).
 * При высоких широтах не определена — возвращает нули и ошибку на уровне фасада.
 */
final class Placidus implements HouseSystem
{
    /**
     * Куспы Placidus численным решателем (скан+ньютон), с защитой по широте.
     * @return array cusps[1..12] в радианах или нули, если широта вне области
     */
    public function cusps(float $armc_rad, float $geolat_rad, float $eps_rad, float $asc_rad = NAN, float $mc_rad = NAN): array
    {
        // Guard for high latitudes
        if (!LatitudeGuard::isPlacidusDefined($geolat_rad)) {
            return array_fill(0, 13, 0.0);
        }
        [$asc, $mc] = Houses::ascMcFromArmc($armc_rad, $geolat_rad, $eps_rad);
        $cusp = array_fill(0, 13, 0.0);
        $cusp[1] = $asc;
        $cusp[10] = $mc;
        $cusp[7] = Math::normAngleRad($asc + Math::PI);
        $cusp[4] = Math::normAngleRad($mc + Math::PI);

        $radec = function (float $lon_ecl) use ($eps_rad): array {
            [$ra, $dec] = \Swisseph\Coordinates::eclipticToEquatorialRad($lon_ecl, 0.0, 1.0, $eps_rad);
            return [$ra, $dec];
        };
        $sda = function (float $dec) use ($geolat_rad): float {
            $t = -tan($geolat_rad) * tan($dec);
            if ($t < -1.0) $t = -1.0; if ($t > 1.0) $t = 1.0;
            return acos($t);
        };
        $hourAngle = function (float $ra) use ($armc_rad): float {
            $H = Math::normAngleRad($armc_rad - $ra);
            if ($H > Math::PI) $H -= Math::TWO_PI;
            return $H;
        };
        $dDay = function (float $lon_ecl, float $f) use ($radec, $hourAngle, $sda): float {
            [$ra, $dec] = $radec($lon_ecl);
            $H = $hourAngle($ra);
            $S = $sda($dec);
            return $H + $f * $S;
        };
        $dNight = function (float $lon_ecl, float $f) use ($radec, $hourAngle, $sda): float {
            [$ra, $dec] = $radec($lon_ecl);
            $H = $hourAngle($ra);
            $S = $sda($dec);
            return $H + $S + $f * (Math::PI - $S);
        };
        $solve = function (callable $fn, float $f, float $guessLon): float {
            $bestLon = $guessLon; $bestVal = INF;
            $scanSteps = 360; $step = Math::TWO_PI / $scanSteps;
            for ($k=0; $k<$scanSteps; $k++) {
                $lon = Math::normAngleRad($guessLon + ($k - $scanSteps/2) * $step);
                $v = $fn($lon, $f);
                $abs = abs($v);
                if ($abs < $bestVal) { $bestVal = $abs; $bestLon = $lon; }
                if ($bestVal < 1e-3) break;
            }
            $x = $bestLon;
            for ($i=0; $i<20; $i++) {
                $fx = $fn($x, $f);
                $dx = 1e-4;
                $fx1 = $fn(Math::normAngleRad($x + $dx), $f);
                $der = ($fx1 - $fx) / $dx;
                if (abs($der) < 1e-8) break;
                $x = Math::normAngleRad($x - $fx / $der);
                if (abs($fx) < 1e-8) break;
            }
            return Math::normAngleRad($x);
        };
        $guess12 = Math::normAngleRad(($asc + $mc) / 2.0 + 0.0);
        $guess11 = Math::normAngleRad(($asc + 2.0*$mc) / 3.0);
        $guess2  = Math::normAngleRad(($cusp[7] + $cusp[4]) / 2.0);
        $guess3  = Math::normAngleRad(($cusp[7] + 2.0*$cusp[4]) / 3.0);
        $cusp[12] = $solve($dDay, 1.0/3.0, $guess12);
        $cusp[11] = $solve($dDay, 2.0/3.0, $guess11);
        $cusp[2] = $solve($dNight, 1.0/3.0, $guess2);
        $cusp[3] = $solve($dNight, 2.0/3.0, $guess3);
        $cusp[5] = Math::normAngleRad($cusp[11] + Math::PI);
        $cusp[6] = Math::normAngleRad($cusp[12] + Math::PI);
        $cusp[8] = Math::normAngleRad($cusp[2] + Math::PI);
        $cusp[9] = Math::normAngleRad($cusp[3] + Math::PI);
        return $cusp;
    }
}
