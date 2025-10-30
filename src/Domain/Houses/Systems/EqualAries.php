<?php

namespace Swisseph\Domain\Houses\Systems;

use Swisseph\Domain\Houses\HouseSystem;
use Swisseph\Math;

/**
 * Система домов: Equal/1=Aries ('N').
 * Куспы фиксированы по знакам зодиака: 0°, 30°, 60° ... 330° вне зависимости от Asc/MC.
 */
final class EqualAries implements HouseSystem
{
    /**
     * Возвращает куспы домов в радианах.
     * @return array cusps[1..12]
     */
    public function cusps(
        float $armc_rad,
        float $geolat_rad,
        float $eps_rad,
        float $asc_rad = NAN,
        float $mc_rad = NAN
    ): array {
        $cusps = array_fill(0, 13, 0.0);
        for ($i = 1; $i <= 12; $i++) {
            $deg = ($i - 1) * 30.0;
            $cusps[$i] = Math::degToRad($deg);
        }
        return $cusps;
    }
}
