<?php

namespace Swisseph\Domain\Houses\Systems;

use Swisseph\Coordinates;
use Swisseph\Domain\Houses\HouseSystem;
use Swisseph\Math;

/**
 * Система домов: Meridian / Axial rotation ('X').
 * Определение: долготы на эклиптике таких точек, чья прямое восхождение (RA)
 * равно ARMC + n*30°. Это эквивалентно выбору точек на экваторе через шаг RA=30°
 * с последующей проекцией обратно на эклиптику.
 */
final class Meridian implements HouseSystem
{
    /**
     * Возвращает куспы домов в радианах.
     * @param float $armc_rad ARMC в рад
     * @param float $geolat_rad широта (не используется напрямую)
     * @param float $eps_rad наклонение эклиптики
     */
    public function cusps(
        float $armc_rad,
        float $geolat_rad,
        float $eps_rad,
        float $asc_rad = NAN,
        float $mc_rad = NAN
    ): array
    {
        $cusps = array_fill(0, 13, 0.0);
        // Базовый RA стартует с ARMC и инкрементируется на 30° (= pi/6 рад) для каждой точки.
        $ra = Math::radToDeg($armc_rad); // работаем в градусах для преобразований
        $eps_deg = Math::radToDeg($eps_rad);
        for ($i = 1; $i <= 12; $i++) {
            // вычисляем RA точки на эклиптике: RA = ra, Dec = 0 на экваторе, затем преобразуем в эклиптическую долготу
            $ra = Math::normAngleDeg($ra + 30.0);
            // Преобразуем точку на экваторе с заданным RA в эклиптическую долготу.
            // Экватор: деклинация 0 => прямоугольные экваториальные координаты с единичным радиусом
            $ra_rad = Math::degToRad($ra);
            $x_eq = cos($ra_rad);
            $y_eq = sin($ra_rad);
            $z_eq = 0.0;
            // Вращение к эклиптике на +eps вокруг оси X
            $x_ecl = $x_eq;
            $y_ecl = $y_eq * cos($eps_rad) - $z_eq * sin($eps_rad);
            $z_ecl = $y_eq * sin($eps_rad) + $z_eq * cos($eps_rad);
            // Эклиптическая долгота
            $lon = atan2($y_ecl, $x_ecl);
            $cusps[($i + 9) % 12 + 1] = Math::normAngleRad($lon);
        }
        return $cusps;
    }
}
