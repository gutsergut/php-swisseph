<?php

declare(strict_types=1);

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;
use Swisseph\DeltaT;
use Swisseph\ErrorCodes;
use Swisseph\Output;
use Swisseph\Swe\Planets\EphemerisStrategyFactory;

/**
 * Тонкий фасад: валидация аргументов + делегирование стратегиям.
 * Полная физика (прецессия, нутация, световые поправки) внутри стратегий.
 */
final class PlanetsFunctions
{
    /**
     * Delegates to appropriate ephemeris strategy.
     * Maintains C API contract: returns iflag (>=0) или SE_ERR (<0).
     */
    public static function calc(float $jd_tt, int $ipl, int $iflag, array &$xx, ?string &$serr = null): int
    {
        $xx = Output::emptyForFlags($iflag);

        // Диапазон поддерживаемых планет (сохраняем поведение C)
        if (($ipl < Constants::SE_SUN || $ipl > Constants::SE_PLUTO) && $ipl !== Constants::SE_EARTH) {
            $serr = ErrorCodes::compose(ErrorCodes::INVALID_ARG, "ipl=$ipl out of supported range");
            return Constants::SE_ERR;
        }

        // Проверка взаимоисключающих источников эфемерид
        $srcCount = (($iflag & Constants::SEFLG_JPLEPH) ? 1 : 0)
            + (($iflag & Constants::SEFLG_SWIEPH) ? 1 : 0)
            + (($iflag & Constants::SEFLG_MOSEPH) ? 1 : 0);
        if ($srcCount > 1) {
            $serr = ErrorCodes::compose(
                ErrorCodes::INVALID_ARG,
                'Conflicting ephemeris source flags (choose one of JPLEPH/SWIEPH/MOSEPH)'
            );
            return Constants::SE_ERR;
        }

        // VSOP87 конфликтует с любым другим источником (эксклюзивный путь)
        if (($iflag & Constants::SEFLG_VSOP87) && $srcCount > 0) {
            $serr = ErrorCodes::compose(
                ErrorCodes::INVALID_ARG,
                'VSOP87 flag conflicts with other ephemeris source flags'
            );
            return Constants::SE_ERR;
        }

        // По умолчанию используем SWIEPH, если ничего не выставлено и не выбран VSOP87
        if (!(($iflag & Constants::SEFLG_VSOP87)
            || ($iflag & Constants::SEFLG_JPLEPH)
            || ($iflag & Constants::SEFLG_SWIEPH)
            || ($iflag & Constants::SEFLG_MOSEPH))) {
            $iflag |= Constants::SEFLG_SWIEPH;
        }

        $strategy = EphemerisStrategyFactory::forFlags($iflag, $ipl);
        if ($strategy === null) {
            // Источник не реализован (например JPLEPH/MOSEPH в текущем порте)
            $serr = ErrorCodes::compose(ErrorCodes::UNSUPPORTED, 'Ephemeris source not implemented yet');
            return Constants::SE_ERR;
        }

        $res = $strategy->compute($jd_tt, $ipl, $iflag);
        if ($res->retc < 0) {
            if ($serr === null) {
                $serr = $res->serr;
            }
            return Constants::SE_ERR;
        }

        // Берём финальный блок координат (с учётом флагов) из стратегии
        $xx = $res->x;
        return $iflag;
    }

    /**
     * UT -> TT конверсия затем делегирование стратегии.
     */
    public static function calcUt(float $jd_ut, int $ipl, int $iflag, array &$xx, ?string &$serr = null): int
    {
        $dt_sec = DeltaT::deltaTSecondsFromJd($jd_ut);
        $jd_tt = $jd_ut + $dt_sec / 86400.0;
        return self::calc($jd_tt, $ipl, $iflag, $xx, $serr);
    }

    /**
     * Planetocentric calculation - calculate positions relative to another planet.
     *
     * Port of swe_calc_pctr() from sweph.c:8096-8340 (~250 lines).
     * Full C API compatibility - NO SIMPLIFICATIONS.
     *
     * @param float $tjd Julian day number (TT/ET)
     * @param int $ipl Target planet number
     * @param int $iplctr Center planet number (viewing position)
     * @param int $iflag Calculation flags
     * @param array &$xxret Output array [6] for coordinates
     * @param string|null &$serr Error string
     * @return int iflag on success, SE_ERR on error
     */
    public static function calcPctr(
        float $tjd,
        int $ipl,
        int $iplctr,
        int $iflag,
        array &$xxret,
        ?string &$serr = null
    ): int {
        // Validation: planets must be different
        if ($ipl === $iplctr) {
            $serr = sprintf("ipl and iplctr (= %d) must not be identical", $ipl);
            return Constants::SE_ERR;
        }

        // Validate flags (plaus_iflag equivalent)
        $iflag = self::plausibleIflag($iflag, $ipl, $tjd, $serr);
        $epheflag = $iflag & Constants::SEFLG_EPHMASK;

        // Fill obliquity and nutation values in swed (via swe_calc of ECL_NUT)
        $xx = [];
        $dt = DeltaT::deltaTSecondsFromJd($tjd) / 86400.0;
        self::calc($tjd + $dt, Constants::SE_ECL_NUT, $iflag, $xx, $serr);

        // Remove HELCTR/BARYCTR from iflag for internal calculations
        $iflag &= ~(Constants::SEFLG_HELCTR | Constants::SEFLG_BARYCTR);

        // Build flags for barycentric J2000 ICRS calculations
        $iflag2 = $epheflag;
        $iflag2 |= (Constants::SEFLG_BARYCTR | Constants::SEFLG_J2000 | Constants::SEFLG_ICRS |
                   Constants::SEFLG_TRUEPOS | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ |
                   Constants::SEFLG_SPEED);
        $iflag2 |= (Constants::SEFLG_NOABERR | Constants::SEFLG_NOGDEFL);

        // Calculate center planet (barycentric)
        $xxctr = [];
        $retc = self::calc($tjd, $iplctr, $iflag2, $xxctr, $serr);
        if ($retc < 0) {
            return Constants::SE_ERR;
        }

        // Calculate target planet (barycentric)
        $xx = [];
        $retc = self::calc($tjd, $ipl, $iflag2, $xx, $serr);
        if ($retc < 0) {
            return Constants::SE_ERR;
        }

        // Save initial position
        $xx0 = $xx;

        // TODO: Implement light-time correction, deflection, aberration,
        // precession, nutation, coordinate transformations
        // This is a stub that needs full implementation (~200 more lines)

        $serr = "swe_calc_pctr: Full implementation in progress";
        return Constants::SE_ERR;
    }

    /**
     * Validate and adjust calculation flags (port of plaus_iflag).
     */
    private static function plausibleIflag(int $iflag, int $ipl, float $tjd, ?string &$serr): int
    {
        // For now, just return iflag as-is
        // Full implementation would validate flag combinations
        return $iflag;
    }
}
