<?php

declare(strict_types=1);

namespace Swisseph\Swe\Planets;

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;
use Swisseph\Swe\Jpl\JplConstants;
use Swisseph\Swe\Jpl\JplEphemeris;

/**
 * JPL Ephemeris strategy for planet calculations
 * Uses DE405, DE431, DE440, DE441, etc.
 *
 * This strategy fetches barycentric equatorial J2000 coordinates from JPL ephemeris
 * and passes them through the same apparent position pipeline as SwephStrategy.
 *
 * Port of jplplan() from sweph.c:1987-2103
 *
 * Key insight: JPL returns coordinates in ICRS (equatorial), same as Swiss Ephemeris
 * files store coordinates in equatorial J2000. The pipeline (app_pos_etc_plan +
 * app_pos_rest) handles all transformations including equatorial→ecliptic conversion.
 */
class JplStrategy implements EphemerisStrategy
{
    private ?JplEphemeris $jpl = null;
    private bool $initialized = false;

    public function supports(int $ipl, int $iflag): bool
    {
        // Support Sun, Moon, Mercury-Pluto
        if ($ipl >= Constants::SE_SUN && $ipl <= Constants::SE_PLUTO) {
            return true;
        }
        if ($ipl === Constants::SE_EARTH) {
            return true;
        }
        // Mean nodes not supported by JPL
        if ($ipl === Constants::SE_MEAN_NODE || $ipl === Constants::SE_TRUE_NODE) {
            return false;
        }
        return false;
    }

    public function compute(float $jdTt, int $ipl, int $iflag): StrategyResult
    {
        $swed = SwedState::getInstance();
        $serr = null;

        // Initialize JPL ephemeris if needed
        if (!$this->initialized) {
            $initResult = $this->initializeJpl($swed);
            if ($initResult !== null) {
                return $initResult;
            }
        }

        // Map SE planet to internal index and JPL body index
        $ipli = $this->seToIpli($ipl);
        $jplTarget = $this->seToJpl($ipl);
        if ($jplTarget < 0 || $ipli < 0) {
            return StrategyResult::err(
                sprintf('Planet %d not supported by JPL ephemeris', $ipl),
                Constants::SE_ERR
            );
        }

        // Get references to plan_data structures
        $pdp = &$swed->pldat[$ipli];
        $pedp = &$swed->pldat[SwephConstants::SEI_EARTH];
        $psdp = &$swed->pldat[SwephConstants::SEI_SUNBARY];

        // Check if already computed for this date with JPL
        if ($pdp->teval === $jdTt && $pdp->iephe === Constants::SEFLG_JPLEPH) {
            // Already computed, use cached data
            // But we still need to run through the pipeline for output format
        } else {
            // Fetch coordinates from JPL ephemeris
            $retc = $this->fetchJplCoordinates($jdTt, $ipl, $ipli, $jplTarget, $iflag, $serr);
            if ($retc !== Constants::SE_OK) {
                return StrategyResult::err($serr ?? 'JPL fetch error', $retc);
            }
        }

        // Special handling for Moon
        if ($ipl === Constants::SE_MOON) {
            return $this->computeMoon($jdTt, $iflag, $serr);
        }

        // Special handling for Sun
        // Geocentric Sun = -geocentric Earth (Earth relative to Sun)
        // In C: app_pos_etc_sun() handles this
        if ($ipl === Constants::SE_SUN) {
            return $this->computeSun($jdTt, $iflag, $serr);
        }

        // SE_SUN + BARYCTR: special path like SwephStrategy
        if ($ipl === Constants::SE_SUN && ($iflag & Constants::SEFLG_BARYCTR)) {
            $final = PlanetApparentPipeline::appPosEtcSbar($jdTt, $iflag);
            return StrategyResult::okFinal($final);
        }

        // Use the same pipeline as SwephStrategy
        // pdp->x now contains barycentric equatorial J2000 coordinates
        $xpret = $pdp->x;

        $final = PlanetApparentPipeline::computeFinal($jdTt, $ipl, $iflag, $xpret);
        return StrategyResult::okFinal($final);
    }

    /**
     * Initialize JPL ephemeris file
     */
    private function initializeJpl(SwedState $swed): ?StrategyResult
    {
        $this->jpl = JplEphemeris::getInstance();

        $jplFile = $swed->getJplFile();
        if (empty($jplFile)) {
            $jplFile = 'de441.eph';  // Default
        }

        $ss = [];
        $serr = null;
        $ret = $this->jpl->open($ss, $jplFile, $swed->getEphePath(), $serr);

        if ($ret !== JplConstants::OK) {
            return StrategyResult::err(
                $serr ?? 'Could not open JPL ephemeris file',
                Constants::SE_ERR
            );
        }

        $this->initialized = true;
        return null;  // Success
    }

