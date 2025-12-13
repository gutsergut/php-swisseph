<?php

declare(strict_types=1);

namespace Swisseph\Swe\FixedStars;

use Swisseph\Constants;
use Swisseph\State;

/**
 * Legacy fixed star file I/O operations.
 * Port of swi_fixstar_load_record() from sweph.c:7527-7668
 *
 * This is the OLD API that reads star file line-by-line (slower).
 * For new code, use StarRegistry (loads all stars at once).
 *
 * NO SIMPLIFICATIONS - Full C code fidelity maintained.
 */
final class LegacyStarFile
{
    /** File handle for star catalog */
    private static $fileHandle = null;

    /** Is using old star file format */
    private static bool $isOldStarFile = false;

    /**
     * Load star record from file by searching line-by-line.
     * Port of swi_fixstar_load_record() from sweph.c:7527-7668
     *
     * C signature:
     * static int32 swi_fixstar_load_record(char *star, char *srecord,
     *                                      char *sname, char *sbayer,
     *                                      double *dparams, char *serr)
     *
     * @param string &$star Input: star name/number; Output: formatted "tradname,nomenclature"
     * @param string &$srecord Output: raw record from file
     * @param array|null &$dparams Output: [epoch, ra, de, ramot, demot, radvel, parall, mag]
     * @param string|null &$serr Error message
     * @return int OK on success, ERR on error
     */
    public static function loadRecord(
        string &$star,
        string &$srecord,
        ?array &$dparams = null,
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

        // C: if (*sstar == ',') is_bayer = TRUE;
        $isBayer = false;
        $starNr = 0;

        if ($sstar[0] === ',') {
            $isBayer = true;
        } elseif (ctype_digit($sstar[0])) {
            // C: else if (isdigit((int) *sstar)) star_nr = atoi(sstar);
            $starNr = (int)$sstar;
        } else {
            // C: if ((sp = strchr(sstar, ',')) != NULL) *sp = '\0';
            // Traditional name: cut off Bayer designation
            $commaPos = strpos($sstar, ',');
            if ($commaPos !== false) {
                $sstar = substr($sstar, 0, $commaPos);
            }
        }

        $cmplen = strlen($sstar);

        // C: if (swed.fixfp == NULL) { ... open file ... }
        if (self::$fileHandle === null) {
            $retc = self::openStarFile($serr);
            if ($retc === Constants::SE_ERR) {
                return Constants::SE_ERR;
            }
        }

        // C: rewind(swed.fixfp);
        rewind(self::$fileHandle);

        $line = 0;
        $fline = 0;

        // C: while (fgets(s, AS_MAXCH, swed.fixfp) != NULL)
        while (($s = fgets(self::$fileHandle)) !== false) {
            $fline++;

            // C: if (*s == '#') continue;
            if ($s[0] === '#') {
                continue;
            }

            $line++;

            // C: if (star_nr == line) goto found;
            if ($starNr === $line) {
                $srecord = rtrim($s, "\r\n");
                goto found;
            } elseif ($starNr > 0) {
                continue;
            }

            // C: if ((sp = strchr(s, ',')) == NULL) { error }
            $commaPos = strpos($s, ',');
            if ($commaPos === false) {
                $serr = sprintf(
                    'star file %s damaged at line %d',
                    Constants::SE_STARFILE,
                    $fline
                );
                return Constants::SE_ERR;
            }

            // C: if (is_bayer) { if (strncmp(sp, sstar, cmplen) == 0) goto found; }
            if ($isBayer) {
                $afterComma = substr($s, $commaPos);
                if (strncmp($afterComma, $sstar, $cmplen) === 0) {
                    $srecord = rtrim($s, "\r\n");
                    goto found;
                }
                continue;
            }

            // C: *sp = '\0'; ... memcpy(fstar, s, slen); ... *sp = ',';
            $fstar = substr($s, 0, $commaPos);

            // C: slen = strlen(s); if (slen > SE_MAX_STNAME) slen = SE_MAX_STNAME;
            $slen = strlen($fstar);
            if ($slen > Constants::SE_MAX_STNAME) {
                $fstar = substr($fstar, 0, Constants::SE_MAX_STNAME);
            }

            // C: while ((sp = strchr(fstar, ' ')) != NULL) swi_strcpy(sp, sp+1);
            $fstar = str_replace(' ', '', $fstar);

            // C: i = (int) strlen(fstar); if (i < (int) cmplen) continue;
            $i = strlen($fstar);
            if ($i < $cmplen) {
                continue;
            }

            // C: for (sp2 = fstar; *sp2 != '\0'; sp2++) *sp2 = tolower((int) *sp2);
            $fstar = strtolower($fstar);

            // C: if (strncmp(fstar, sstar, cmplen) == 0) goto found;
            if (strncmp($fstar, $sstar, $cmplen) === 0) {
                $srecord = rtrim($s, "\r\n");
                goto found;
            }
        }

        // Not found
        if (strlen($star) < \Swisseph\SwephFile\SwephConstants::AS_MAXCH - 20) {
            $serr = sprintf('star %s not found', $star);
        } else {
            $serr = 'star not found';
        }
        return Constants::SE_ERR;

        found:
        // C: retc = fixstar_cut_string(srecord, star, &stardata, serr);
        $stardata = FixedStarParser::parseRecord($srecord, $star, $serr);
        if ($stardata === null) {
            return Constants::SE_ERR;
        }

        // C: sprintf(star, "%s,%s", stardata.starname, stardata.starbayer);
        $star = $stardata->getFullName();

        // C: if (dparams != NULL) { ... fill array ... }
        if ($dparams !== null) {
            $dparams = [
                $stardata->epoch,   // dparams[0]
                $stardata->ra,      // dparams[1]
                $stardata->de,      // dparams[2]
                $stardata->ramot,   // dparams[3]
                $stardata->demot,   // dparams[4]
                $stardata->radvel,  // dparams[5]
                $stardata->parall,  // dparams[6]
                $stardata->mag,     // dparams[7]
            ];
        }

        return Constants::SE_OK;
    }

