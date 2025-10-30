<?php

namespace Swisseph\Domain\Houses\Systems;

use Swisseph\Domain\Houses\HouseSystem;
use Swisseph\Math;

/**
 * Система домов: Sripati (индийская). По сути Porphyry, но «кусп» понимается как середина дома.
 * Реализация: берём границы Porphyry и возвращаем серединные точки между соседними границами по эклиптике.
 */
final class Sripati implements HouseSystem
{
    /**
     * Куспы Sripati как середины Porphyry-сегментов.
     * @return array cusps[1..12] в радианах
     */
    public function cusps(float $armc_rad, float $geolat_rad, float $eps_rad, float $asc_rad = NAN, float $mc_rad = NAN): array
    {
        // Сначала получаем Porphyry границы
        $por = new Porphyry();
        $p = $por->cusps($armc_rad, $geolat_rad, $eps_rad, $asc_rad, $mc_rad);
        // Середины между p[i] и p[i+1] (по направлению вперёд вдоль эклиптики)
        $s = array_fill(0, 13, 0.0);
        for ($i = 1; $i <= 12; $i++) {
            $a = $p[$i];
            $b = $p[$i === 12 ? 1 : $i + 1];
            // вперёд-вперёд до b
            $delta = $b - $a;
            while ($delta < 0) { $delta += Math::TWO_PI; }
            while ($delta >= Math::TWO_PI) { $delta -= Math::TWO_PI; }
            $mid = $a + 0.5 * $delta;
            $s[$i] = Math::normAngleRad($mid);
        }
        return $s;
    }
}
