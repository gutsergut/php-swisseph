<?php

namespace Swisseph\Domain\Houses\Systems;

use Swisseph\Domain\Houses\HouseSystem;
use Swisseph\Houses;
use Swisseph\Math;

/**
 * Система домов: Whole Sign (каждый дом = целый знак, начиная с знака Asc).
 */
final class WholeSign implements HouseSystem
{
    /**
     * Куспы Whole Sign: для k-го дома долгота = начало знака Asc + (k-1)*30°.
     * @return array cusps[1..12] в радианах
     */
    public function cusps(float $armc_rad, float $geolat_rad, float $eps_rad, float $asc_rad = NAN, float $mc_rad = NAN): array
    {
        if (!is_finite($asc_rad)) {
            [$asc, ] = Houses::ascMcFromArmc($armc_rad, $geolat_rad, $eps_rad);
        } else {
            $asc = $asc_rad;
        }
        $asc_deg = Math::normAngleDeg(Math::radToDeg($asc));
        $sign_start_deg = floor($asc_deg / 30.0) * 30.0;
        $cusps = array_fill(0, 13, 0.0);
        for ($k = 1; $k <= 12; $k++) {
            $deg = Math::normAngleDeg($sign_start_deg + ($k - 1) * 30.0);
            $cusps[$k] = Math::degToRad($deg);
        }
        return $cusps;
    }
}