    /**
     * Open star catalog file.
     * Port of swi_fopen logic from sweph.c:7569-7577
     *
     * @param string|null &$serr Error message
     * @return int OK on success, ERR on error
     */
    private static function openStarFile(?string &$serr): int
    {
        $ephePath = State::getEphePath();

        // Try new format first
        $starFile = $ephePath . DIRECTORY_SEPARATOR . Constants::SE_STARFILE;
        self::$fileHandle = @fopen($starFile, 'r');

        if (self::$fileHandle === false) {
            // Try old format
            self::$isOldStarFile = true;
            $starFileOld = $ephePath . DIRECTORY_SEPARATOR . Constants::SE_STARFILE_OLD;
            self::$fileHandle = @fopen($starFileOld, 'r');

            if (self::$fileHandle === false) {
                self::$isOldStarFile = false;
                $serr = sprintf(
                    'star file %s not found in %s',
                    Constants::SE_STARFILE,
                    $ephePath
                );
                return Constants::SE_ERR;
            }
        }

        return Constants::SE_OK;
    }

    /**
     * Format and normalize star search name.
     * Port of fixstar_format_search_name() from sweph.c:6208-6228
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
        // C: strncpy(sstar, star, SWI_STAR_LENGTH);
        $sstar = $star;

        // C: while ((sp = strchr(sstar, ' ')) != NULL) swi_strcpy(sp, sp+1);
        $sstar = str_replace(' ', '', $sstar);

        // C: for (sp = sstar; *sp != '\0' && *sp != ','; sp++) *sp = tolower((int) *sp);
        $commaPos = strpos($sstar, ',');
        if ($commaPos !== false) {
            $before = substr($sstar, 0, $commaPos);
            $after = substr($sstar, $commaPos);
            $sstar = strtolower($before) . $after;
        } else {
            $sstar = strtolower($sstar);
        }

        // C: cmplen = strlen(sstar); if (cmplen == 0) return ERR;
        if (empty($sstar)) {
            $serr = 'star name empty';
            return Constants::SE_ERR;
        }

        return Constants::SE_OK;
    }

    /**
     * Close star file handle.
     */
    public static function closeFile(): void
    {
        if (self::$fileHandle !== null) {
            fclose(self::$fileHandle);
            self::$fileHandle = null;
        }
    }
}
