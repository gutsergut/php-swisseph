<?php

namespace Swisseph\Domain\Houses\Systems;

use Swisseph\Domain\Houses\HouseSystem;
use Swisseph\Houses;
use Swisseph\Math;

/**
 * Система домов: Morinus.
 * Определение: берутся точки на экваторе с прямым восхождением RA = ARMC + n*30°,
 * затем они преобразуются в эклиптические координаты и дают куспы домов.
 */
final class Morinus implements HouseSystem
{
    /**
     * Куспы Morinus: равные 30° от MC по возрастанию долгот.
     * @return array cusps[1..12] в радианах
     */
    public function cusps(
        float $armc_rad,
        float $geolat_rad,
        float $eps_rad,
        float $asc_rad = NAN,
        float $mc_rad = NAN
    ): array {
        // Следуем swisseph: работаем через RA на экваторе и обратное преобразование в эклиптику
        $cusps = array_fill(0, 13, 0.0);
        $ra = Math::radToDeg($armc_rad);
        for ($i = 1; $i <= 12; $i++) {
            $ra = Math::normAngleDeg($ra + 30.0);
            $ra_rad = Math::degToRad($ra);
            // Единичный вектор в экваториальных координатах при деклинации 0
            $x_eq = cos($ra_rad);
            $y_eq = sin($ra_rad);
            $z_eq = 0.0;
            // Переход к эклиптике (+eps вокруг X)
            $x_ecl = $x_eq;
            $y_ecl = $y_eq * cos($eps_rad) - $z_eq * sin($eps_rad);
            $z_ecl = $y_eq * sin($eps_rad) + $z_eq * cos($eps_rad);
            $lon = atan2($y_ecl, $x_ecl);
            // В swisseph индекс j = i + 10 (с оборачиванием)
            $j = $i + 10;
            if ($j > 12) {
                $j -= 12;
            }
            $cusps[$j] = Math::normAngleRad($lon);
        }
        return $cusps;
    }
}
