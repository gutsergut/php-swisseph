<?php

namespace Swe\Functions;

use Swe\Constants;
use Swe\Error;
use Swe\FixedStar;
use Swe\Sweph;
use Swe\Math;
use Swe\Coordinates\PolarCoordinates;

/**
 * Fixed star position calculations.
 * Port of fixstar functions from sweph.c
 */
class FixstarFunctions
{
    /** Max star file line length */
    private const AS_MAXCH = 256;

    /** Star file name (new format) */
    private const SE_STARFILE = 'sefstars.txt';

    /** Star file name (old format) */
    private const SE_STARFILE_OLD = 'fixstars.cat';

    /** Max traditional star name length for searching */
    private const SE_MAX_STNAME = 256;

    /** Star file identifier for swi_fopen */
    private const SEI_FILE_FIXSTAR = 0;

    /** TLS: Last star data from swe_fixstar() */
    private static ?string $lastStarData = null;
    private static ?string $lastStarName = null;

    /** TLS: Last star data from swe_fixstar_mag() */
    private static ?string $lastMagStarData = null;
    private static ?string $lastMagStarName = null;

    /** File pointer for star catalog */
    private static $starFilePointer = null;

    /** Is old star file format? */
    private static bool $isOldStarFile = false;

    /**
     * Formats the input search name of a star:
     * - remove white spaces
     * - traditional name to lower case (Bayer designation remains as it is)
     *
     * Port of fixstar_format_search_name() from sweph.c:6207-6228
     *
     * @param string $star Input star name
     * @param string $sstar Output formatted star name (passed by reference)
     * @param string|null $serr Error string (passed by reference)
     * @return int OK or ERR
     */
    private static function formatSearchName(string $star, string &$sstar, ?string &$serr): int
    {
        // Copy and truncate to max length
        $sstar = substr($star, 0, FixedStar::STAR_LENGTH);

        // Remove whitespaces from search name
        $sstar = str_replace(' ', '', $sstar);

        // Traditional name of star to lower case;
        // keep uppercase with Bayer/Flamsteed designations after comma
        $commaPos = strpos($sstar, ',');
        if ($commaPos !== false) {
            // Everything before comma to lowercase
            $beforeComma = substr($sstar, 0, $commaPos);
            $afterComma = substr($sstar, $commaPos);
            $sstar = strtolower($beforeComma) . $afterComma;
        } else {
            // No comma - entire name to lowercase
            $sstar = strtolower($sstar);
        }

        $cmplen = strlen($sstar);
        if ($cmplen === 0) {
            if ($serr !== null) {
                $serr = 'swe_fixstar(): star name empty';
            }
            return Error::ERR;
        }

        return Error::OK;
    }

