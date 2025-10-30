<?php

namespace Swisseph\Domain\Houses\Support;

use Swisseph\Math;

/**
 * Постобработка массивов куспов: заполнение оппозиций и фиксация базовых куспов.
 */
final class CuspPostprocessor
{
    /**
     * Заполнить противоположные куспы автоматически.
     * Принимает массив с индексами [1..12], возвращает новый массив.
     */
    public static function withOpposites(array $cusps): array
    {
        $c = $cusps;
        if (isset($c[1]))  { $c[7]  = Math::normAngleRad(($c[1] ?? 0.0) + Math::PI); }
        if (isset($c[10])) { $c[4]  = Math::normAngleRad(($c[10] ?? 0.0) + Math::PI); }
        if (isset($c[2]))  { $c[8]  = Math::normAngleRad(($c[2] ?? 0.0) + Math::PI); }
        if (isset($c[3]))  { $c[9]  = Math::normAngleRad(($c[3] ?? 0.0) + Math::PI); }
        if (isset($c[11])) { $c[5]  = Math::normAngleRad(($c[11] ?? 0.0) + Math::PI); }
        if (isset($c[12])) { $c[6]  = Math::normAngleRad(($c[12] ?? 0.0) + Math::PI); }
        return $c;
    }

    /**
     * Принудительно выставить cusp1=Asc и cusp10=MC (если известны).
     */
    public static function forceAscMc(array $cusps, ?float $asc = null, ?float $mc = null): array
    {
        if ($asc !== null && is_finite($asc)) {
            $cusps[1] = Math::normAngleRad($asc);
        }
        if ($mc !== null && is_finite($mc)) {
            $cusps[10] = Math::normAngleRad($mc);
        }
        return $cusps;
    }
}
