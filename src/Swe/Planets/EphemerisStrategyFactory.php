<?php

namespace Swisseph\Swe\Planets;

use Swisseph\Constants;

/**
 * Фабрика выбора источника по флагам ephemeris.
 * Приоритет: JPLEPH (TODO) > VSOP87 > SWIEPH > MOSEPH (TODO).
 */
final class EphemerisStrategyFactory
{
    /**
     * Выбор стратегии на основе флагов и планеты.
     */
    public static function forFlags(int $iflag, int $ipl): ?EphemerisStrategy
    {
        if ($iflag & Constants::SEFLG_VSOP87) {
            $s = new Vsop87Strategy();
            return $s->supports($ipl, $iflag) ? $s : null;
        }

        if ($iflag & Constants::SEFLG_SWIEPH) {
            $s = new SwephStrategy();
            return $s->supports($ipl, $iflag) ? $s : null;
        }

        // JPLEPH/MOSEPH пока не поддержаны
        return null;
    }
}
