<?php

namespace Swisseph\Domain\Houses\Systems;

use Swisseph\Domain\Houses\HouseSystem;
use Swisseph\Houses;
use Swisseph\Math;

/**
 * Система домов: Equal (равные дома от Asc по 30°).
 */
final class Equal implements HouseSystem
{
    /**
     * Вычисляет куспы для системы Equal.
     *
     * @param float $armc_rad ARMC в радианах
     * @param float $geolat_rad географическая широта в радианах
     * @param float $eps_rad наклон эклиптики в радианах
     * @param float $asc_rad Asc в радианах (если уже известен), иначе NAN
     * @param float $mc_rad MC в радианах (если уже известен), иначе NAN
     * @return array cusps[1..12] в радианах; индекс 0 не используется
     */
    public function cusps(float $armc_rad, float $geolat_rad, float $eps_rad, float $asc_rad = NAN, float $mc_rad = NAN): array
    {
        if (!is_finite($asc_rad) || !is_finite($mc_rad)) {
            [$asc, ] = Houses::ascMcFromArmc($armc_rad, $geolat_rad, $eps_rad);
        } else {
            $asc = $asc_rad;
        }
        // equalCusps inline: cusp k = Asc + (k-1)*30°
        $cusps = array_fill(0, 13, 0.0);
        for ($k = 1; $k <= 12; $k++) {
            $angle = $asc + ($k - 1) * (Math::PI / 6.0);
            $cusps[$k] = Math::normAngleRad($angle);
        }
        return $cusps;
    }
}
