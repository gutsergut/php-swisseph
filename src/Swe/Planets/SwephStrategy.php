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
        // Support main planets, Earth, main belt asteroids (Chiron through Vesta),
        // planetary moons (SE_PLMOON_OFFSET + moon_id), and numbered asteroids (SE_AST_OFFSET + asteroid_number)
        return ($ipl >= Constants::SE_SUN && $ipl <= Constants::SE_PLUTO)
            || $ipl === Constants::SE_EARTH
            || ($ipl >= Constants::SE_CHIRON && $ipl <= Constants::SE_VESTA)
            || ($ipl > Constants::SE_PLMOON_OFFSET && $ipl < Constants::SE_AST_OFFSET) // Planetary moons
            || $ipl > Constants::SE_AST_OFFSET;  // Numbered asteroids
    }

    public function compute(float $jd_tt, int $ipl, int $iflag): StrategyResult
    {
        // Determine internal planet index (ipli) and file number (ifno)
        // Based on sweph.c ~1018-1050 logic for minor planets

        // MPC constants for main belt asteroids
        $MPC_VESTA = 4;  // Vesta is asteroid #4 in MPC numbering

        // Numbered asteroids (SE_AST_OFFSET + asteroid_number)
        if ($ipl > Constants::SE_AST_OFFSET) {
            $asteroidNum = $ipl - Constants::SE_AST_OFFSET;

            // Per C sweph.c ~1030: asteroids 1-4 can be remapped to SEI_CERES..SEI_VESTA
            if ($asteroidNum >= 1 && $asteroidNum <= $MPC_VESTA) {
                // Map to internal indices for Ceres, Pallas, Juno, Vesta
                $ipli = SwephConstants::SEI_CERES + $asteroidNum - 1;
                $ifno = SwephConstants::SEI_FILE_MAIN_AST;
            } else {
                // All other numbered asteroids use SEI_ANYBODY
                $ipli = SwephConstants::SEI_ANYBODY;
                $ifno = SwephConstants::SEI_FILE_ANY_AST;
            }
        } elseif ($ipl > Constants::SE_PLMOON_OFFSET && $ipl < Constants::SE_AST_OFFSET) {
            // Planetary moons (SE_PLMOON_OFFSET + moon_id)
            // Per C sweph.c:426-427, 1046, 2194: planetary moons use SEI_ANYBODY and SEI_FILE_ANY_AST
            // Files are stored in sat/ subdirectory: sat/sepm9501.se1
            $ipli = SwephConstants::SEI_ANYBODY;
            $ifno = SwephConstants::SEI_FILE_ANY_AST;
        } elseif ($ipl >= Constants::SE_CHIRON && $ipl <= Constants::SE_VESTA) {
            // Main belt asteroids (Chiron, Pholus, Ceres, Pallas, Juno, Vesta)
            if (!isset(SwephConstants::PNOEXT2INT[$ipl])) {
                return StrategyResult::err("Asteroid ipl=$ipl not in mapping", Constants::SE_ERR);
            }
            $ipli = SwephConstants::PNOEXT2INT[$ipl];
            $ifno = SwephConstants::SEI_FILE_MAIN_AST;
        } else {
            // Main planets and Earth
            if (!isset(SwephConstants::PNOEXT2INT[$ipl])) {
                return StrategyResult::err("Planet $ipl not supported in Swiss Ephemeris", Constants::SE_ERR);
            }
            $ipli = SwephConstants::PNOEXT2INT[$ipl];
            $ifno = SwephConstants::SEI_FILE_PLANET;
        }

        $xpret = [];
        $xperet = $xpsret = $xpmret = null;
        $retc = \Swisseph\SwephFile\SwephPlanCalculator::calculate(
            $jd_tt,
            $ipli,
            $ipl,
            $ifno,
            $iflag,
            true,
            $xpret,
            $xperet,
            $xpsret,
            $xpmret,
            $serr
        );

        if (getenv('DEBUG_VENUS') && $ipl === Constants::SE_VENUS) {
            error_log(sprintf("[SwephStrategy] Venus: iflag=%d, xpret=[%.15f,%.15f,%.15f,%.15f,%.15f,%.15f]",
                $iflag, $xpret[0] ?? 0, $xpret[1] ?? 0, $xpret[2] ?? 0,
                $xpret[3] ?? 0, $xpret[4] ?? 0, $xpret[5] ?? 0));
        }

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

        // SE_SUN + BARYCTR: специальный путь app_pos_etc_sbar
        if ($ipl === Constants::SE_SUN && ($iflag & Constants::SEFLG_BARYCTR)) {
            $final = PlanetApparentPipeline::appPosEtcSbar($jd_tt, $iflag);
            return StrategyResult::okFinal($final);
        }

        // Общий пайплайн видимого результата
        $final = PlanetApparentPipeline::computeFinal($jd_tt, $ipl, $iflag, $xpret);
        return StrategyResult::okFinal($final);
    }
}
