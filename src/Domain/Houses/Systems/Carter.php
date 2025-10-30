<?php

namespace Swisseph\Domain\Houses\Systems;

use Swisseph\Domain\Houses\HouseSystem;
use Swisseph\Math;

/**
 * Система домов: Carter "Poli-Equatorial" ('F').
 * Определение (swisseph): экватор делится на 12 равных частей, начиная с
 * прямого восхождения точки Asc (RA_asc). Затем ищутся точки эклиптики,
 * у которых RA равны этим делениям. Примечание: MC отличается от 10-го куспа.
 *
 * В данной реализации кусп 1 совпадает с Asc по долготе эклиптики.
 */
final class Carter implements HouseSystem
{
    /**
     * Возвращает куспы домов в радианах (индексы 1..12).
     * @param float $armc_rad   ARMC (не используется напрямую)
     * @param float $geolat_rad географ. широта (не используется)
     * @param float $eps_rad    наклонение эклиптики (рад)
     * @param float $asc_rad    долгота Asc на эклиптике (рад)
     * @param float $mc_rad     долгота MC на эклиптике (рад)
     * @return array            cusps[1..12] в радианах
     */
    public function cusps(
        float $armc_rad,
        float $geolat_rad,
        float $eps_rad,
        float $asc_rad = NAN,
        float $mc_rad = NAN
    ): array {
        $cusps = array_fill(0, 13, 0.0);

        // RA Asc из эклиптической долготы Asc и наклонения эклиптики
        // Формула: ra = atan2(sin(lambda)*cos(eps), cos(lambda))
        $ra_asc = atan2(sin($asc_rad) * cos($eps_rad), cos($asc_rad));

        // Строим 12 делений RA начиная с RA_asc через 30°
        $ra_deg = Math::radToDeg($ra_asc);
        for ($i = 1; $i <= 12; $i++) {
            $alpha = Math::degToRad(Math::normAngleDeg($ra_deg + 30.0 * ($i - 1)));
            // Точка на экваторе с декл. 0 и данным RA
            $x_eq = cos($alpha);
            $y_eq = sin($alpha);
            $z_eq = 0.0;
            // Переход к эклиптике: вращение на +eps вокруг оси X
            $x_ecl = $x_eq;
            $y_ecl = $y_eq * cos($eps_rad) - $z_eq * sin($eps_rad);
            // $z_ecl = $y_eq * sin($eps_rad) + $z_eq * cos($eps_rad); // не требуется для долготы
            $lon = atan2($y_ecl, $x_ecl);
            $cusps[$i] = Math::normAngleRad($lon);
        }
        return $cusps;
    }
}
