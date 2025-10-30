<?php

namespace Swisseph\Domain\Houses\Systems;

use Swisseph\Houses;
use Swisseph\Math;

/**
 * Гокленовские сектора ('G'): 36 границ, нумерация по часовой стрелке.
 * Упрощённая реализация: в каждом квадранте (MC→Desc→IC→Asc→MC) делим
 * эклиптическую дугу на 9 равных частей по направлению по часовой стрелке.
 * Примечание: В SE доступно несколько методов для позиций; здесь реализуются
 * только границы на эклиптике как равномерное деление по эклиптической дуге квадранта.
 */
final class Gauquelin
{
    /**
     * Вычисляет 36 границ секторов в радианах, индексы 1..36.
     */
    public static function cusps36(
        float $armc_rad,
        float $geolat_rad,
        float $eps_rad,
        float $asc_rad = NAN,
        float $mc_rad = NAN
    ): array {
        if (!is_finite($asc_rad) || !is_finite($mc_rad)) {
            [$asc, $mc] = Houses::ascMcFromArmc($armc_rad, $geolat_rad, $eps_rad);
        } else {
            $asc = $asc_rad; $mc = $mc_rad;
        }
        $asc = Math::normAngleRad($asc);
        $mc  = Math::normAngleRad($mc);
        $desc = Math::normAngleRad($asc + Math::PI);
        $ic   = Math::normAngleRad($mc + Math::PI);

        // Вспомогательные в градусах для удобства деления по часовой стрелке
        $cwDelta = function (float $from_deg, float $to_deg): float {
            // величина дуги по часовой стрелке от from к to в градусах [0,360)
            $d = $from_deg - $to_deg;
            while ($d < 0) { $d += 360.0; }
            while ($d >= 360.0) { $d -= 360.0; }
            return $d;
        };
        $stepCW = function (float $start_deg, float $delta_deg): float {
            // смещение по часовой стрелке (уменьшение долготы)
            $x = $start_deg - $delta_deg;
            while ($x < 0) { $x += 360.0; }
            while ($x >= 360.0) { $x -= 360.0; }
            return $x;
        };

        $mc_deg = Math::radToDeg($mc);
        $desc_deg = Math::radToDeg($desc);
        $ic_deg = Math::radToDeg($ic);
        $asc_deg = Math::radToDeg($asc);

        $edges = array_fill(0, 37, 0.0);
        $idx = 1;

        // Кв. MC -> Desc (по часовой стрелке)
        $q1 = $cwDelta($mc_deg, $desc_deg);
        for ($i = 0; $i < 9; $i++, $idx++) {
            $edges[$idx] = $stepCW($mc_deg, $q1 * $i / 9.0);
        }
        // Кв. Desc -> IC
        $q2 = $cwDelta($desc_deg, $ic_deg);
        for ($i = 1; $i <= 9; $i++, $idx++) {
            $edges[$idx] = $stepCW($desc_deg, $q2 * ($i - 1) / 9.0);
        }
        // Кв. IC -> Asc
        $q3 = $cwDelta($ic_deg, $asc_deg);
        for ($i = 1; $i <= 9; $i++, $idx++) {
            $edges[$idx] = $stepCW($ic_deg, $q3 * ($i - 1) / 9.0);
        }
        // Кв. Asc -> MC
        $q4 = $cwDelta($asc_deg, $mc_deg);
        for ($i = 1; $i <= 9 && $idx <= 36; $i++, $idx++) {
            $edges[$idx] = $stepCW($asc_deg, $q4 * ($i - 1) / 9.0);
        }

        // Перевод в радианы и нормализация
        for ($k = 1; $k <= 36; $k++) {
            $edges[$k] = Math::degToRad(Math::normAngleDeg($edges[$k]));
        }
        return $edges; // индексы [1..36]
    }
}
