<?php

declare(strict_types=1);

namespace Swisseph\Swe\Planets;

use Swisseph\Constants;
use Swisseph\ErrorCodes;
use Swisseph\SwephFile\SwephConstants;

/**
 * Стратегия, инкапсулирующая путь SWIEPH (оригинальный sweplan + app_pos_rest).
 * Перенос из прежнего монолита PlanetsFunctions без упрощений.
 */
final class SwephStrategy implements EphemerisStrategy
{
    public function supports(int $ipl, int $iflag): bool
    {
        if (!($iflag & Constants::SEFLG_SWIEPH)) {
            return false;
        }
        return ($ipl >= Constants::SE_SUN && $ipl <= Constants::SE_PLUTO) || $ipl === Constants::SE_EARTH;
    }

    public function compute(float $jd_tt, int $ipl, int $iflag): StrategyResult
    {
        // Маппинг внешнего номера
        if (!isset(SwephConstants::PNOEXT2INT[$ipl])) {
            return StrategyResult::err("Planet $ipl not supported in Swiss Ephemeris", Constants::SE_ERR);
        }
        $ipli = SwephConstants::PNOEXT2INT[$ipl];

        $xpret = [];
        $xperet = $xpsret = $xpmret = null;
        $retc = \Swisseph\SwephFile\SwephPlanCalculator::calculate(
            $jd_tt,
            $ipli,
            $ipl,
            SwephConstants::SEI_FILE_PLANET,
            $iflag,
            true,
            $xpret,
            $xperet,
            $xpsret,
            $xpmret,
            $serr
        );
        if ($retc < 0) {
            return StrategyResult::err($serr ?? 'sweplan error', $retc);
        }

        // Луна: полный специализированный путь
        if ($ipl === Constants::SE_MOON) {
            $retc = \Swisseph\Swe\Moon\MoonTransform::appPosEtc($iflag, $serr);
            if ($retc !== Constants::SE_OK) {
                return StrategyResult::err($serr ?? 'Moon transform error', $retc);
            }
            $swed = \Swisseph\SwephFile\SwedState::getInstance();
            $pdp = &$swed->pldat[SwephConstants::SEI_MOON];
            $offset = 0;
            if ($iflag & Constants::SEFLG_EQUATORIAL) {
                $offset = ($iflag & Constants::SEFLG_XYZ) ? 18 : 12;
            } else {
                $offset = ($iflag & Constants::SEFLG_XYZ) ? 6 : 0;
            }
            $out = [0,0,0,0,0,0];
            for ($i=0;$i<6;$i++){ $out[$i] = $pdp->xreturn[$offset+$i]; }
            return StrategyResult::okFinal($out);
        }

        // Общий пайплайн видимого результата
        $final = PlanetApparentPipeline::computeFinal($jd_tt, $ipl, $iflag, $xpret);
        return StrategyResult::okFinal($final);
    }
}
