<?php

namespace Swisseph\Swe\Planets;

use Swisseph\Constants;

/**
 * Фабрика выбора источника по флагам ephemeris.
 * Приоритет: JPLEPH > VSOP87 > SWIEPH > MOSEPH.
 */
final class EphemerisStrategyFactory
{
    /**
     * Выбор стратегии на основе флагов и планеты.
     */
    public static function forFlags(int $iflag, int $ipl): ?EphemerisStrategy
    {
        // JPL Ephemeris - highest precision
        if ($iflag & Constants::SEFLG_JPLEPH) {
            $s = new JplStrategy();
            if ($s->supports($ipl, $iflag)) {
                return $s;
            }
            // Fallback to SWIEPH if JPL not available
            $sw = new SwephStrategy();
            if ($sw->supports($ipl, $iflag | Constants::SEFLG_SWIEPH)) {
                return $sw;
            }
            // Final fallback to Moshier
            $m = new MoshierStrategy();
            if ($m->supports($ipl, $iflag | Constants::SEFLG_MOSEPH)) {
                return $m;
            }
            return null;
        }

        if ($iflag & Constants::SEFLG_VSOP87) {
            $s = new Vsop87Strategy();
            if ($s->supports($ipl, $iflag)) {
                return $s;
            }
            // Fallback to SWIEPH for planets not supported by VSOP87 (e.g. Pluto)
            $sw = new SwephStrategy();
            if ($sw->supports($ipl, $iflag | Constants::SEFLG_SWIEPH)) {
                return $sw;
            }
            return null;
        }

        if ($iflag & Constants::SEFLG_SWIEPH) {
            $s = new SwephStrategy();
            return $s->supports($ipl, $iflag) ? $s : null;
        }

        if ($iflag & Constants::SEFLG_MOSEPH) {
            $s = new MoshierStrategy();
            return $s->supports($ipl, $iflag) ? $s : null;
        }

        return null;
    }
}
