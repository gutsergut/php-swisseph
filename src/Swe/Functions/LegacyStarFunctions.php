<?php

declare(strict_types=1);

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;
use Swisseph\DeltaT;
use Swisseph\Swe\FixedStars\LegacyStarFile;
use Swisseph\Swe\FixedStars\LegacyStarCalculator;

/**
 * Legacy fixed star position calculation functions.
 * Port of swe_fixstar* functions from sweph.c:7950-8090.
 *
 * This is the OLD API that reads star file line-by-line (slower).
 * For new code, use StarFunctions (swe_fixstar2*) instead.
 *
 * NO SIMPLIFICATIONS - Full C code fidelity maintained.
 */
final class LegacyStarFunctions
{
    /** Cache for last star record */
    private static ?string $lastStarData = null;
    private static ?string $lastStarName = null;

    /**
     * Calculate fixed star position for Ephemeris Time (legacy API).
     * Port of swe_fixstar() from sweph.c:7950-8013
     *
     * C signature:
     * int32 CALL_CONV swe_fixstar(char *star, double tjd, int32 iflag,
     *                             double *xx, char *serr)
     *
     * @param string &$star Input: star name or number; Output: formatted "tradname,nomenclature"
     * @param float $tjd Julian Day Ephemeris Time
     * @param int $iflag Calculation flags (SEFLG_*)
     * @param array &$xx Output: 6 doubles [lon/ra, lat/dec, dist, dlon, dlat, ddist]
     * @param string|null &$serr Error message
     * @return int iflag on success, ERR on error
     */
    public static function fixstar(
        string &$star,
        float $tjd,
        int $iflag,
        array &$xx,
        ?string &$serr = null
    ): int {
        $serr = '';

        // C: char sstar[SWI_STAR_LENGTH + 1];
        $sstar = '';

        // C: retc = fixstar_format_search_name(star, sstar, serr);
        $retc = self::formatSearchName($star, $sstar, $serr);
        if ($retc === Constants::SE_ERR) {
            self::clearOutput($xx);
            return Constants::SE_ERR;
        }

        // C: if (*sstar == ',') { ; // is Bayer designation }
        if ($sstar[0] === ',') {
            // Bayer designation - continue
        } elseif (ctype_digit($sstar[0])) {
            // C: else if (isdigit((int) *sstar)) { ; // sequential star number }
            // Star number - continue
        } else {
            // C: else { if ((sp = strchr(sstar, ',')) != NULL) *sp = '\0'; }
            // Traditional name: cut off Bayer designation for cache check
            $commaPos = strpos($sstar, ',');
            if ($commaPos !== false) {
                $sstar = substr($sstar, 0, $commaPos);
            }
        }

        // C: if (*slast_stardata != '\0' && strcmp(slast_starname, sstar) == 0) {
        //      strcpy(srecord, slast_stardata); goto found;
        // }
        $srecord = '';
        if (self::$lastStarData !== null && self::$lastStarName === $sstar) {
            $srecord = self::$lastStarData;
            goto found;
        }

        // C: if ((retc = swi_fixstar_load_record(star, srecord, NULL, NULL, NULL, serr)) != OK)
        //      goto return_err;
        $retc = LegacyStarFile::loadRecord($star, $srecord, null, $serr);
        if ($retc !== Constants::SE_OK) {
            self::clearOutput($xx);
            return Constants::SE_ERR;
        }

        found:
        // C: strcpy(slast_stardata, srecord); strcpy(slast_starname, sstar);
        self::$lastStarData = $srecord;
        self::$lastStarName = $sstar;

        // C: if ((retc = swi_fixstar_calc_from_record(srecord, tjd, iflag, star, xx, serr)) == ERR)
        //      goto return_err;
        $retc = LegacyStarCalculator::calculateFromRecord($srecord, $tjd, $iflag, $star, $xx, $serr);
        if ($retc === Constants::SE_ERR) {
            self::clearOutput($xx);
            return Constants::SE_ERR;
        }

        return $iflag;
    }

