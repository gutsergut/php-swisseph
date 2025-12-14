<?php

declare(strict_types=1);

namespace Swisseph\Swe\Planets;

use Swisseph\Constants;
use Swisseph\Moshier\MoshierPlanetCalculator;
use Swisseph\Moshier\MoshierConstants;
use Swisseph\SwephFile\SwedState;
use Swisseph\Domain\Moshier\MoshierMoon;

/**
 * Moshier semi-analytical ephemeris strategy.
 *
 * Provides planet positions using Moshier's analytical theories.
 * Does not require external ephemeris files.
 *
 * Accuracy:
 * - Planets: ~50" for inner planets, ~3-10" for outer planets
 * - Moon: ±7" longitude, ±5" latitude (-3000..+3000 AD)
 *
 * Full port of main_planet() SEFLG_MOSEPH case from sweph.c
 *
 * @see sweph.c main_planet(), swemplan.c swi_moshplan(), swemmoon.c swi_moshmoon()
 */
final class MoshierStrategy implements EphemerisStrategy
{
    /**
     * Map from external planet number (SE_*) to internal SEI_* index
     */
    private const PNOEXT2SEI = [
        Constants::SE_SUN => MoshierConstants::SEI_EARTH,  // Sun needs special handling
        Constants::SE_MOON => MoshierConstants::SEI_MOON,  // Moon uses MoshierMoon
        Constants::SE_MERCURY => MoshierConstants::SEI_MERCURY,
        Constants::SE_VENUS => MoshierConstants::SEI_VENUS,
        Constants::SE_EARTH => MoshierConstants::SEI_EARTH,
        Constants::SE_MARS => MoshierConstants::SEI_MARS,
        Constants::SE_JUPITER => MoshierConstants::SEI_JUPITER,
        Constants::SE_SATURN => MoshierConstants::SEI_SATURN,
        Constants::SE_URANUS => MoshierConstants::SEI_URANUS,
        Constants::SE_NEPTUNE => MoshierConstants::SEI_NEPTUNE,
        Constants::SE_PLUTO => MoshierConstants::SEI_PLUTO,
    ];

    public function supports(int $ipl, int $iflag): bool
    {
        if (!($iflag & Constants::SEFLG_MOSEPH)) {
            return false;
        }
        // Moshier supports Sun-Pluto, Moon and Earth
        return isset(self::PNOEXT2SEI[$ipl]);
    }

    public function compute(float $jd_tt, int $ipl, int $iflag): StrategyResult
    {
        if (!isset(self::PNOEXT2SEI[$ipl])) {
            return StrategyResult::err("Planet $ipl not supported in Moshier ephemeris", Constants::SE_ERR);
        }

        $ipli = self::PNOEXT2SEI[$ipl];
        $serr = null;

        // Moon has its own computation path
        if ($ipl === Constants::SE_MOON) {
            return $this->computeMoon($jd_tt, $iflag);
        }

        // Step 1: Call moshplan() with DO_SAVE=true
        // This stores heliocentric equatorial J2000 in SwedState->pldat[ipli]->x
        // and Earth in SwedState->pldat[SEI_EARTH]->x
        $xpret = null;  // Not needed, we read from SwedState
        $xeret = null;

        // For Sun, we compute Earth (ipli = SEI_EARTH)
        $ipliToCompute = ($ipl === Constants::SE_SUN) ? MoshierConstants::SEI_EARTH : $ipli;

        $ret = MoshierPlanetCalculator::moshplan($jd_tt, $ipliToCompute, true, $xpret, $xeret, $serr);
        if ($ret < 0) {
            return StrategyResult::err($serr ?? 'Moshier computation error', Constants::SE_ERR);
        }

        // Step 2: Call appropriate apparent position function
        // From C: app_pos_etc_sun() for Sun, app_pos_etc_plan() for planets
        if ($ipl === Constants::SE_SUN) {
            // Sun uses special function (no light deflection, different geocentric conversion)
            $ret = MoshierApparentPipeline::appPosEtcSun($iflag, $serr);
            if ($ret < 0) {
                return StrategyResult::err($serr ?? 'Sun apparent position error', Constants::SE_ERR);
            }
            // Sun results stored in SEI_EARTH pdp
            $swed = SwedState::getInstance();
            $pdp = &$swed->pldat[MoshierConstants::SEI_EARTH];
        } else {
            // Regular planets
            $ret = MoshierApparentPipeline::appPosEtcPlan($ipli, $ipl, $iflag, $serr);
            if ($ret < 0) {
                return StrategyResult::err($serr ?? 'Apparent position error', Constants::SE_ERR);
            }
            $swed = SwedState::getInstance();
            $pdp = &$swed->pldat[$ipli];
        }

        return StrategyResult::okFinal($pdp->xreturn);
    }

    /**
     * Compute Moon position using MoshierMoon (ELP2000-85)
     *
     * @param float $jd_tt Julian day TT
     * @param int $iflag Calculation flags
     * @return StrategyResult
     */
    private function computeMoon(float $jd_tt, int $iflag): StrategyResult
    {
        $serr = null;
        $swed = SwedState::getInstance();
        $pdp = &$swed->pldat[MoshierConstants::SEI_MOON];

        // Ensure Earth is computed first (needed for geocentric conversion)
        $xpret = null;
        $xeret = null;
        $ret = MoshierPlanetCalculator::moshplan($jd_tt, MoshierConstants::SEI_EARTH, true, $xpret, $xeret, $serr);
        if ($ret < 0) {
            return StrategyResult::err($serr ?? 'Earth computation error for Moon', Constants::SE_ERR);
        }

        // Compute Moon using MoshierMoon
        $moon = new MoshierMoon();
        $xx = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];

        $ret = $moon->moshmoon($jd_tt, $xx, $serr);
        if ($ret < 0) {
            return StrategyResult::err($serr ?? 'Moon computation error', Constants::SE_ERR);
        }

        // Store in pdp->x for apparent position pipeline
        for ($i = 0; $i <= 5; $i++) {
            $pdp->x[$i] = $xx[$i];
        }
        $pdp->teval = $jd_tt;
        $pdp->xflgs = -1;  // Force recomputation

        // Call apparent position function for Moon
        $ret = MoshierApparentPipeline::appPosEtcMoon($iflag, $serr);
        if ($ret < 0) {
            return StrategyResult::err($serr ?? 'Moon apparent position error', Constants::SE_ERR);
        }

        return StrategyResult::okFinal($pdp->xreturn);
    }
}