    /**
     * Cuts a comma-separated fixed star data record from sefstars.txt
     * and fills it into a FixedStar structure.
     *
     * Port of fixstar_cut_string() from sweph.c:6270-6366
     *
     * @param string $srecord CSV record from star file
     * @param string|null $star Output star name (passed by reference)
     * @param FixedStar $stardata Output star data structure
     * @param string|null $serr Error string (passed by reference)
     * @return int OK or ERR
     */
    private static function cutString(string $srecord, ?string &$star, FixedStar $stardata, ?string &$serr): int
    {
        $s = $srecord;

        // Split by comma
        $cpos = explode(',', $s, 20);
        $numFields = count($cpos);

        // Trim whitespace from first two fields
        if (isset($cpos[0])) {
            $cpos[0] = rtrim($cpos[0]);
        }
        if (isset($cpos[1])) {
            $cpos[1] = rtrim($cpos[1]);
        }

        // Check minimum number of fields
        if ($numFields < 14) {
            if ($serr !== null) {
                if ($numFields >= 2) {
                    $serr = sprintf("data of star '%s,%s' incomplete", $cpos[0], $cpos[1]);
                } else {
                    $truncated = strlen($s) > 200 ? substr($s, 0, 200) : $s;
                    $serr = sprintf("invalid line in fixed stars file: '%s'", $truncated);
                }
            }
            return Error::ERR;
        }

        // Truncate star names to max length
        if (strlen($cpos[0]) > FixedStar::STAR_LENGTH) {
            $cpos[0] = substr($cpos[0], 0, FixedStar::STAR_LENGTH);
        }
        if (strlen($cpos[1]) > FixedStar::STAR_LENGTH - 1) {
            $cpos[1] = substr($cpos[1], 0, FixedStar::STAR_LENGTH - 1);
        }

        // Return combined star name
        if ($star !== null) {
            $star = $cpos[0];
            if (strlen($cpos[0]) + strlen($cpos[1]) + 1 < FixedStar::STAR_LENGTH - 1) {
                $star .= ',' . $cpos[1];
            }
        }

        // Fill star names
        $stardata->starname = $cpos[0];
        $stardata->starbayer = $cpos[1];

        // Parse star data fields
        $epoch = (float)$cpos[2];
        $ra_h = (float)$cpos[3];
        $ra_m = (float)$cpos[4];
        $ra_s = (float)$cpos[5];
        $de_d = (float)$cpos[6];
        $sde_d = $cpos[6];  // string version for sign check
        $de_m = (float)$cpos[7];
        $de_s = (float)$cpos[8];
        $ra_pm = (float)$cpos[9];
        $de_pm = (float)$cpos[10];
        $radv = (float)$cpos[11];
        $parall = (float)$cpos[12];
        if ($parall < 0) {
            $parall = -$parall;  // fix bug like old Rasalgheti
        }
        $mag = (float)$cpos[13];

        /****************************************
         * position and speed (equinox)
         ****************************************/
        // RA and Dec in degrees
        $ra = ($ra_s / 3600.0 + $ra_m / 60.0 + $ra_h) * 15.0;

        if (strpos($sde_d, '-') === false) {
            $de = $de_s / 3600.0 + $de_m / 60.0 + $de_d;
        } else {
            $de = -$de_s / 3600.0 - $de_m / 60.0 + $de_d;
        }

        // Speed in RA and Dec, degrees per century
        if (self::$isOldStarFile) {
            $ra_pm = $ra_pm * 15 / 3600.0;
            $de_pm = $de_pm / 3600.0;
        } else {
            $ra_pm = $ra_pm / 10.0 / 3600.0;
            $de_pm = $de_pm / 10.0 / 3600.0;
            $parall /= 1000.0;
        }

        // Parallax, degrees
        if ($parall > 1) {
            $parall = (1 / $parall / 3600.0);
        } else {
            $parall /= 3600;
        }

        // Radial velocity in AU per century
        $radv *= Constants::KM_S_TO_AU_CTY;

        // Convert to radians
        $ra *= Constants::DEGTORAD;
        $de *= Constants::DEGTORAD;
        $ra_pm *= Constants::DEGTORAD;
        $de_pm *= Constants::DEGTORAD;
        $ra_pm /= cos($de);  // catalogues give proper motion in RA as great circle
        $parall *= Constants::DEGTORAD;

        // Fill star data structure
        $stardata->epoch = $epoch;
        $stardata->ra = $ra;
        $stardata->de = $de;
        $stardata->ramot = $ra_pm;
        $stardata->demot = $de_pm;
        $stardata->parall = $parall;
        $stardata->radvel = $radv;
        $stardata->mag = $mag;

        return Error::OK;
    }

    /**
     * Returns built-in star data for special stars required for Hindu sidereal ephemerides.
     *
     * Port of get_builtin_star() from sweph.c:6804-6866
     *
     * @param string $star Star name to search
     * @param string $sstar Formatted search name
     * @param string $srecord Output CSV record (passed by reference)
     * @return bool True if built-in star found
     */
    private static function getBuiltinStar(string $star, string $sstar, string &$srecord): bool
    {
        // Ayanamsha SE_SIDM_TRUE_CITRA - Spica
        if (stripos($star, 'spica') === 0) {
            $srecord = 'Spica,alVir,ICRS,13,25,11.57937,-11,09,40.7501,-42.35,-30.67,1,13.06,0.97,-10,3672';
            return true;
        }

        // Ayanamsha SE_SIDM_TRUE_REVATI - Revati (zeta Psc)
        if (strpos($star, ',zePsc') !== false || stripos($star, 'revati') === 0) {
            $srecord = 'Revati,zePsc,ICRS,01,13,43.88735,+07,34,31.2745,145,-55.69,15,18.76,5.187,06,174';
            return true;
        }

        // Ayanamsha SE_SIDM_TRUE_PUSHYA - Pushya (delta Cnc)
        if (strpos($star, ',deCnc') !== false || stripos($star, 'pushya') === 0) {
            $srecord = 'Pushya,deCnc,ICRS,08,44,41.09921,+18,09,15.5034,-17.67,-229.26,17.14,24.98,3.94,18,2027';
            return true;
        }

        // Ayanamsha SE_SIDM_TRUE_MULA - Mula (lambda Sco)
        if (strpos($star, ',laSco') !== false || stripos($star, 'mula') === 0) {
            $srecord = 'Mula,laSco,ICRS,17,33,36.52012,-37,06,13.7648,-8.53,-30.8,-3,5.71,1.62,-37,11673';
            return true;
        }

        // Ayanamsha SE_SIDM_GALCENT_* - Galactic Center (Sgr A*)
        if (strpos($star, ',SgrA*') !== false) {
            $srecord = 'Gal. Center,SgrA*,2000,17,45,40.03599,-29,00,28.1699,-2.755718425,-5.547,0.0,0.125,999.99,0,0';
            return true;
        }

        // Ayanamsha SE_SIDM_GALEQU_IAU1958 - Galactic Pole IAU1958
        if (strpos($star, ',GP1958') !== false) {
            $srecord = 'Gal. Pole IAU1958,GP1958,1950,12,49,0.0,27,24,0.0,0.0,0.0,0.0,0.0,0.0,0,0';
            return true;
        }

        // Ayanamsha SE_SIDM_GALEQU_TRUE / SE_SIDM_GALEQU_MULA - Galactic Pole
        if (strpos($star, ',GPol') !== false) {
            $srecord = 'Gal. Pole,GPol,ICRS,12,51,36.7151981,27,06,11.193172,0.0,0.0,0.0,0.0,0.0,0,0';
            return true;
        }

        return false;
    }

