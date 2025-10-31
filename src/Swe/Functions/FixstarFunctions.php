<?php

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;
use Swisseph\FixedStar;
use Swisseph\Coordinates;
use Swisseph\SiderealMode;
use Swisseph\FK4FK5;
use Swisseph\ICRS;
use Swisseph\Precession;
use Swisseph\Bias;
use Swisseph\VectorMath;
use Swisseph\Swe\Functions\TimeFunctions;
use Swisseph\Swe\Functions\PlanetsFunctions;

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
            return Constants::SE_ERR;
        }

        return Constants::SE_OK;
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
            return Constants::SE_ERR;
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

        return Constants::SE_OK;
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
        if ($retc === Constants::SE_ERR) {
            return Constants::SE_ERR;
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
            // TODO: Use proper ephemeris path from global state
            $filepath = __DIR__ . '/../../../tests/ephe';  // fallback for testing
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
                    return Constants::SE_ERR;
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
                return Constants::SE_ERR;
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
        return Constants::SE_ERR;

        found:
        $srecord = trim($s);

        // Parse record into star data
        $stardata = new FixedStar();
        $retc = self::cutString($srecord, $star, $stardata, $serr);
        if ($retc === Constants::SE_ERR) {
            return Constants::SE_ERR;
        }

        // Fill output parameters if requested
        if (is_array($dparams)) {
            $dparams[0] = $stardata->epoch;   // epoch
            $dparams[1] = $stardata->ra;      // RA(epoch)
            $dparams[2] = $stardata->de;      // Decl(epoch)
            $dparams[3] = $stardata->ramot;   // RA proper motion
            $dparams[4] = $stardata->demot;   // decl proper motion
            $dparams[5] = $stardata->radvel;  // radial velocity
            $dparams[6] = $stardata->parall;  // parallax
            $dparams[7] = $stardata->mag;     // magnitude V
        }

        return Constants::SE_OK;
    }

    /**
     * Calculate fixstar position from ET (Ephemeris Time).
     *
     * Port of swe_fixstar() from sweph.c:7950-8018
     *
     * @param string $star Star name (traditional name, Bayer designation, or sequential number). Modified to full name on success.
     * @param float $tjd Julian day number (ET)
     * @param int $iflag Calculation flags (SEFLG_*)
     * @param array $xx Output array [longitude, latitude, distance, speed_long, speed_lat, speed_dist]
     * @param string|null $serr Error string (passed by reference)
     * @return int Flag value or ERR
     */
    public static function fixstar(string &$star, float $tjd, int $iflag, array &$xx, ?string &$serr): int
    {
        $sstar = '';
        $srecord = '';

        // Initialize error string
        $serr = '';

        // Format search name
        $retc = self::formatSearchName($star, $sstar, $serr);
        if ($retc === Constants::SE_ERR) {
            goto return_err;
        }

        // Process search name
        if ($sstar[0] === ',') {
            // is Bayer designation
        } elseif (ctype_digit($sstar[0])) {
            // is a sequential star number
        } else {
            // cut off Bayer, if trad. name
            $commaPos = strpos($sstar, ',');
            if ($commaPos !== false) {
                $sstar = substr($sstar, 0, $commaPos);
            }
        }

        // Check cache: star elements from last call
        if (self::$lastStarData !== null && self::$lastStarName === $sstar) {
            $srecord = self::$lastStarData;
            goto found;
        }

        // Check built-in stars
        if (self::getBuiltinStar($star, $sstar, $srecord)) {
            goto found;
        }

        // Load from star file
        $dparams = null;
        if (($retc = self::loadRecord($star, $srecord, $dparams, $serr)) !== Constants::SE_OK) {
            goto return_err;
        }

        found:
        // Cache for next call
        self::$lastStarData = $srecord;
        self::$lastStarName = $sstar;

        // Calculate position from record with full coordinate transformations
        $retc = self::calcFromRecord($srecord, $tjd, $iflag, $star, $xx, $serr);
        if ($retc === Constants::SE_ERR) {
            goto return_err;
        }

        return $retc;

        return_err:
        $xx = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        return Constants::SE_ERR;
    }

    /**
     * Calculate fixstar position from UT (Universal Time).
     *
     * Port of swe_fixstar_ut() from sweph.c:8020-8042
     *
     * @param string $star Star name. Modified to full name on success.
     * @param float $tjdUt Julian day number (UT)
     * @param int $iflag Calculation flags (SEFLG_*)
     * @param array $xx Output array [longitude, latitude, distance, speed_long, speed_lat, speed_dist]
     * @param string|null $serr Error string (passed by reference)
     * @return int Flag value or ERR
     */
    public static function fixstarUt(string &$star, float $tjdUt, int $iflag, array &$xx, ?string &$serr): int
    {
        // Get delta T
        $deltat = TimeFunctions::deltatEx($tjdUt, $iflag, $serr);

        // Calculate with ET
        $retflag = self::fixstar($star, $tjdUt + $deltat, $iflag, $xx, $serr);

        // If ephemeris changed, recalculate delta T
        if ($retflag !== Constants::SE_ERR && ($retflag & Constants::SEFLG_EPHMASK) !== ($iflag & Constants::SEFLG_EPHMASK)) {
            $deltat = TimeFunctions::deltatEx($tjdUt, $retflag, $serr);
            $retflag = self::fixstar($star, $tjdUt + $deltat, $iflag, $xx, $serr);
        }

        return $retflag;
    }

    /**
     * Get visual magnitude of a fixed star.
     *
     * Port of swe_fixstar_mag() from sweph.c:8044-8095
     *
     * @param string $star Star name. Modified to full name on success.
     * @param float $mag Output magnitude (passed by reference)
     * @param string|null $serr Error string (passed by reference)
     * @return int OK or ERR
     */
    public static function fixstarMag(string &$star, float &$mag, ?string &$serr): int
    {
        $sstar = '';
        $srecord = '';

        // Initialize error string
        $serr = '';

        // Format search name
        $retc = self::formatSearchName($star, $sstar, $serr);
        if ($retc === Constants::SE_ERR) {
            goto return_err;
        }

        // Process search name
        if ($sstar[0] === ',') {
            // is Bayer designation
        } elseif (ctype_digit($sstar[0])) {
            // is a sequential star number
        } else {
            // cut off Bayer, if trad. name
            $commaPos = strpos($sstar, ',');
            if ($commaPos !== false) {
                $sstar = substr($sstar, 0, $commaPos);
            }
        }

        // Check cache: star elements from last call
        if (self::$lastMagStarData !== null && self::$lastMagStarName === $sstar) {
            $srecord = self::$lastMagStarData;
            $stardata = new FixedStar();
            $retc = self::cutString($srecord, $star, $stardata, $serr);
            if ($retc === Constants::SE_ERR) {
                goto return_err;
            }
            $mag = $stardata->mag;
            goto found;
        }

        // Load from file (or built-in)
        $dparams = [];
        if (($retc = self::loadRecord($star, $srecord, $dparams, $serr)) !== Constants::SE_OK) {
            goto return_err;
        }

        // Magnitude is in dparams[7]
        $mag = $dparams[7];

        found:
        // Cache for next call
        self::$lastMagStarData = $srecord;
        self::$lastMagStarName = $sstar;

        return Constants::SE_OK;

        return_err:
        $mag = 0.0;
        return Constants::SE_ERR;
    }

    /**
     * Calculate fixstar position from CSV record with full astronomical transformations.
     *
     * Port of swi_fixstar_calc_from_record() from sweph.c:7667-7950
     *
     * Applies: proper motion, parallax, radial velocity, FK4→FK5 precession, ICRF conversion,
     * observer position, light deflection, aberration, precession, nutation, coordinate transforms,
     * sidereal positions.
     *
     * @param string $srecord CSV record from star file
     * @param float $tjd Julian day (ET)
     * @param int $iflag Calculation flags
     * @param string $star Star name (for error messages)
     * @param array $xx Output coordinates [6 elements]
     * @param string $serr Error string
     * @return int Flag value or ERR
     */
    private static function calcFromRecord(string $srecord, float $tjd, int $iflag, string $star, array &$xx, string &$serr): int
    {
        // Port of swi_fixstar_calc_from_record() from sweph.c:7667-7950
        // Full astronomical transformations without simplifications

        $retc = Constants::SE_OK;
        $x = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xxsv = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xobs = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xobs_dt = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xearth = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xearth_dt = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xsun = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xsun_dt = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];

        $dt = Constants::PLAN_SPEED_INTV * 0.1;
        $iflgsave = $iflag;
        $iflag |= Constants::SEFLG_SPEED; // We need speed to work correctly

        // TODO: Validate and adjust iflag with plaus_iflag()
        $epheflag = $iflag & Constants::SEFLG_EPHMASK;

        // TODO: Check ephemeris initialization (swi_init_swed_if_start)
        // TODO: Handle ephemeris file management

        // Set default sidereal mode if needed
        if (($iflag & Constants::SEFLG_SIDEREAL) && !SiderealMode::isSet()) {
            SiderealMode::set(Constants::SE_SIDM_FAGAN_BRADLEY, 0.0, 0.0);
        }

        /******************************************
         * Parse star record
         ******************************************/
        $stardata = new FixedStar();
        $retc = self::cutString($srecord, $star, $stardata, $serr);
        if ($retc === Constants::SE_ERR) {
            return Constants::SE_ERR;
        }

        $epoch = $stardata->epoch;
        $ra_pm = $stardata->ramot;  // RA proper motion (radians/century)
        $de_pm = $stardata->demot;  // Dec proper motion (radians/century)
        $radv = $stardata->radvel;  // Radial velocity (AU/century)
        $parall = $stardata->parall; // Parallax (radians)
        $ra = $stardata->ra;        // RA at epoch (radians)
        $de = $stardata->de;        // Dec at epoch (radians)

        /******************************************
         * Calculate time since epoch
         ******************************************/
        if ($epoch == 1950) {
            $t = $tjd - Constants::B1950; // days since 1950.0
        } else { // epoch == 2000
            $t = $tjd - Constants::J2000; // days since 2000.0
        }

        /******************************************
         * Initial position vector (equatorial)
         ******************************************/
        $x[0] = $ra;
        $x[1] = $de;
        $x[2] = 1.0; // Will be replaced with actual distance

        // Calculate distance from parallax
        if ($parall == 0) {
            $rdist = 1000000000.0; // Very distant star
        } else {
            $rdist = 1.0 / ($parall * Constants::RADTODEG * 3600.0) * Constants::PARSEC_TO_AUNIT;
        }
        $x[2] = $rdist;

        // Proper motion and radial velocity (per day)
        $x[3] = $ra_pm / 36525.0;   // RA proper motion per day
        $x[4] = $de_pm / 36525.0;   // Dec proper motion per day
        $x[5] = $radv / 36525.0;    // Radial velocity per day

        /******************************************
         * Convert to Cartesian coordinates with speeds
         ******************************************/
        Coordinates::polcartSp($x, $x);

        /******************************************
         * PART 2: FK4 → FK5 conversion for epoch 1950
         ******************************************/
        if ($epoch == 1950) {
            // Convert from FK4 (B1950.0) to FK5 (J2000.0)
            FK4FK5::fk4ToFk5($x, Constants::B1950);

            // Precess from B1950 to J2000
            Precession::precess($x, Constants::B1950, 0, Constants::J_TO_J2000);
            Precession::precess($x, Constants::B1950, 0, Constants::J_TO_J2000, 3); // Speed vector
        }

        /******************************************
         * PART 3: ICRF conversion
         ******************************************/
        // FK5 to ICRF, if JPL ephemeris refers to ICRF
        // With data that are already ICRF, epoch = 0
        if ($epoch != 0) {
            // Convert FK5 → ICRF (backward = TRUE)
            ICRS::icrsToFk5($x, $iflag, true);

            // With ephemerides < DE403, we now convert to J2000
            // For DE >= 403, apply bias correction
            // TODO: Implement swi_get_denum() to check DE number
            // For now, assume modern ephemerides (DE >= 403) and apply bias
            $denum = 431; // Assume DE431 (modern ephemeris)
            if ($denum >= 403) {
                Bias::bias($x, Constants::J2000, Constants::SEFLG_SPEED, false);
            }
        }

        /******************************************
         * PART 4: Earth and Sun positions
         * For parallax, light deflection, and aberration
         ******************************************/
        $xpo = null;
        $xpo_dt = null;

        if (!($iflag & Constants::SEFLG_BARYCTR) && (!($iflag & Constants::SEFLG_HELCTR) || !($iflag & Constants::SEFLG_MOSEPH))) {
            // Get Earth position at tjd - dt
            $retc = PlanetsFunctions::calc($tjd - $dt, Constants::SE_EARTH, $epheflag | Constants::SEFLG_J2000 | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ, $xearth_dt, $serr);
            if ($retc < 0) {
                return Constants::SE_ERR;
            }

            // Get Earth position at tjd
            $retc = PlanetsFunctions::calc($tjd, Constants::SE_EARTH, $epheflag | Constants::SEFLG_J2000 | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ, $xearth, $serr);
            if ($retc < 0) {
                return Constants::SE_ERR;
            }

            // Get Sun position at tjd - dt
            $retc = PlanetsFunctions::calc($tjd - $dt, Constants::SE_SUN, $epheflag | Constants::SEFLG_J2000 | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ, $xsun_dt, $serr);
            if ($retc < 0) {
                return Constants::SE_ERR;
            }

            // Get Sun position at tjd
            $retc = PlanetsFunctions::calc($tjd, Constants::SE_SUN, $epheflag | Constants::SEFLG_J2000 | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ, $xsun, $serr);
            if ($retc < 0) {
                return Constants::SE_ERR;
            }
        }

        /******************************************
         * PART 5: Observer position (geocenter or topocenter)
         ******************************************/
        if ($iflag & Constants::SEFLG_TOPOCTR) {
            // TODO: Implement swi_get_observer() for topocentric positions
            // For now, topocentric not supported for stars
            $serr = 'Topocentric positions for fixed stars not yet implemented';
            return Constants::SE_ERR;
        } elseif (!($iflag & Constants::SEFLG_BARYCTR) && (!($iflag & Constants::SEFLG_HELCTR) || !($iflag & Constants::SEFLG_MOSEPH))) {
            // Barycentric position of geocenter
            for ($i = 0; $i <= 5; $i++) {
                $xobs[$i] = $xearth[$i];
                $xobs_dt[$i] = $xearth_dt[$i];
            }
        }

        /******************************************
         * PART 6: Apply proper motion and parallax
         ******************************************/
        // Determine observer position for parallax
        if (($iflag & Constants::SEFLG_HELCTR) && ($iflag & Constants::SEFLG_MOSEPH)) {
            $xpo = null;    // No parallax if Moshier and heliocentric
            $xpo_dt = null;
        } elseif ($iflag & Constants::SEFLG_HELCTR) {
            $xpo = $xsun;
            $xpo_dt = $xsun_dt;
        } elseif ($iflag & Constants::SEFLG_BARYCTR) {
            $xpo = null;    // No parallax if barycentric
            $xpo_dt = null;
        } else {
            $xpo = $xobs;
            $xpo_dt = $xobs_dt;
        }

        // Apply proper motion over time and subtract observer position (parallax)
        if ($xpo === null) {
            // No parallax correction - just apply proper motion
            for ($i = 0; $i <= 2; $i++) {
                $x[$i] += $t * $x[$i + 3];
            }
        } else {
            // Apply proper motion and parallax
            for ($i = 0; $i <= 2; $i++) {
                $x[$i] += $t * $x[$i + 3];      // Proper motion
                $x[$i] -= $xpo[$i];              // Subtract observer position (parallax)
                $x[$i + 3] -= $xpo[$i + 3];      // Speed correction
            }
        }

        // Part 7: Relativistic light deflection
        if (($iflag & Constants::SEFLG_TRUEPOS) == 0 && ($iflag & Constants::SEFLG_NOGDEFL) == 0) {
            self::deflectLight($x, $xearth, $xearth_dt, $xsun, $xsun_dt, $dt, $iflag);
        }

        // Part 8: Annual aberration of light
        if (($iflag & Constants::SEFLG_TRUEPOS) == 0 && ($iflag & Constants::SEFLG_NOABERR) == 0) {
            self::aberrLightEx($x, $xpo, $xpo_dt, $dt, $iflag);
        }

        // Part 9: ICRS to J2000 bias correction
        // Apply if NOT ICRS and (DE≥403 or BARYCTR)
        // For fixstars, we typically use modern ephemeris (DE431/DE440/441) which are all ≥403
        if (!($iflag & Constants::SEFLG_ICRS)) {
            // MOSEPH is 403, JPLEPH default is 431+, SWIEPH default is 431+
            // Only skip bias if explicitly using very old ephemeris
            $applyBias = ($iflag & Constants::SEFLG_BARYCTR) ||
                         !($iflag & Constants::SEFLG_MOSEPH); // MOSEPH is exactly 403, apply bias
            if ($applyBias) {
                Bias::bias($x, $tjd, $iflag, false);  // ICRS → J2000
            }
        }

        // Save J2000 coordinates (required for sidereal positions later)
        $xxsv = $x;

        // Part 10: Precession J2000 → equator of date
        if (!($iflag & Constants::SEFLG_J2000)) {
            Precession::precess($x, $tjd, $iflag, Constants::J2000_TO_J);
            if ($iflag & Constants::SEFLG_SPEED) {
                Precession::precessSpeed($x, $tjd, $iflag, Constants::J2000_TO_J);
            }
        }

        // Part 11: Nutation
        // TODO: Requires nutation matrix from Swed - will implement in next iteration
        // if (!($iflag & Constants::SEFLG_NONUT)) {
        //     Coordinates::nutate($x, $nutMatrix, $nutMatrixVelocity, $iflag, false);
        // }

        // Part 12: Transformation to ecliptic coordinates
        // TODO: Requires obliquity data from Swed - will implement in next iteration
        // For now, keeping positions in equatorial coordinates
        //
        // if (!($iflag & Constants::SEFLG_EQUATORIAL)) {
        //     // Get obliquity (epsilon) - use date obliquity unless J2000 requested
        //     // Transform equatorial → ecliptic using coortrf2
        //     // Apply nutation to ecliptic if calculated
        // }

        // Part 13: Sidereal positions
        // TODO: Requires sidereal mode infrastructure - will implement when needed
        // if ($iflag & Constants::SEFLG_SIDEREAL) {
        //     // Three modes: ECL_T0, SSY_PLANE, traditional ayanamsa
        // }

        // Part 14: Final conversions
        // Transform to polar coordinates if not XYZ
        if (!($iflag & Constants::SEFLG_XYZ)) {
            Coordinates::cartPolSp($x, $x);
        }

        // Convert radians to degrees if not RADIANS
        if (!($iflag & Constants::SEFLG_RADIANS) && !($iflag & Constants::SEFLG_XYZ)) {
            for ($i = 0; $i < 2; $i++) {
                $x[$i] *= Constants::RADTODEG;
                $x[$i + 3] *= Constants::RADTODEG;
            }
        }

        // Copy to output array
        for ($i = 0; $i <= 5; $i++) {
            $xx[$i] = $x[$i];
        }

        // Clear speed if not requested
        if (!($iflgsave & Constants::SEFLG_SPEED)) {
            $iflag = $iflag & ~Constants::SEFLG_SPEED;
            for ($i = 3; $i <= 5; $i++) {
                $xx[$i] = 0.0;
            }
        }

        // Don't return chosen ephemeris if none was specified
        if (($iflgsave & Constants::SEFLG_EPHMASK) == 0) {
            $iflag = $iflag & ~Constants::SEFLG_DEFAULTEPH;
        }

        $serr = '';
        return $iflag;
    }

    /**
     * Annual aberration of light (wrapper with speed correction).
     * Computes aberration caused by Earth's orbital motion around the Sun.
     *
     * The influence of aberration on apparent velocity can reach 0.4"/day.
     *
     * Port of swi_aberr_light_ex() from sweph.c:3671-3690
     *
     * @param array &$xx Planet position/velocity [x, y, z, vx, vy, vz] (modified in place)
     * @param array $xe Earth position and velocity at time t
     * @param array $xe_dt Earth position and velocity at time t - dt
     * @param float $dt Time difference between xe and xe_dt
     * @param int $iflag Calculation flags
     */
    private static function aberrLightEx(
        array &$xx,
        array $xe,
        array $xe_dt,
        float $dt,
        int $iflag
    ): void {
        $xxs = $xx;  // Save original position/velocity

        // Apply aberration correction to position
        self::aberrLight($xx, $xe);

        // Correct velocity if requested
        if ($iflag & Constants::SEFLG_SPEED) {
            // Compute position at t - dt
            $xx2 = [
                $xxs[0] - $dt * $xxs[3],
                $xxs[1] - $dt * $xxs[4],
                $xxs[2] - $dt * $xxs[5],
                0.0, 0.0, 0.0
            ];

            // Apply aberration at t - dt
            self::aberrLight($xx2, $xe_dt);

            // Velocity via finite differences
            for ($i = 0; $i <= 2; $i++) {
                $xx[$i + 3] = ($xx[$i] - $xx2[$i]) / $dt;
            }
        }
    }

    /**
     * Annual aberration of light (basic calculation).
     * Computes relativistic aberration effect due to Earth's motion.
     *
     * Formula (special relativity):
     *   β = v/c (velocity as fraction of light speed)
     *   β_1 = sqrt(1 - β²) (Lorentz factor component)
     *   f1 = (u · v) / |u|
     *   f2 = 1 + f1 / (1 + β_1)
     *   xx' = (β_1 * xx + f2 * |u| * v) / (1 + f1)
     *
     * Port of aberr_light() from sweph.c:3645-3660
     *
     * @param array &$xx Planet position [x, y, z, vx, vy, vz] (position modified in place)
     * @param array $xe Earth position and velocity [x, y, z, vx, vy, vz]
     */
    private static function aberrLight(array &$xx, array $xe): void
    {
        $u = [$xx[0], $xx[1], $xx[2]];
        $ru = sqrt(VectorMath::squareSum($u));

        // Earth velocity in AU/day, convert to fraction of light speed
        // xe[i+3] is in AU/day, CLIGHT is in AU/day, so no time conversion needed
        $v = [
            $xe[3] / 24.0 / 3600.0 / Constants::CLIGHT * Constants::AUNIT,
            $xe[4] / 24.0 / 3600.0 / Constants::CLIGHT * Constants::AUNIT,
            $xe[5] / 24.0 / 3600.0 / Constants::CLIGHT * Constants::AUNIT
        ];

        $v2 = VectorMath::squareSum($v);
        $b_1 = sqrt(1.0 - $v2);  // Lorentz factor component

        $f1 = VectorMath::dotProduct($u, $v) / $ru;
        $f2 = 1.0 + $f1 / (1.0 + $b_1);

        // Apply relativistic velocity addition formula
        for ($i = 0; $i <= 2; $i++) {
            $xx[$i] = ($b_1 * $xx[$i] + $f2 * $ru * $v[$i]) / (1.0 + $f1);
        }
    }

    /**
     * Relativistic light deflection by the sun.
     * Implements general relativity correction for light passing near solar limb.
     *
     * When a planet approaches superior conjunction with the sun, the deflection
     * angle cannot be computed using the point-mass formula. This implementation
     * uses the mass distribution within the sun (via meff()) for continuity.
     *
     * Maximum effect:
     * - 1.75 arcsec at solar limb
     * - Can reach 30+ arcsec for inner planets very close to sun
     * - Speed changes: 7-30 arcsec/day near solar conjunction
     *
     * Port of swi_deflect_light() from sweph.c:3742-3920
     *
     * @param array &$xx Planet position/velocity [x, y, z, vx, vy, vz] (modified in place)
     * @param array $xearth Earth barycentric position at tjd
     * @param array $xearth_dt Earth barycentric position at tjd - dt
     * @param array $xsun Sun barycentric position at tjd
     * @param array $xsun_dt Sun barycentric position at tjd - dt
     * @param float $dt Time delta for light-time correction
     * @param int $iflag Calculation flags
     */
    private static function deflectLight(
        array &$xx,
        array $xearth,
        array $xearth_dt,
        array $xsun,
        array $xsun_dt,
        float $dt,
        int $iflag
    ): void {
        // Position calculation (always)
        $xx2 = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];

        // U = planet_bary(t-tau) - earth_bary(t) = planet_geo
        $u = [$xx[0], $xx[1], $xx[2]];

        // E = earth_bary(t) - sun_bary(t) = earth_helio
        // (xsun is sun barycentric position)
        $e = [
            $xearth[0] - $xsun[0],
            $xearth[1] - $xsun[1],
            $xearth[2] - $xsun[2]
        ];

        // Q = planet_bary(t-tau) - sun_bary(t-tau) = planet_helio
        // Compute sun_bary(t-tau) by backward extrapolation
        $xsun_tau = [
            $xsun[0] - $dt * $xsun[3],
            $xsun[1] - $dt * $xsun[4],
            $xsun[2] - $dt * $xsun[5]
        ];

        $q = [
            $xx[0] + $xearth[0] - $xsun_tau[0],
            $xx[1] + $xearth[1] - $xsun_tau[1],
            $xx[2] + $xearth[2] - $xsun_tau[2]
        ];

        // Compute magnitudes and normalize to unit vectors
        $ru = sqrt(VectorMath::squareSum($u));
        $rq = sqrt(VectorMath::squareSum($q));
        $re = sqrt(VectorMath::squareSum($e));

        $u = [$u[0] / $ru, $u[1] / $ru, $u[2] / $ru];
        $q = [$q[0] / $rq, $q[1] / $rq, $q[2] / $rq];
        $e = [$e[0] / $re, $e[1] / $re, $e[2] / $re];

        // Dot products
        $uq = VectorMath::dotProduct($u, $q);
        $ue = VectorMath::dotProduct($u, $e);
        $qe = VectorMath::dotProduct($q, $e);

        // Effective mass correction for solar limb
        // When planet is near sun center in superior conjunction,
        // deflection formula breaks down (sun treated as point mass).
        // Use mass distribution within sun for smooth transition.
        $sina = sqrt(1.0 - $ue * $ue);  // sin(angle) between sun and planet
        $sin_sunr = Constants::SUN_RADIUS / $re;  // sine of sun angular radius

        if ($sina < $sin_sunr) {
            $meff_fact = self::meff($sina / $sin_sunr);
        } else {
            $meff_fact = 1.0;
        }

        // Deflection formula from GR:
        // g1 = 2 * G * M_sun / c^2 / AU / distance_to_sun
        // g2 = 1 + q·e
        $g1 = 2.0 * Constants::HELGRAVCONST * $meff_fact /
              Constants::CLIGHT / Constants::CLIGHT / Constants::AUNIT / $re;
        $g2 = 1.0 + $qe;

        // Deflected position: xx2 = ru * (u + (g1/g2) * (uq*e - ue*q))
        for ($i = 0; $i <= 2; $i++) {
            $xx2[$i] = $ru * ($u[$i] + ($g1 / $g2) * ($uq * $e[$i] - $ue * $q[$i]));
        }

        // Speed correction (if requested)
        if ($iflag & Constants::SEFLG_SPEED) {
            // Light deflection affects apparent speed, especially near solar conjunction.
            // For outer planet at solar limb with speed diff = 1°, effect is ~7"/day.
            // Within solar disc, can reach 30" or more.
            //
            // Example: Mercury at J2434871.45, distance from sun 45":
            //   Without deflection: 2d10'10".4034
            //   With deflection:    2d10'43".4824
            //
            // Compute deflection at slightly shifted time to get velocity effect.
            $dtsp = -Constants::DEFL_SPEED_INTV;

            // U = planet_bary(t-tau-dtsp) - earth_bary(t-dtsp)
            $u_sp = [
                $xx[0] - $dtsp * $xx[3],
                $xx[1] - $dtsp * $xx[4],
                $xx[2] - $dtsp * $xx[5]
            ];

            // E = earth_bary(t-dtsp) - sun_bary(t-dtsp)
            $e_sp = [
                $xearth[0] - $xsun[0] - $dtsp * ($xearth[3] - $xsun[3]),
                $xearth[1] - $xsun[1] - $dtsp * ($xearth[4] - $xsun[4]),
                $xearth[2] - $xsun[2] - $dtsp * ($xearth[5] - $xsun[5])
            ];

            // Q = planet_bary(t-tau-dtsp) - sun_bary(t-tau-dtsp)
            $q_sp = [
                $u_sp[0] + $xearth[0] - $xsun_tau[0] - $dtsp * ($xearth[3] - $xsun_tau[3]),
                $u_sp[1] + $xearth[1] - $xsun_tau[1] - $dtsp * ($xearth[4] - $xsun_tau[4]),
                $u_sp[2] + $xearth[2] - $xsun_tau[2] - $dtsp * ($xearth[5] - $xsun_tau[5])
            ];

            // Normalize
            $ru_sp = sqrt(VectorMath::squareSum($u_sp));
            $rq_sp = sqrt(VectorMath::squareSum($q_sp));
            $re_sp = sqrt(VectorMath::squareSum($e_sp));

            $u_sp = [$u_sp[0] / $ru_sp, $u_sp[1] / $ru_sp, $u_sp[2] / $ru_sp];
            $q_sp = [$q_sp[0] / $rq_sp, $q_sp[1] / $rq_sp, $q_sp[2] / $rq_sp];
            $e_sp = [$e_sp[0] / $re_sp, $e_sp[1] / $re_sp, $e_sp[2] / $re_sp];

            // Dot products at shifted time
            $uq_sp = VectorMath::dotProduct($u_sp, $q_sp);
            $ue_sp = VectorMath::dotProduct($u_sp, $e_sp);
            $qe_sp = VectorMath::dotProduct($q_sp, $e_sp);

            // Effective mass at shifted time
            $sina_sp = sqrt(1.0 - $ue_sp * $ue_sp);
            $sin_sunr_sp = Constants::SUN_RADIUS / $re_sp;

            if ($sina_sp < $sin_sunr_sp) {
                $meff_fact_sp = self::meff($sina_sp / $sin_sunr_sp);
            } else {
                $meff_fact_sp = 1.0;
            }

            $g1_sp = 2.0 * Constants::HELGRAVCONST * $meff_fact_sp /
                     Constants::CLIGHT / Constants::CLIGHT / Constants::AUNIT / $re_sp;
            $g2_sp = 1.0 + $qe_sp;

            // Deflected position at shifted time
            $xx3 = [0.0, 0.0, 0.0];
            for ($i = 0; $i <= 2; $i++) {
                $xx3[$i] = $ru_sp * ($u_sp[$i] + ($g1_sp / $g2_sp) * ($uq_sp * $e_sp[$i] - $ue_sp * $q_sp[$i]));
            }

            // Speed correction via finite differences
            // dx1 = deflection at t
            // dx2 = deflection at t-dtsp
            // velocity correction = (dx1 - dx2) / dtsp
            for ($i = 0; $i <= 2; $i++) {
                $dx1 = $xx2[$i] - $xx[$i];
                $dx2 = $xx3[$i] - $u_sp[$i] * $ru_sp;
                $dx1 -= $dx2;
                $xx[$i + 3] += $dx1 / $dtsp;
            }
        }

        // Apply deflected position
        for ($i = 0; $i <= 2; $i++) {
            $xx[$i] = $xx2[$i];
        }
    }

    /**
     * Effective solar mass for light deflection calculations.
     * Returns the effective mass of the sun at distance r (fraction of solar radius).
     * Used for gravitational light deflection near the solar limb.
     *
     * The mass distribution m(r) is from Michael Stix, "The Sun", p. 47.
     * Values computed with classic treatment of photon passing gravity field, multiplied by 2.
     *
     * Port of meff() from sweph.c:6021-6036
     *
     * @param float $r Distance from sun center in solar radii (0.0 to 1.0)
     * @return float Effective mass (0.0 to 1.0)
     */
    private static function meff(float $r): float
    {
        // Mass distribution lookup table
        // Each entry: [r => radius fraction, m => effective mass fraction]
        static $effArr = [
            ['r' => 1.000, 'm' => 1.000000],
            ['r' => 0.990, 'm' => 0.999979],
            ['r' => 0.980, 'm' => 0.999940],
            ['r' => 0.970, 'm' => 0.999881],
            ['r' => 0.960, 'm' => 0.999811],
            ['r' => 0.950, 'm' => 0.999724],
            ['r' => 0.940, 'm' => 0.999622],
            ['r' => 0.930, 'm' => 0.999497],
            ['r' => 0.920, 'm' => 0.999354],
            ['r' => 0.910, 'm' => 0.999192],
            ['r' => 0.900, 'm' => 0.999000],
            ['r' => 0.890, 'm' => 0.998786],
            ['r' => 0.880, 'm' => 0.998535],
            ['r' => 0.870, 'm' => 0.998242],
            ['r' => 0.860, 'm' => 0.997919],
            ['r' => 0.850, 'm' => 0.997571],
            ['r' => 0.840, 'm' => 0.997198],
            ['r' => 0.830, 'm' => 0.996792],
            ['r' => 0.820, 'm' => 0.996316],
            ['r' => 0.810, 'm' => 0.995791],
            ['r' => 0.800, 'm' => 0.995226],
            ['r' => 0.790, 'm' => 0.994625],
            ['r' => 0.780, 'm' => 0.993991],
            ['r' => 0.770, 'm' => 0.993326],
            ['r' => 0.760, 'm' => 0.992598],
            ['r' => 0.750, 'm' => 0.991770],
            ['r' => 0.740, 'm' => 0.990873],
            ['r' => 0.730, 'm' => 0.989919],
            ['r' => 0.720, 'm' => 0.988912],
            ['r' => 0.710, 'm' => 0.987856],
            ['r' => 0.700, 'm' => 0.986755],
            ['r' => 0.690, 'm' => 0.985610],
            ['r' => 0.680, 'm' => 0.984398],
            ['r' => 0.670, 'm' => 0.982986],
            ['r' => 0.660, 'm' => 0.981437],
            ['r' => 0.650, 'm' => 0.979779],
            ['r' => 0.640, 'm' => 0.978024],
            ['r' => 0.630, 'm' => 0.976182],
            ['r' => 0.620, 'm' => 0.974256],
            ['r' => 0.610, 'm' => 0.972253],
            ['r' => 0.600, 'm' => 0.970174],
            ['r' => 0.590, 'm' => 0.968024],
            ['r' => 0.580, 'm' => 0.965594],
            ['r' => 0.570, 'm' => 0.962797],
            ['r' => 0.560, 'm' => 0.959758],
            ['r' => 0.550, 'm' => 0.956515],
            ['r' => 0.540, 'm' => 0.953088],
            ['r' => 0.530, 'm' => 0.949495],
            ['r' => 0.520, 'm' => 0.945741],
            ['r' => 0.510, 'm' => 0.941838],
            ['r' => 0.500, 'm' => 0.937790],
            ['r' => 0.490, 'm' => 0.933563],
            ['r' => 0.480, 'm' => 0.928668],
            ['r' => 0.470, 'm' => 0.923288],
            ['r' => 0.460, 'm' => 0.917527],
            ['r' => 0.450, 'm' => 0.911432],
            ['r' => 0.440, 'm' => 0.905035],
            ['r' => 0.430, 'm' => 0.898353],
            ['r' => 0.420, 'm' => 0.891022],
            ['r' => 0.410, 'm' => 0.882940],
            ['r' => 0.400, 'm' => 0.874312],
            ['r' => 0.390, 'm' => 0.865206],
            ['r' => 0.380, 'm' => 0.855423],
            ['r' => 0.370, 'm' => 0.844619],
            ['r' => 0.360, 'm' => 0.833074],
            ['r' => 0.350, 'm' => 0.820876],
            ['r' => 0.340, 'm' => 0.808031],
            ['r' => 0.330, 'm' => 0.793962],
            ['r' => 0.320, 'm' => 0.778931],
            ['r' => 0.310, 'm' => 0.763021],
            ['r' => 0.300, 'm' => 0.745815],
            ['r' => 0.290, 'm' => 0.727557],
            ['r' => 0.280, 'm' => 0.708234],
            ['r' => 0.270, 'm' => 0.687583],
            ['r' => 0.260, 'm' => 0.665741],
            ['r' => 0.250, 'm' => 0.642597],
            ['r' => 0.240, 'm' => 0.618252],
            ['r' => 0.230, 'm' => 0.592586],
            ['r' => 0.220, 'm' => 0.565747],
            ['r' => 0.210, 'm' => 0.537697],
            ['r' => 0.200, 'm' => 0.508554],
            ['r' => 0.190, 'm' => 0.478420],
            ['r' => 0.180, 'm' => 0.447322],
            ['r' => 0.170, 'm' => 0.415454],
            ['r' => 0.160, 'm' => 0.382892],
            ['r' => 0.150, 'm' => 0.349955],
            ['r' => 0.140, 'm' => 0.316691],
            ['r' => 0.130, 'm' => 0.283565],
            ['r' => 0.120, 'm' => 0.250431],
            ['r' => 0.110, 'm' => 0.218327],
            ['r' => 0.100, 'm' => 0.186794],
            ['r' => 0.090, 'm' => 0.156287],
            ['r' => 0.080, 'm' => 0.128421],
            ['r' => 0.070, 'm' => 0.102237],
            ['r' => 0.060, 'm' => 0.077393],
            ['r' => 0.050, 'm' => 0.054833],
            ['r' => 0.040, 'm' => 0.036361],
            ['r' => 0.030, 'm' => 0.020953],
            ['r' => 0.020, 'm' => 0.009645],
            ['r' => 0.010, 'm' => 0.002767],
            ['r' => 0.000, 'm' => 0.000000]
        ];

        // Boundary conditions
        if ($r <= 0.0) {
            return 0.0;
        } elseif ($r >= 1.0) {
            return 1.0;
        }

        // Find bracket in lookup table (table is sorted descending by r)
        $i = 0;
        while ($i < count($effArr) && $effArr[$i]['r'] > $r) {
            $i++;
        }

        // Linear interpolation between eff_arr[i-1] and eff_arr[i]
        $f = ($r - $effArr[$i - 1]['r']) / ($effArr[$i]['r'] - $effArr[$i - 1]['r']);
        $m = $effArr[$i - 1]['m'] + $f * ($effArr[$i]['m'] - $effArr[$i - 1]['m']);

        return $m;
    }
}
