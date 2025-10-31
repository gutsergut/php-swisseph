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

        // TODO: Part 7 - Light deflection
        // TODO: Part 8 - Aberration
        // TODO: Part 9 - Precession
        // TODO: Part 10 - Nutation
        // TODO: Part 11 - Coordinate transformations
        // TODO: Part 12 - Sidereal positions
        // TODO: Part 13 - Final conversions

        $xx = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $serr = 'calcFromRecord() partially implemented - Parts 7-13 TODO';
        return Constants::SE_ERR;
    }
}