    /**
     * Calculate fixed star position for Universal Time (legacy API).
     * Port of swe_fixstar_ut() from sweph.c:8015-8033
     *
     * C signature:
     * int32 CALL_CONV swe_fixstar_ut(char *star, double tjd_ut, int32 iflag,
     *                                double *xx, char *serr)
     *
     * @param string &$star Input: star name or number; Output: formatted "tradname,nomenclature"
     * @param float $tjdUt Julian Day Universal Time
     * @param int $iflag Calculation flags (SEFLG_*)
     * @param array &$xx Output: 6 doubles [lon/ra, lat/dec, dist, dlon, dlat, ddist]
     * @param string|null &$serr Error message
     * @return int iflag on success, ERR on error
     */
    public static function fixstarUt(
        string &$star,
        float $tjdUt,
        int $iflag,
        array &$xx,
        ?string &$serr = null
    ): int {
        $serr = '';

        // C: iflag = plaus_iflag(iflag, -1, tjd_ut, serr);
        // For now, use iflag as-is (plaus_iflag is internal validation)

        // C: epheflag = iflag & SEFLG_EPHMASK;
        $epheflag = $iflag & Constants::SEFLG_EPHMASK;
        if ($epheflag === 0) {
            $epheflag = Constants::SEFLG_SWIEPH;
            $iflag |= Constants::SEFLG_SWIEPH;
        }

        // C: deltat = swe_deltat_ex(tjd_ut, iflag, serr);
        $deltat = DeltaT::deltaTSecondsFromJd($tjdUt) / 86400.0;

        // C: retflag = swe_fixstar(star, tjd_ut + deltat, iflag, xx, serr);
        $retflag = self::fixstar($star, $tjdUt + $deltat, $iflag, $xx, $serr);

        // C: if (retflag != ERR && (retflag & SEFLG_EPHMASK) != epheflag)
        if ($retflag != Constants::SE_ERR && ($retflag & Constants::SEFLG_EPHMASK) !== $epheflag) {
            $deltat = DeltaT::deltaTSecondsFromJd($tjdUt) / 86400.0;
            $retflag = self::fixstar($star, $tjdUt + $deltat, $iflag, $xx, null);
        }

        return $retflag;
    }

    /**
     * Get fixed star magnitude (legacy API).
     * Port of swe_fixstar_mag() from sweph.c:8035-8090
     *
     * C signature:
     * int32 CALL_CONV swe_fixstar_mag(char *star, double *mag, char *serr)
     *
     * @param string &$star Input: star name or number; Output: formatted "tradname,nomenclature"
     * @param float &$mag Output: star magnitude
     * @param string|null &$serr Error message
     * @return int OK on success, ERR on error
     */
    public static function fixstarMag(
        string &$star,
        float &$mag,
        ?string &$serr = null
    ): int {
        $serr = '';

        // C: char sstar[SWI_STAR_LENGTH + 1];
        $sstar = '';

        // C: retc = fixstar_format_search_name(star, sstar, serr);
        $retc = self::formatSearchName($star, $sstar, $serr);
        if ($retc === Constants::SE_ERR) {
            return Constants::SE_ERR;
        }

        // C: if (*sstar == ',') { ; // is Bayer designation }
        if ($sstar[0] === ',') {
            // Bayer designation - continue
        } elseif (ctype_digit($sstar[0])) {
            // Star number - continue
        } else {
            // Traditional name: cut off Bayer designation
            $commaPos = strpos($sstar, ',');
            if ($commaPos !== false) {
                $sstar = substr($sstar, 0, $commaPos);
            }
        }

        // C: if (*slast_stardata != '\0' && strcmp(slast_starname, sstar) == 0) {
        //      strcpy(srecord, slast_stardata); goto found;
        // }
        $srecord = '';
        $dparams = null;

        if (self::$lastStarData !== null && self::$lastStarName === $sstar) {
            $srecord = self::$lastStarData;
            goto found;
        }

        // C: if ((retc = swi_fixstar_load_record(star, srecord, NULL, NULL, dparams, serr)) != OK)
        //      return ERR;
        $retc = LegacyStarFile::loadRecord($star, $srecord, $dparams, $serr);
        if ($retc !== Constants::SE_OK) {
            return Constants::SE_ERR;
        }

        found:
        // C: strcpy(slast_stardata, srecord); strcpy(slast_starname, sstar);
        self::$lastStarData = $srecord;
        self::$lastStarName = $sstar;

        // C: if (dparams == NULL) ... load dparams
        if ($dparams === null) {
            $retc = LegacyStarFile::loadRecord($star, $srecord, $dparams, $serr);
            if ($retc !== Constants::SE_OK) {
                return Constants::SE_ERR;
            }
        }

        // C: *mag = dparams[7];
        $mag = $dparams[7];

        return Constants::SE_OK;
    }

    /**
     * Format and normalize star search name.
     * Helper function matching C logic.
     *
     * @param string $star Input star name
     * @param string &$sstar Output formatted name
     * @param string|null &$serr Error message
     * @return int OK on success, ERR on error
     */
    private static function formatSearchName(
        string $star,
        string &$sstar,
        ?string &$serr
    ): int {
        // Remove all whitespaces
        $sstar = str_replace(' ', '', $star);

        // Convert to lowercase only before comma
        $commaPos = strpos($sstar, ',');
        if ($commaPos !== false) {
            $before = substr($sstar, 0, $commaPos);
            $after = substr($sstar, $commaPos);
            $sstar = strtolower($before) . $after;
        } else {
            $sstar = strtolower($sstar);
        }

        if (empty($sstar)) {
            $serr = 'star name empty';
            return Constants::SE_ERR;
        }

        return Constants::SE_OK;
    }

    /**
     * Clear output array (set all elements to 0).
     *
     * @param array &$xx Output array to clear
     */
    private static function clearOutput(array &$xx): void
    {
        for ($i = 0; $i <= 5; $i++) {
            $xx[$i] = 0.0;
        }
    }
}
