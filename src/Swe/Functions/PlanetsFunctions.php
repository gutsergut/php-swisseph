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
}