    /**
     * Loads a fixed star record from file sefstars.txt.
     *
     * Port of swi_fixstar_load_record() from sweph.c:7527-7654
     *
     * @param string $star Star name or number (modified in place)
     * @param string $srecord Output CSV record (passed by reference)
     * @param array|null $dparams Output parameters array [epoch, ra, de, ramot, demot, radvel, parall, mag]
     * @param string|null $serr Error string (passed by reference)
     * @return int OK or ERR
     */
    private static function loadRecord(string &$star, string &$srecord, ?array &$dparams, ?string &$serr): int
    {
        $sstar = '';
        $fstar = '';
        $starNr = 0;
        $line = 0;
        $fline = 0;
        $isBayer = false;

        // Format search name
        $retc = self::formatSearchName($star, $sstar, $serr);
        if ($retc === Error::ERR) {
            return Error::ERR;
        }

        // Check search type
        if ($sstar[0] === ',') {
            $isBayer = true;
        } elseif (ctype_digit($sstar[0])) {
            $starNr = (int)$sstar;
        } else {
            // Traditional name: cut off Bayer designation
            $commaPos = strpos($sstar, ',');
            if ($commaPos !== false) {
                $sstar = substr($sstar, 0, $commaPos);
            }
        }

        $cmplen = strlen($sstar);

        /******************************************************
         * Open star file
         ******************************************************/
        if (self::$starFilePointer === null) {
            // Try new format first
            $filepath = Sweph::getEphePath();
            $filename = $filepath . DIRECTORY_SEPARATOR . self::SE_STARFILE;

            if (file_exists($filename)) {
                self::$starFilePointer = fopen($filename, 'r');
                self::$isOldStarFile = false;
            } else {
                // Try old format
                $filename = $filepath . DIRECTORY_SEPARATOR . self::SE_STARFILE_OLD;
                if (file_exists($filename)) {
                    self::$starFilePointer = fopen($filename, 'r');
                    self::$isOldStarFile = true;
                } else {
                    if ($serr !== null) {
                        $serr = sprintf('fixed star file %s not found in %s', self::SE_STARFILE, $filepath);
                    }
                    return Error::ERR;
                }
            }
        }

        rewind(self::$starFilePointer);

        // Search for star in file
        while (($s = fgets(self::$starFilePointer)) !== false) {
            $fline++;

            // Skip comment lines
            if ($s[0] === '#') continue;

            $line++;

            // Search by line number
            if ($starNr === $line) {
                goto found;
            } elseif ($starNr > 0) {
                continue;
            }

            // Check for comma
            $commaPos = strpos($s, ',');
            if ($commaPos === false) {
                if ($serr !== null) {
                    $serr = sprintf('star file %s damaged at line %d', self::SE_STARFILE, $fline);
                }
                return Error::ERR;
            }

            // Search by Bayer designation
            if ($isBayer) {
                if (strncmp(substr($s, $commaPos), $sstar, $cmplen) === 0) {
                    goto found;
                } else {
                    continue;
                }
            }

            // Search by traditional name
            $starName = substr($s, 0, $commaPos);
            $slen = strlen($starName);
            if ($slen > self::SE_MAX_STNAME) {
                $slen = self::SE_MAX_STNAME;
            }
            $fstar = substr($starName, 0, $slen);

            // Remove whitespaces from star name
            $fstar = str_replace(' ', '', $fstar);

            // Check length
            if (strlen($fstar) < $cmplen) {
                continue;
            }

            // Star name to lowercase and compare
            $fstarLower = strtolower($fstar);
            if (strncmp($fstarLower, $sstar, $cmplen) === 0) {
                goto found;
            }
        }

        // Star not found
        if ($serr !== null) {
            $serr = sprintf('star %s not found', $star);
        }
        return Error::ERR;

        found:
        $srecord = trim($s);

        // Parse record into star data
        $stardata = new FixedStar();
        $retc = self::cutString($srecord, $star, $stardata, $serr);
        if ($retc === Error::ERR) {
            return Error::ERR;
        }

        // Fill output parameters if requested
        if ($dparams !== null) {
            $dparams = [
                $stardata->epoch,   // 0: epoch
                $stardata->ra,      // 1: RA(epoch)
                $stardata->de,      // 2: Decl(epoch)
                $stardata->ramot,   // 3: RA proper motion
                $stardata->demot,   // 4: decl proper motion
                $stardata->radvel,  // 5: radial velocity
                $stardata->parall,  // 6: parallax
                $stardata->mag,     // 7: magnitude V
            ];
        }

        return Error::OK;
    }
}
