<?php

namespace Swisseph\Domain\Houses\Systems;

use Swisseph\Domain\Houses\HouseSystem;
use Swisseph\Houses;
use Swisseph\Math;

/**
 * Система домов: Equal (MC) — равные дома от MC по 30° по эклиптике.
 * cusp10 = MC; cusp11 = MC + 30°; cusp12 = MC + 60°; cusp1 = MC + 90°; ...
 */
final class EqualMc implements HouseSystem
{
    /**
     * Куспы Equal/MC: равные 30° от MC по возрастанию долгот.
     * @return array cusps[1..12] в радианах
     */
    public function cusps(float $armc_rad, float $geolat_rad, float $eps_rad, float $asc_rad = NAN, float $mc_rad = NAN): array
    {
        if (!is_finite($mc_rad)) {
            [, $mc] = Houses::ascMcFromArmc($armc_rad, $geolat_rad, $eps_rad);
        } else {
            $mc = $mc_rad;
        }
        $cusps = array_fill(0, 13, 0.0);
        for ($i = 1; $i <= 12; $i++) {
            // смещение: 1->+90°, 2->+120° ... 10->+0°
            $deg = ((($i + 2) % 12) * 30.0);
            $cusps[$i] = Math::normAngleRad($mc + Math::degToRad($deg));
        }
        return $cusps;
    }
}
