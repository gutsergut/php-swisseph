<?php

declare(strict_types=1);

namespace Swisseph\SwephFile;

use Swisseph\Constants;

/**
 * Utility functions for Swiss Ephemeris file handling
 *
 * Port of internal helper functions from sweph.c
 */
final class SwephUtils
{
    /**
     * Get DE ephemeris number for given planet and flags
     *
     * Port of swi_get_denum() from sweph.c:2406-2442
     *
     * Determines which JPL DE ephemeris version is being used based on:
     * - SEFLG_MOSEPH: returns 403 (Moshier)
     * - SEFLG_JPLEPH: returns JPL DE number from state or SE_DE_NUMBER
     * - Swiss Ephemeris files: returns denum from file header
     *
     * @param int $ipli Planet index (SEI_*)
     * @param int $iflag Calculation flags (SEFLG_*)
     * @return int DE ephemeris number (e.g., 431, 406, 403)
     */
    public static function getDenum(int $ipli, int $iflag): int
    {
        // C: if (iflag & SEFLG_MOSEPH) return 403;
        if ($iflag & Constants::SEFLG_MOSEPH) {
            return 403;
        }

        // C: if (iflag & SEFLG_JPLEPH)
        if ($iflag & Constants::SEFLG_JPLEPH) {
            $swed = SwedState::getInstance();
            // C: if (swed.jpldenum > 0) return swed.jpldenum; else return SE_DE_NUMBER;
            if ($swed->jpldenum > 0) {
                return $swed->jpldenum;
            } else {
                return Constants::SE_DE_NUMBER;
            }
        }

        // C: Determine file index based on planet
        $fdp = null;

        // C: if (ipli > SE_AST_OFFSET)
        if ($ipli > Constants::SE_AST_OFFSET) {
            $fdp = SwedState::getInstance()->fidat[SwephConstants::SEI_FILE_ANY_AST];
        }
        // C: else if (ipli > SE_PLMOON_OFFSET)
        elseif ($ipli > Constants::SE_PLMOON_OFFSET) {
            $fdp = SwedState::getInstance()->fidat[SwephConstants::SEI_FILE_ANY_AST];
        }
        // C: else if (ipli == SEI_CHIRON || ... || ipli == SEI_VESTA)
        elseif ($ipli == SwephConstants::SEI_CHIRON
            || $ipli == SwephConstants::SEI_PHOLUS
            || $ipli == SwephConstants::SEI_CERES
            || $ipli == SwephConstants::SEI_PALLAS
            || $ipli == SwephConstants::SEI_JUNO
            || $ipli == SwephConstants::SEI_VESTA) {
            $fdp = SwedState::getInstance()->fidat[SwephConstants::SEI_FILE_MAIN_AST];
        }
        // C: else if (ipli == SEI_MOON)
        elseif ($ipli == SwephConstants::SEI_MOON) {
            $fdp = SwedState::getInstance()->fidat[SwephConstants::SEI_FILE_MOON];
        }
        // C: else (main planets)
        else {
            $fdp = SwedState::getInstance()->fidat[SwephConstants::SEI_FILE_PLANET];
        }

        // C: if (fdp != NULL)
        if ($fdp !== null) {
            // C: if (fdp->sweph_denum != 0) return fdp->sweph_denum; else return SE_DE_NUMBER;
            if ($fdp->sweph_denum != 0) {
                return $fdp->sweph_denum;
            } else {
                return Constants::SE_DE_NUMBER;
            }
        }

        // C: return SE_DE_NUMBER;
        return Constants::SE_DE_NUMBER;
    }
}