    /**
     * Fetch coordinates from JPL and store in pldat
     * Port of jplplan() from sweph.c:1987-2103
     *
     * JPL returns coordinates in ICRS (equatorial J2000 frame).
     * We store them directly without conversion - the pipeline handles
     * all transformations including equatorial→ecliptic.
     */
    private function fetchJplCoordinates(
        float $jdTt,
        int $ipl,
        int $ipli,
        int $jplTarget,
        int $iflag,
        ?string &$serr
    ): int {
        $swed = SwedState::getInstance();
        $pdp = &$swed->pldat[$ipli];
        $pedp = &$swed->pldat[SwephConstants::SEI_EARTH];
        $psdp = &$swed->pldat[SwephConstants::SEI_SUNBARY];

        // Determine what needs to be computed (C sweph.c:2017-2023)
        $doEarth = true;  // Almost always need Earth for geocentric
        $doSunbary = true;  // Need for heliocentric conversion

        // Moon uses Earth as center in JPL
        $ictr = ($ipl === Constants::SE_MOON) ? JplConstants::J_EARTH : JplConstants::J_SBARY;

        // 1. Get Earth barycentric if needed
        if ($doEarth && ($pedp->teval !== $jdTt || $pedp->iephe !== Constants::SEFLG_JPLEPH)) {
            $xpe = [];
            $ret = $this->jpl->pleph($jdTt, JplConstants::J_EARTH, JplConstants::J_SBARY, $xpe, $serr);
            if ($ret !== JplConstants::OK) {
                return Constants::SE_ERR;
            }
            // Store in pldat (equatorial J2000, no conversion!)
            $pedp->x = $xpe;
            $pedp->teval = $jdTt;
            $pedp->xflgs = -1;  // New light-time etc. required
            $pedp->iephe = Constants::SEFLG_JPLEPH;
        }

        // 2. Get Sun barycentric if needed
        if ($doSunbary && ($psdp->teval !== $jdTt || $psdp->iephe !== Constants::SEFLG_JPLEPH)) {
            $xps = [];
            $ret = $this->jpl->pleph($jdTt, JplConstants::J_SUN, JplConstants::J_SBARY, $xps, $serr);
            if ($ret !== JplConstants::OK) {
                return Constants::SE_ERR;
            }
            // Store in pldat (equatorial J2000, no conversion!)
            $psdp->x = $xps;
            $psdp->teval = $jdTt;
            $psdp->xflgs = -1;
            $psdp->iephe = Constants::SEFLG_JPLEPH;
        }

        // 3. Get planet position
        if ($ipli !== SwephConstants::SEI_EARTH && $ipli !== SwephConstants::SEI_SUNBARY) {
            $xp = [];
            $ret = $this->jpl->pleph($jdTt, $jplTarget, $ictr, $xp, $serr);
            if ($ret !== JplConstants::OK) {
                return Constants::SE_ERR;
            }
            // Store in pldat (equatorial J2000, no conversion!)
            $pdp->x = $xp;
            $pdp->teval = $jdTt;
            $pdp->xflgs = -1;
            $pdp->iephe = Constants::SEFLG_JPLEPH;
        }

        return Constants::SE_OK;
    }

    /**
     * Special handling for Moon
     */
    private function computeMoon(float $jdTt, int $iflag, ?string &$serr): StrategyResult
    {
        $swed = SwedState::getInstance();

        // Moon data is already stored in pldat by fetchJplCoordinates
        // Use MoonTransform for full apparent position
        $retc = \Swisseph\Swe\Moon\MoonTransform::appPosEtc($iflag, $serr);
        if ($retc !== Constants::SE_OK) {
            return StrategyResult::err($serr ?? 'Moon transform error', $retc);
        }

        $pdp = &$swed->pldat[SwephConstants::SEI_MOON];
        $offset = 0;
        if ($iflag & Constants::SEFLG_EQUATORIAL) {
            $offset = ($iflag & Constants::SEFLG_XYZ) ? 18 : 12;
        } else {
            $offset = ($iflag & Constants::SEFLG_XYZ) ? 6 : 0;
        }
        $out = [0, 0, 0, 0, 0, 0];
        for ($i = 0; $i < 6; $i++) {
            $out[$i] = $pdp->xreturn[$offset + $i];
        }
        return StrategyResult::okFinal($out);
    }

    /**
     * Special handling for Sun
     * Port of app_pos_etc_sun() from sweph.c
     *
     * For geocentric Sun we pass the Sun's barycentric position to the pipeline.
     * The pipeline will subtract Earth's position to get geocentric.
     */
    private function computeSun(float $jdTt, int $iflag, ?string &$serr): StrategyResult
    {
        $swed = SwedState::getInstance();
        $psdp = &$swed->pldat[SwephConstants::SEI_SUNBARY];

        // Pass Sun barycentric position to pipeline
        // The pipeline handles geocentric subtraction internally
        $xpret = $psdp->x;
        $final = PlanetApparentPipeline::computeFinal($jdTt, Constants::SE_SUN, $iflag, $xpret);
        return StrategyResult::okFinal($final);
    }

    /**
     * Map Swiss Ephemeris external planet ID to internal index (SEI_*)
     */
    private function seToIpli(int $ipl): int
    {
        return SwephConstants::PNOEXT2INT[$ipl] ?? -1;
    }

    /**
     * Map Swiss Ephemeris planet ID to JPL body ID
     */
    private function seToJpl(int $ipl): int
    {
        return JplConstants::SE_TO_JPL[$ipl] ?? -1;
    }
}
