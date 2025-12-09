<?php

namespace Swisseph\Swe\Planets;

use Swisseph\Constants;

/**
 * Контракт источника исходных координат планеты.
 * Стратегия возвращает либо базовый вектор (барицентрический J2000, xyz [+v]),
 * либо финальный результат (уже упакованный согласно флагам), что позволяет
 * Moon/Sun особым путям обойти общий пайплайн без дублирования.
 */
interface EphemerisStrategy
{
    /**
     * @return bool true если стратегия может обработать пару (ipl, iflag)
     */
    public function supports(int $ipl, int $iflag): bool;

    /**
     * Рассчитать исходный вектор для дальнейших преобразований или финальный результат.
     *
     * Контракт:
     * - Если kind === 'barycentric_j2000', то $x имеет длину 6: [x,y,z,dx,dy,dz] (AU, AU/day),
     *   система координат: эклиптическая J2000, центр: барицентр.
     * - Если kind === 'final', то $x — уже готовый выход swe_calc (6 значений) согласно $iflag.
     * - Возврат: Constants::SE_OK (0) при успехе, отрицательное при ошибке, $serr заполняется.
     *
     * Примечания:
     * - Стратегия может использовать SwedState/SwephPlanCalculator для прогрева кэша (Earth/Sunbary).
     */
    public function compute(float $jd_tt, int $ipl, int $iflag): StrategyResult;
}
