<?php

namespace Swisseph\Domain\Houses\Systems;

use Swisseph\Domain\Houses\HouseSystem;
use Swisseph\Houses;
use Swisseph\Math;

/**
 * Система домов: Vehlow — равные дома с началом в 15° до Asc (сдвиг -15°),
 * то есть cusp1 = Asc - 15°, далее каждые 30°.
 */
final class Vehlow implements HouseSystem
{
    /**
     * Куспы Vehlow: Asc смещается на -15° перед делением равными 30°.
     * @return array cusps[1..12] в радианах
     */
    public function cusps(float $armc_rad, float $geolat_rad, float $eps_rad, float $asc_rad = NAN, float $mc_rad = NAN): array
    {
        if (!is_finite($asc_rad)) {
            [$asc, ] = Houses::ascMcFromArmc($armc_rad, $geolat_rad, $eps_rad);
        } else {
            $asc = $asc_rad;
        }
        $start = Math::normAngleRad($asc - (Math::PI / 12.0)); // -15°
        $cusps = array_fill(0, 13, 0.0);
        for ($k = 1; $k <= 12; $k++) {
            $angle = $start + ($k - 1) * (Math::PI / 6.0);
            $cusps[$k] = Math::normAngleRad($angle);
        }
        return $cusps;
    }
}
