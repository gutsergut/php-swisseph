<?php

use Swisseph\Julian;
use Swisseph\State;
use Swisseph\DeltaT;
use Swisseph\Sidereal;
use Swisseph\Utc;
use Swisseph\ErrorCodes;
use Swisseph\Constants;
use Swisseph\Sun;
use Swisseph\Moon;
use Swisseph\Mercury;
use Swisseph\Venus;
use Swisseph\Mars;
use Swisseph\Jupiter;
use Swisseph\Saturn;
use Swisseph\Uranus;
use Swisseph\Neptune;
use Swisseph\Pluto;
use Swisseph\Houses;
use Swisseph\Obliquity;
use Swisseph\Math;
use Swisseph\Domain\Houses\Registry as HouseRegistry;
use Swisseph\Domain\Houses\Support\AscMc as HousesAscMc;
use Swisseph\Domain\Houses\Support\CuspPostprocessor as CuspPost;
use Swisseph\Swe\Functions\PlanetsFunctions;
use Swisseph\Swe\Functions\TimeFunctions;
use Swisseph\Swe\Functions\HorizonFunctions;
use Swisseph\Swe\Functions\RefractionFunctions;
use Swisseph\Swe\Functions\TransformFunctions;
use Swisseph\Swe\Functions\NodesApsidesFunctions;
use Swisseph\Swe\Functions\OrbitalElementsFunctions;
use Swisseph\Swe\Functions\PhenoFunctions;
use Swisseph\Swe\Functions\FixstarFunctions;
use Swisseph\Swe\Functions\StarFunctions;
use Swisseph\Swe\Functions\LegacyStarFunctions;
use Swisseph\Swe\Functions\SolarEclipseFunctions;
use Swisseph\Swe\Functions\LunarEclipseWhenFunctions;
use Swisseph\Swe\Functions\LunarEclipseWhenLocFunctions;
use Swisseph\Swe\Functions\LunarOccultationWhenGlobFunctions;
use Swisseph\Swe\Functions\SolarEclipseWhereFunctions;
use Swisseph\Swe\Functions\GauquelinSectorFunctions;
use Swisseph\Swe\Functions\CrossingFunctions;

if (!function_exists('swe_julday')) {
    /**
     * PHP wrapper compatible with Swiss Ephemeris: swe_julday
     * @param int $y
     * @param int $m
     * @param int $d
     * @param float $ut
     * @param int $gregflag 1=Gregorian, 0=Julian
     */
    function swe_julday(int $y, int $m, int $d, float $ut, int $gregflag): float
    {
        return Julian::toJulianDay($y, $m, $d, $ut, $gregflag);
    }
}

if (!function_exists('swe_revjul')) {
    /**
     * PHP wrapper compatible with Swiss Ephemeris: swe_revjul
     * @param float $jd
     * @param int $gregflag
     * @return array{y:int,m:int,d:int,ut:float}
     */
    function swe_revjul(float $jd, int $gregflag): array
    {
        return Julian::fromJulianDay($jd, $gregflag);
    }
}

if (!function_exists('swe_set_ephe_path')) {
    function swe_set_ephe_path(string $path): void
    {
        State::setEphePath($path);
        // Also set in SwedState for Swiss Ephemeris file reader
        \Swisseph\SwephFile\SwedState::getInstance()->setEphePath($path);
    }
}
if (!function_exists('swe_set_jpl_file')) {
    function swe_set_jpl_file(string $fname): void
    {
        State::setJplFile($fname);
    }
}
if (!function_exists('swe_set_topo')) {
    function swe_set_topo(float $lon, float $lat, float $alt): void
    {
        State::setTopo($lon, $lat, $alt);

        // C API: swe_set_topo() directly sets swed.topd (sweph.c:7303-7318)
        // Update SwedState immediately like C code does
        $swed = \Swisseph\SwephFile\SwedState::getInstance();        // Only update if values changed (like C does at sweph.c:7306-7309)
        if (!$swed->geoposIsSet
            || $swed->topd->geolon != $lon
            || $swed->topd->geolat != $lat
            || $swed->topd->geoalt != $alt
        ) {
            $swed->topd->geolon = $lon;
            $swed->topd->geolat = $lat;
            $swed->topd->geoalt = $alt;
            $swed->geoposIsSet = true;
            $swed->topd->teval = 0.0; // Force recalculation (C does at sweph.c:7316)
        }
    }
}
if (!function_exists('swe_set_tid_acc')) {
    /**
     * Set tidal acceleration (used in Delta-T calculation)
     * C API: void swe_set_tid_acc(double t_acc);
     *
     * Sets the tidal acceleration value used in automatic Delta-T calculation.
     * Pass SE_TIDAL_AUTOMATIC (999999.0) to restore default value (-25.80).
     *
     * Values from JPL Ephemerides:
     * - DE200: -23.8946
     * - DE403/DE404/DE405/DE406: -25.826
     * - DE430/DE431: -25.80 (current default)
     *
     * @param float $t_acc Tidal acceleration in arcsec/cy^2, or SE_TIDAL_AUTOMATIC
     * @return void
     */
    function swe_set_tid_acc(float $t_acc): void
    {
        \Swisseph\Swe\Functions\MiscUtilityFunctions::setTidAcc($t_acc);
    }
}

if (!function_exists('swe_deltat_ex')) {
    /**
     * Delta T wrapper: returns TT-UT in days for JD(UT).
     * @param float $jdut Julian Day (UT)
     * @param int $ephe_flag Ephemeris flags
     * @param string|null $serr (by-ref) error text if any
     * @return float Delta T in days
     */
    function swe_deltat_ex(float $jdut, int $ephe_flag = 0, ?string &$serr = null): float
    {
        return TimeFunctions::deltatEx($jdut, $ephe_flag, $serr);
    }
}

if (!function_exists('swe_deltat')) {
    /**
     * Delta T wrapper (simplified): returns TT-UT in days for JD(UT).
     * @param float $jdut Julian Day (UT)
     * @return float Delta T in days
     */
    function swe_deltat(float $jdut): float
    {
        return TimeFunctions::deltat($jdut);
    }
}

// Sidereal time wrappers
if (!function_exists('swe_sidtime')) {
    function swe_sidtime(float $jdut): float
    {
        return Sidereal::gmstHoursFromJdUt($jdut);
    }
}

// UTC<->JD wrappers (simplified; no leap seconds table)
if (!function_exists('swe_utc_to_jd')) {
    /**
     * Returns array [jd_et (TT), jd_ut]
     */
    function swe_utc_to_jd(
        int $y,
        int $m,
        int $d,
        int $hour,
        int $min,
        float $sec,
        int $gregflag,
        ?string &$serr = null
    ): array {
        $serr = null;
        if ($gregflag !== 0 && $gregflag !== 1) {
            $serr = ErrorCodes::compose(ErrorCodes::INVALID_CALENDAR, "gregflag=$gregflag");
            // still try to proceed by treating any non-zero as Gregorian for resilience
            $gregflag = $gregflag ? 1 : 0;
        }
        [$jd_ut, $jd_tt] = Utc::utcToJd($y, $m, $d, $hour, $min, $sec, $gregflag);
        return [$jd_tt, $jd_ut];
    }
}
if (!function_exists('swe_jd_to_utc')) {
    /**
     * Accepts JD(UT); returns [y, m, d, hour, min, sec]
     */
    function swe_jd_to_utc(float $jd_ut, int $gregflag, ?string &$serr = null): array
    {
        $serr = null;
        if ($gregflag !== 0 && $gregflag !== 1) {
            $serr = ErrorCodes::compose(ErrorCodes::INVALID_CALENDAR, "gregflag=$gregflag");
            $gregflag = $gregflag ? 1 : 0;
        }
        return Utc::jdToUtc($jd_ut, $gregflag);
    }
}
if (!function_exists('swe_sidtime0')) {
    /**
     * Sidereal time with nutation correction
     *
     * @param float $jdut Julian day (UT)
     * @param float $eps Obliquity (degrees)
     * @param float $nut Nutation in longitude (degrees)
     * @param float $nut2 Nutation in obliquity (degrees) - currently unused in simplified version
     * @return float Apparent sidereal time in hours
     */
    function swe_sidtime0(float $jdut, float $eps, float $nut, float $nut2): float
    {
        return Sidereal::sidtime0($jdut, $eps, $nut);
    }
}

// Equation of time
if (!function_exists('swe_time_equ')) {
    /**
     * Compute equation of time E (days) for JD(UT)
     * @return int 0 on success; SE_ERR on failure
     */
    function swe_time_equ(float $jd_ut, ?float &$E = null, ?string &$serr = null): int
    {
        return TimeFunctions::timeEqu($jd_ut, $E, $serr);
    }
}

// Sidereal / Ayanamsha API (minimal)
if (!function_exists('swe_set_sid_mode')) {
    /**
     * Set sidereal mode and user-defined ayanamsha parameters.
     *
     * Port of swe_set_sid_mode() from sweph.c
     *
     * @param int $sid_mode Sidereal mode (SE_SIDM_*) combined with options (SE_SIDBIT_*)
     *                      Options are in bits above SE_SIDBITS (256)
     *                      Example: SE_SIDM_LAHIRI | SE_SIDBIT_ECL_DATE
     * @param float $t0 User-defined reference epoch (JD, only for SE_SIDM_USER)
     * @param float $ayan_t0 User-defined ayanamsha at t0 (degrees, only for SE_SIDM_USER)
     */
    function swe_set_sid_mode(int $sid_mode, float $t0, float $ayan_t0): void
    {
        // Extract mode and options
        // In C: sid_mode % SE_SIDBITS gives mode, rest are options
        $mode = $sid_mode % \Swisseph\Constants::SE_SIDBITS;
        $opts = $sid_mode - $mode; // or: ($sid_mode & ~(\Swisseph\Constants::SE_SIDBITS - 1))

        \Swisseph\State::setSidMode($mode, $opts, $t0, $ayan_t0);
    }
}
if (!function_exists('swe_get_ayanamsa_ex')) {
    /**
     * Get ayanamsha for JD(TT) with iflag (ignored here except SEFLG_SIDEREAL presence) into $daya (degrees)
     */
    function swe_get_ayanamsa_ex(float $jd_tt, int $iflag, ?float &$daya = null, ?string &$serr = null): int
    {
        $serr = null;
        $daya = \Swisseph\Sidereal::ayanamshaDegFromJdTT($jd_tt);
        return 0;
    }
}
if (!function_exists('swe_get_ayanamsa_ex_ut')) {
    function swe_get_ayanamsa_ex_ut(float $jd_ut, int $iflag, ?float &$daya = null, ?string &$serr = null): int
    {
        $dt = \Swisseph\DeltaT::deltaTSecondsFromJd($jd_ut) / 86400.0;
        $jd_tt = $jd_ut + $dt;
        return swe_get_ayanamsa_ex($jd_tt, $iflag, $daya, $serr);
    }
}
if (!function_exists('swe_get_ayanamsa_name')) {
    function swe_get_ayanamsa_name(int $sid_mode): string
    {
        return match ($sid_mode) {
            \Swisseph\Constants::SE_SIDM_FAGAN_BRADLEY => 'Fagan/Bradley',
            \Swisseph\Constants::SE_SIDM_LAHIRI => 'Lahiri',
            \Swisseph\Constants::SE_SIDM_DELUCE => 'De Luce',
            \Swisseph\Constants::SE_SIDM_RAMAN => 'Raman',
            \Swisseph\Constants::SE_SIDM_USHASHASHI => 'Usha/Shashi',
            \Swisseph\Constants::SE_SIDM_KRISHNAMURTI => 'Krishnamurti',
            \Swisseph\Constants::SE_SIDM_DJWHAL_KHUL => 'Djwhal Khul',
            \Swisseph\Constants::SE_SIDM_YUKTESHWAR => 'Yukteshwar',
            \Swisseph\Constants::SE_SIDM_JN_BHASIN => 'J.N. Bhasin',
            \Swisseph\Constants::SE_SIDM_BABYLONIAN_KUGLER1 => 'Babylonian/Kugler 1',
            \Swisseph\Constants::SE_SIDM_BABYLONIAN_KUGLER2 => 'Babylonian/Kugler 2',
            \Swisseph\Constants::SE_SIDM_BABYLONIAN_KUGLER3 => 'Babylonian/Kugler 3',
            \Swisseph\Constants::SE_SIDM_BABYLONIAN_HUBER => 'Babylonian/Huber',
            \Swisseph\Constants::SE_SIDM_BABYLONIAN_SCHRAM => 'Babylonian/Eta Piscium',
            \Swisseph\Constants::SE_SIDM_BABYLONIAN_ESHEL => 'Babylonian/Aldebaran = 15 Tau',
            \Swisseph\Constants::SE_SIDM_ARYABHATA => 'Hipparchos',
            \Swisseph\Constants::SE_SIDM_ARYABHATA_522 => 'Sassanian',
            \Swisseph\Constants::SE_SIDM_GALCENT_0SAG => 'Galact. Center = 0 Sag',
            \Swisseph\Constants::SE_SIDM_J2000 => 'J2000',
            \Swisseph\Constants::SE_SIDM_J1900 => 'J1900',
            \Swisseph\Constants::SE_SIDM_B1950 => 'B1950',
            \Swisseph\Constants::SE_SIDM_SURYASIDDHANTA => 'Suryasiddhanta',
            \Swisseph\Constants::SE_SIDM_SURYASIDDHANTA_MSUN => 'Suryasiddhanta, mean Sun',
            \Swisseph\Constants::SE_SIDM_ARYABHATA_MSUN => 'Aryabhata',
            \Swisseph\Constants::SE_SIDM_ARYABHATA_MSUN2 => 'Aryabhata, mean Sun',
            \Swisseph\Constants::SE_SIDM_SS_REVATI => 'SS Revati',
            \Swisseph\Constants::SE_SIDM_SS_CITRA => 'SS Citra',
            \Swisseph\Constants::SE_SIDM_TRUE_CITRA => 'True Citra',
            \Swisseph\Constants::SE_SIDM_TRUE_REVATI => 'True Revati',
            \Swisseph\Constants::SE_SIDM_TRUE_PUSHYA => 'True Pushya (PVRN Rao)',
            \Swisseph\Constants::SE_SIDM_GALCENT_RGILBRAND => 'Galactic Center (Gil Brand)',
            \Swisseph\Constants::SE_SIDM_GALEQU_IAU1958 => 'Galactic Equator (IAU1958)',
            \Swisseph\Constants::SE_SIDM_GALEQU_TRUE => 'Galactic Equator',
            \Swisseph\Constants::SE_SIDM_GALEQU_MULA => 'Galactic Equator mid-Mula',
            \Swisseph\Constants::SE_SIDM_GALALIGN_MARDYKS => 'Skydram (Mardyks)',
            \Swisseph\Constants::SE_SIDM_TRUE_MULA => 'True Mula (Chandra Hari)',
            \Swisseph\Constants::SE_SIDM_GALCENT_MULA_WILHELM => 'Dhruva/Gal.Center/Mula (Wilhelm)',
            \Swisseph\Constants::SE_SIDM_ARYABHATA_522_ALT => 'Aryabhata 522',
            \Swisseph\Constants::SE_SIDM_BABYL_BRITTON => 'Babylonian/Britton',
            \Swisseph\Constants::SE_SIDM_TRUE_SHEORAN => '"Vedic"/Sheoran',
            \Swisseph\Constants::SE_SIDM_GALCENT_COCHRANE => 'Cochrane (Gal.Center = 0 Cap)',
            \Swisseph\Constants::SE_SIDM_GALEQU_FIORENZA => 'Galactic Equator (Fiorenza)',
            \Swisseph\Constants::SE_SIDM_VALENS_MOON => 'Vettius Valens',
            \Swisseph\Constants::SE_SIDM_LAHIRI_1940 => 'Lahiri 1940',
            \Swisseph\Constants::SE_SIDM_LAHIRI_VP285 => 'Lahiri VP285',
            \Swisseph\Constants::SE_SIDM_KRISHNAMURTI_VP291 => 'Krishnamurti-Senthilathiban',
            \Swisseph\Constants::SE_SIDM_LAHIRI_ICRC => 'Lahiri ICRC',
            \Swisseph\Constants::SE_SIDM_USER => 'User-defined',
            default => 'Sidereal mode ' . $sid_mode,
        };
    }
}

// Skeletons for swe_calc/swe_calc_ut (not yet implemented)
if (!function_exists('swe_calc')) {
    /**
     * Thin facade to Swe\Functions\PlanetsFunctions::calc
     */
    function swe_calc(float $jd_tt, int $ipl, int $iflag, array &$xx, ?string &$serr = null): int
    {
        return PlanetsFunctions::calc($jd_tt, $ipl, $iflag, $xx, $serr);
    }
}
if (!function_exists('swe_calc_ut')) {
    /**
     * Thin facade to Swe\Functions\PlanetsFunctions::calcUt
     */
    function swe_calc_ut(float $jd_ut, int $ipl, int $iflag, array &$xx, ?string &$serr = null): int
    {
        return PlanetsFunctions::calcUt($jd_ut, $ipl, $iflag, $xx, $serr);
    }
}

if (!function_exists('swe_get_orbital_elements')) {
    /**
     * Get orbital elements for a planet
     *
     * Thin facade to Swe\Functions\OrbitalElementsFunctions::getOrbitalElements
     */
    function swe_get_orbital_elements(
        float $jd_et,
        int $ipl,
        int $iflag,
        array &$dret = null,
        ?string &$serr = null
    ): int {
        return OrbitalElementsFunctions::getOrbitalElements($jd_et, $ipl, $iflag, $dret, $serr);
    }
}

if (!function_exists('swe_orbit_max_min_true_distance')) {
    /**
     * Calculate maximum, minimum and true distance between planet and Earth (or Sun)
     *
     * This function calculates the maximum possible distance, the minimum possible distance,
     * and the current true distance of a planet, the EMB, or an asteroid.
     *
     * @param float $tjd_et Julian day in ET/TT
     * @param int $ipl Planet number
     * @param int $iflag Calculation flags (SEFLG_EPHMASK | SEFLG_HELCTR | SEFLG_BARYCTR)
     * @param float &$dmax Output: maximum distance (AU)
     * @param float &$dmin Output: minimum distance (AU)
     * @param float &$dtrue Output: true distance (AU)
     * @param string|null &$serr Error message
     * @return int SE_OK or SE_ERR
     */
    function swe_orbit_max_min_true_distance(
        float $tjd_et,
        int $ipl,
        int $iflag,
        float &$dmax,
        float &$dmin,
        float &$dtrue,
        ?string &$serr = null
    ): int {
        return OrbitalElementsFunctions::orbitMaxMinTrueDistance($tjd_et, $ipl, $iflag, $dmax, $dmin, $dtrue, $serr);
    }
}

// Horizontal conversions and refraction
if (!function_exists('swe_azalt')) {
    /**
     * Convert equatorial or ecliptic coordinates to horizontal coordinates
     *
     * C API: void swe_azalt(double tjd_ut, int32 calc_flag, double *geopos,
     *                       double atpress, double attemp, double *xin, double *xaz)
     * Port from swecl.c:2788-2822
     *
     * IMPORTANT: This matches C API parameter order exactly:
     * (tjd_ut, calc_flag, geopos, atpress, attemp, xin, xaz)
     *
     * @param float $tjd_ut Julian day, Universal Time
     * @param int $calc_flag SE_ECL2HOR (0) or SE_EQU2HOR (1)
     * @param array $geopos [longitude (deg), latitude (deg), height (m)]
     * @param float $atpress Atmospheric pressure in mbar/hPa (0 = auto-estimate)
     * @param float $attemp Atmospheric temperature in °C
     * @param array $xin Input coordinates [coord1, coord2] in degrees
     * @param array $xaz Output [azimuth, true_alt, apparent_alt] in degrees
     * @return void
     */
    function swe_azalt(
        float $tjd_ut,
        int $calc_flag,
        array $geopos,
        float $atpress,
        float $attemp,
        array $xin,
        array &$xaz
    ): void {
        HorizonFunctions::azalt($tjd_ut, $calc_flag, $geopos, $atpress, $attemp, $xin, $xaz);
    }
}
if (!function_exists('swe_azalt_rev')) {
    /**
     * Convert horizontal coordinates to equatorial or ecliptic coordinates
     *
     * C API: void swe_azalt_rev(double tjd_ut, int32 calc_flag, double *geopos,
     *                           double *xin, double *xout)
     * Port from swecl.c:2838-2878
     *
     * @param float $tjd_ut Julian day, Universal Time
     * @param int $calc_flag SE_HOR2ECL (0) or SE_HOR2EQU (1)
     * @param array $geopos [longitude (deg), latitude (deg), height (m)]
     * @param array $xin Input [azimuth, true_altitude] in degrees
     * @param array $xout Output coordinates [coord1, coord2] in degrees
     * @return void
     */
    function swe_azalt_rev(
        float $tjd_ut,
        int $calc_flag,
        array $geopos,
        array $xin,
        array &$xout
    ): void {
        HorizonFunctions::azalt_rev($tjd_ut, $calc_flag, $geopos, $xin, $xout);
    }
}
if (!function_exists('swe_refrac')) {
    /**
     * Atmospheric refraction: true<->apparent altitude conversion.
     * Port of swe_refrac() from swecl.c:2887
     *
     * Uses algorithm from Meeus for different altitude ranges.
     *
     * @param float $inalt Input altitude in degrees (true or apparent, depending on calc_flag)
     * @param float $atpress Atmospheric pressure in millibars (hectopascals)
     * @param float $attemp Atmospheric temperature in degrees Celsius
     * @param int $calc_flag Constants::SE_TRUE_TO_APP (0) or SE_APP_TO_TRUE (1)
     * @return float Output altitude in degrees (apparent or true, depending on calc_flag)
     *
     * Mirrors C API: swe_refrac(inalt, atpress, attemp, calc_flag)
     */
    function swe_refrac(float $inalt, float $atpress, float $attemp, int $calc_flag): float
    {
        return RefractionFunctions::refrac($inalt, $atpress, $attemp, $calc_flag);
    }
}

if (!function_exists('swe_refrac_extended')) {
    /**
     * Extended atmospheric refraction with observer altitude and lapse rate.
     * Port of swe_refrac_extended() from swecl.c:3035
     *
     * More accurate than swe_refrac():
     * - Allows correct calculation for altitudes above sea level > 0
     * - Handles negative apparent heights (below ideal horizon)
     * - Allows manipulation of refraction constant via lapse rate
     *
     * @param float $inalt Altitude of object above geometric horizon in degrees
     * @param float $geoalt Observer altitude above sea level in meters
     * @param float $atpress Atmospheric pressure in millibars (hectopascals)
     * @param float $attemp Atmospheric temperature in degrees Celsius
     * @param float $lapse_rate Temperature lapse rate (dT/dh) in K/m (default: SE_LAPSE_RATE = 0.0065)
     * @param int $calc_flag Constants::SE_TRUE_TO_APP (0) or SE_APP_TO_TRUE (1)
     * @param array|null $dret Optional return array with 4 elements:
     *                         [0] = true altitude (if possible, else input value)
     *                         [1] = apparent altitude (if possible, else input value)
     *                         [2] = refraction value in degrees
     *                         [3] = dip of horizon in degrees
     *                         Body is above horizon if dret[0] != dret[1]
     * @return float Apparent altitude (SE_TRUE_TO_APP) or true altitude (SE_APP_TO_TRUE)
     *
     * Mirrors C API: swe_refrac_extended(inalt, geoalt, atpress, attemp, lapse_rate, calc_flag, dret)
     */
    function swe_refrac_extended(
        float $inalt,
        float $geoalt,
        float $atpress,
        float $attemp,
        float $lapse_rate,
        int $calc_flag,
        ?array &$dret = null
    ): float {
        return RefractionFunctions::refracExtended(
            $inalt,
            $geoalt,
            $atpress,
            $attemp,
            $lapse_rate,
            $calc_flag,
            $dret
        );
    }
}

if (!function_exists('swe_set_lapse_rate')) {
    /**
     * Set atmospheric lapse rate (temperature gradient).
     * Port of swe_set_lapse_rate() from swecl.c:2988
     *
     * Used for refraction calculations with swe_refrac_extended().
     *
     * @param float $lapse_rate Temperature lapse rate (dT/dh) in degrees K per meter
     *                          Default is Constants::SE_LAPSE_RATE = 0.0065 K/m
     *
     * Mirrors C API: swe_set_lapse_rate(lapse_rate)
     */
    function swe_set_lapse_rate(float $lapse_rate): void
    {
        RefractionFunctions::setLapseRate($lapse_rate);
    }
}

// Coordinate transforms: cotrans
if (!function_exists('swe_cotrans')) {
    /**
     * Rotate rectangular coordinates around X-axis by angle eps (deg).
     * C API: void swe_cotrans(double *xpo, double *xpn, double eps);
     */
    function swe_cotrans(array $xpo, array &$xpn, float $eps): int
    {
        $serr = null;
        return TransformFunctions::cotrans($xpo, $xpn, $eps, $serr);
    }
}
if (!function_exists('swe_cotrans_sp')) {
    /**
     * Rotate rectangular coordinates and velocities around X-axis by angle eps (deg).
     * C API: void swe_cotrans_sp(double *xpo, double *xpn, double eps);
     */
    function swe_cotrans_sp(array $xpo6, array &$xpn6, float $eps): int
    {
        $serr = null;
        return TransformFunctions::cotransSp($xpo6, $xpn6, $eps, $serr);
    }
}

// Utilities: planet name, version, close
if (!function_exists('swe_get_planet_name')) {
    function swe_get_planet_name(int $ipl): string
    {
        return match ($ipl) {
            Constants::SE_SUN => 'Sun',
            Constants::SE_MOON => 'Moon',
            Constants::SE_MERCURY => 'Mercury',
            Constants::SE_VENUS => 'Venus',
            Constants::SE_MARS => 'Mars',
            Constants::SE_JUPITER => 'Jupiter',
            Constants::SE_SATURN => 'Saturn',
            Constants::SE_URANUS => 'Uranus',
            Constants::SE_NEPTUNE => 'Neptune',
            Constants::SE_PLUTO => 'Pluto',
            default => 'Body ' . $ipl,
        };
    }
}
if (!function_exists('swe_version')) {
    function swe_version(): string
    {
        // Minimal semantic version for this PHP port
        return 'php-swisseph 0.2.0';
    }
}
if (!function_exists('swe_close')) {
    function swe_close(): void
    {
        // No-op in pure PHP port; in C closes ephemeris files and frees resources
    }
}

// Rise/Set/Transit wrappers (scaffold)
if (!function_exists('swe_rise_trans')) {
    /**
     * Swiss Ephemeris-like API: compute rise/set/transit times.
     * NOTE: Scaffold only. Currently returns SE_ERR with serr explaining unimplemented state.
     *
     * @param float $jd_ut  Julian day in UT
     * @param int $ipl      Body id (e.g., Constants::SE_SUN)
     * @param string|null $starname  Star name if ipl == SE_FIXSTAR (not supported yet)
     * @param int $epheflag Ephemeris flags (JPL/SWIEPH/MOSEPH etc.)
     * @param int $rsmi     Bitmask for RISE/SET/MTRANSIT/ITRANSIT (to be defined)
     * @param array $geopos [lon_deg, lat_deg, alt_m]
     * @param float $atpress Atmospheric pressure in mbar (1013.25 default)
     * @param float $attemp  Temperature in Celsius (15.0 default)
    * @param float|null $horhgt  Apparent horizon height in degrees (e.g., -0.833 for Sun); null -> default per-body
     * @param float|null $tret (by-ref) returns event time JD(UT)
     * @param string|null $serr (by-ref) error message
     * @return int return code >=0 on success; SE_ERR on failure
     */
    function swe_rise_trans(
        float $jd_ut,
        int $ipl,
        ?string $starname,
        int $epheflag,
        int $rsmi,
        array $geopos,
        float $atpress,
        float $attemp,
        ?float $horhgt,
        ?float &$tret = null,
        ?string &$serr = null
    ): int {
        return \Swisseph\Swe\Functions\RiseSetFunctions::riseTrans(
            $jd_ut,
            $ipl,
            $starname,
            $epheflag,
            $rsmi,
            $geopos,
            $atpress,
            $attemp,
            $horhgt,
            $tret,
            $serr
        );
    }
}

if (!function_exists('swe_rise_trans_true_hor')) {
    /**
     * Variant using true (non-refracted) horizon. Scaffold-only.
     */
    function swe_rise_trans_true_hor(
        float $jd_ut,
        int $ipl,
        ?string $starname,
        int $epheflag,
        int $rsmi,
        array $geopos,
        float $atpress,
        float $attemp,
        ?float $horhgt,
        ?float &$tret = null,
        ?string &$serr = null
    ): int {
        return \Swisseph\Swe\Functions\RiseSetFunctions::riseTransTrueHor(
            $jd_ut,
            $ipl,
            $starname,
            $epheflag,
            $rsmi,
            $geopos,
            $atpress,
            $attemp,
            $horhgt,
            $tret,
            $serr
        );
    }
}

// Houses wrappers (minimal Equal houses 'E')
if (!function_exists('swe_houses_ex2')) {
    /**
     * Minimal implementation for Equal houses (hsys='E').
     * @param float $jd_ut
     * @param int $iflag currently ignored
     * @param float $geolat latitude in degrees
     * @param float $geolon longitude in degrees (east positive)
     * @param string $hsys one-char house system code
     * @param array $cusp output cusps [1..12] in degrees (index 0 unused)
     * @param array $ascmc output ascmc[10], where [0]=Asc, [1]=MC in degrees
     * @param array|null $cusp_speed optional, ignored for now
     * @param array|null $ascmc_speed optional, ignored for now
     * @param string|null $serr
     */
    function swe_houses_ex2(
        float $jd_ut,
        int $iflag,
        float $geolat,
        float $geolon,
        string $hsys,
        array &$cusp,
        array &$ascmc,
        ?array &$cusp_speed = null,
        ?array &$ascmc_speed = null,
        ?string &$serr = null
    ): int {
        return \Swisseph\Swe\Functions\HousesFunctions::housesEx2(
            $jd_ut,
            $iflag,
            $geolat,
            $geolon,
            $hsys,
            $cusp,
            $ascmc,
            $cusp_speed,
            $ascmc_speed,
            $serr
        );
    }
}

if (!function_exists('swe_houses_ex')) {
    /**
     * Calculate house cusps with ephemeris flags (without speeds).
     * Port of swe_houses_ex() from swehouse.c:178
     *
     * This is a middle-ground function between swe_houses() and swe_houses_ex2():
     * - Has iflag parameter (unlike swe_houses)
     * - Omits speed calculations (unlike swe_houses_ex2)
     *
     * @param float $jd_ut Julian day in UT
     * @param int $iflag Ephemeris flags (e.g., SEFLG_SIDEREAL, SEFLG_NONUT, etc.)
     * @param float $geolat Geographic latitude in degrees
     * @param float $geolon Geographic longitude in degrees
     * @param string $hsys House system code (single letter: P, K, O, R, C, E, W, etc.)
     * @param array &$cusp Output array for house cusps [0..12] or [0..36] for Gauquelin
     * @param array &$ascmc Output array for additional points [0..9]
     * @return int SE_OK (0) or SE_ERR (-1)
     *
     * Mirrors C API: swe_houses_ex(tjd_ut, iflag, geolat, geolon, hsys, cusps, ascmc)
     */
    function swe_houses_ex(
        float $jd_ut,
        int $iflag,
        float $geolat,
        float $geolon,
        string $hsys,
        array &$cusp,
        array &$ascmc
    ): int {
        return \Swisseph\Swe\Functions\HousesFunctions::housesEx(
            $jd_ut,
            $iflag,
            $geolat,
            $geolon,
            $hsys,
            $cusp,
            $ascmc
        );
    }
}

if (!function_exists('swe_houses')) {
    /**
     * Thin wrapper matching classic API (no iflag/speeds): delegates to swe_houses_ex2.
     */
    function swe_houses(float $jd_ut, float $geolat, float $geolon, string $hsys, array &$cusp, array &$ascmc): int
    {
        return \Swisseph\Swe\Functions\HousesFunctions::houses($jd_ut, $geolat, $geolon, $hsys, $cusp, $ascmc);
    }
}

// House position and name wrappers (minimal for 'E')
if (!function_exists('swe_house_pos')) {
    /**
     * Compute house position in Equal system. Input: armc, geolat, eps in degrees; xpin: [RA, Dec, Dist] not used here,
     * we accept ecliptic ecliptic longitude in degrees via xpin[0] for minimal compatibility.
     * Real Swiss Ephemeris expects more; we implement a simplified variant.
     */
    function swe_house_pos(
        float $armc_deg,
        float $geolat_deg,
        float $eps_deg,
        string $hsys,
        array $xpin,
        ?string &$serr = null
    ): float {
        return \Swisseph\Swe\Functions\HousesFunctions::housePos($armc_deg, $geolat_deg, $eps_deg, $hsys, $xpin, $serr);
    }
}
if (!function_exists('swe_house_name')) {
    function swe_house_name(string $hsys): string
    {
        return \Swisseph\Swe\Functions\HousesFunctions::houseName($hsys);
    }
}

if (!function_exists('swe_get_ayanamsa')) {
    function swe_get_ayanamsa(float $jd_tt): float
    {
        return \Swisseph\Sidereal::ayanamshaDegFromJdTT($jd_tt);
    }
}
if (!function_exists('swe_get_ayanamsa_ut')) {
    function swe_get_ayanamsa_ut(float $jd_ut): float
    {
        $dt = \Swisseph\DeltaT::deltaTSecondsFromJd($jd_ut) / 86400.0;
        return \Swisseph\Sidereal::ayanamshaDegFromJdTT($jd_ut + $dt);
    }
}

if (!function_exists('swe_nod_aps')) {
    /**
     * Calculate nodes and apsides of planets
     *
     * @param float $tjd_et Julian day in Ephemeris Time
     * @param int $ipl Planet number (SE_SUN..SE_NEPTUNE)
     * @param int $iflag Calculation flags (SEFLG_SPEED, etc.)
     * @param int $method Method bits (SE_NODBIT_MEAN, SE_NODBIT_OSCU, SE_NODBIT_FOPOINT)
     * @param array|null &$xnasc Ascending node [lon, lat, dist, dlon, dlat, ddist] or null
     * @param array|null &$xndsc Descending node or null
     * @param array|null &$xperi Perihelion or null
     * @param array|null &$xaphe Aphelion (or focal point) or null
     * @param string|null &$serr Error message or null
     * @return int OK (>=0) on success, ERR (<0) on error
     */
    function swe_nod_aps(
        float $tjd_et,
        int $ipl,
        int $iflag,
        int $method,
        ?array &$xnasc,
        ?array &$xndsc,
        ?array &$xperi,
        ?array &$xaphe,
        ?string &$serr = null
    ): int {
        return NodesApsidesFunctions::nodAps(
            $tjd_et,
            $ipl,
            $iflag,
            $method,
            $xnasc,
            $xndsc,
            $xperi,
            $xaphe,
            $serr
        );
    }
}

if (!function_exists('swe_nod_aps_ut')) {
    /**
     * Calculate nodes and apsides of planets (UT version)
     *
     * @param float $tjd_ut Julian day in Universal Time
     * @param int $ipl Planet number
     * @param int $iflag Calculation flags
     * @param int $method Method bits
     * @param array|null &$xnasc Ascending node or null
     * @param array|null &$xndsc Descending node or null
     * @param array|null &$xperi Perihelion or null
     * @param array|null &$xaphe Aphelion or null
     * @param string|null &$serr Error message or null
     * @return int OK (>=0) on success, ERR (<0) on error
     */
    function swe_nod_aps_ut(
        float $tjd_ut,
        int $ipl,
        int $iflag,
        int $method,
        ?array &$xnasc,
        ?array &$xndsc,
        ?array &$xperi,
        ?array &$xaphe,
        ?string &$serr = null
    ): int {
        return NodesApsidesFunctions::nodApsUt(
            $tjd_ut,
            $ipl,
            $iflag,
            $method,
            $xnasc,
            $xndsc,
            $xperi,
            $xaphe,
            $serr
        );
    }
}

if (!function_exists('swe_degnorm')) {
    /**
     * Normalize angle in degrees to [0, 360).
     * @param float $x Angle in degrees
     * @return float Normalized angle
     */
    function swe_degnorm(float $x): float
    {
        return Math::normAngleDeg($x);
    }
}

if (!function_exists('swe_radnorm')) {
    /**
     * Normalize angle in radians to [0, 2π).
     * @param float $x Angle in radians
     * @return float Normalized angle
     */
    function swe_radnorm(float $x): float
    {
        return Math::normAngleRad($x);
    }
}

if (!function_exists('swe_deg_midp')) {
    /**
     * Midpoint between two angles in degrees (shortest arc).
     * @param float $x1 First angle
     * @param float $x0 Second angle
     * @return float Midpoint angle
     */
    function swe_deg_midp(float $x1, float $x0): float
    {
        return Math::degMidpoint($x1, $x0);
    }
}

if (!function_exists('swe_rad_midp')) {
    /**
     * Midpoint between two angles in radians (shortest arc).
     * @param float $x1 First angle
     * @param float $x0 Second angle
     * @return float Midpoint angle
     */
    function swe_rad_midp(float $x1, float $x0): float
    {
        return Math::radMidpoint($x1, $x0);
    }
}

if (!function_exists('swe_split_deg')) {
    /**
     * Split decimal degrees into sign, degrees, minutes, seconds, fraction.
     * @param float $ddeg Decimal degrees
     * @param int $roundflag Rounding flag (SE_SPLIT_DEG_*)
     * @param int &$ideg Degrees (output)
     * @param int &$imin Minutes (output)
     * @param int &$isec Seconds (output)
     * @param float &$dsecfr Fractional seconds (output)
     * @param int &$isgn Sign: +1 or -1 (output)
     */
    function swe_split_deg(
        float $ddeg,
        int $roundflag,
        int &$ideg,
        int &$imin,
        int &$isec,
        float &$dsecfr,
        int &$isgn
    ): void {
        Math::splitDeg($ddeg, $roundflag, $ideg, $imin, $isec, $dsecfr, $isgn);
    }
}

if (!function_exists('swe_date_conversion')) {
    /**
     * Convert calendar date to Julian Day with validation.
     * @param int $y Year
     * @param int $m Month (1-12)
     * @param int $d Day (1-31)
     * @param float $utime Universal time in hours (decimal)
     * @param string $c Calendar type: 'g' (Gregorian) or 'j' (Julian)
     * @param float &$tjd Output Julian Day
     * @return int SE_OK on success, SE_ERR on error
     */
    function swe_date_conversion(
        int $y,
        int $m,
        int $d,
        float $utime,
        string $c,
        float &$tjd
    ): int {
        return Julian::dateConversion($y, $m, $d, $utime, $c, $tjd);
    }
}

if (!function_exists('swe_day_of_week')) {
    /**
     * Get day of week for a Julian Day.
     * Monday = 0, Tuesday = 1, ..., Sunday = 6
     * @param float $jd Julian Day
     * @return int Day of week (0-6)
     */
    function swe_day_of_week(float $jd): int
    {
        return Julian::dayOfWeek($jd);
    }
}

if (!function_exists('swe_lmt_to_lat')) {
    /**
     * Convert Local Mean Time to Local Apparent Time.
     * Port of swe_lmt_to_lat() from sweph.c:7469
     *
     * LAT = LMT + equation_of_time
     *
     * @param float $tjd_lmt Julian Day in Local Mean Time
     * @param float $geolon Geographic longitude (degrees, positive East)
     * @param float &$tjd_lat Output: Julian Day in Local Apparent Time
     * @param string|null &$serr Error message
     * @return int Constants::SE_OK on success, Constants::SE_ERR on error
     *
     * Mirrors C API: swe_lmt_to_lat(tjd_lmt, geolon, &tjd_lat, serr)
     */
    function swe_lmt_to_lat(
        float $tjd_lmt,
        float $geolon,
        ?float &$tjd_lat = null,
        ?string &$serr = null
    ): int {
        return TimeFunctions::lmtToLat($tjd_lmt, $geolon, $tjd_lat, $serr);
    }
}

if (!function_exists('swe_lat_to_lmt')) {
    /**
     * Convert Local Apparent Time to Local Mean Time.
     * Port of swe_lat_to_lmt() from sweph.c:7478
     *
     * LMT = LAT - equation_of_time (with iteration for precision)
     *
     * @param float $tjd_lat Julian Day in Local Apparent Time
     * @param float $geolon Geographic longitude (degrees, positive East)
     * @param float &$tjd_lmt Output: Julian Day in Local Mean Time
     * @param string|null &$serr Error message
     * @return int Constants::SE_OK on success, Constants::SE_ERR on error
     *
     * Mirrors C API: swe_lat_to_lmt(tjd_lat, geolon, &tjd_lmt, serr)
     */
    function swe_lat_to_lmt(
        float $tjd_lat,
        float $geolon,
        ?float &$tjd_lmt = null,
        ?string &$serr = null
    ): int {
        return TimeFunctions::latToLmt($tjd_lat, $geolon, $tjd_lmt, $serr);
    }
}

if (!function_exists('swe_pheno')) {
    /**
     * Calculate planetary phenomena (phase, magnitude, diameter).
     * @param float $tjd_et Julian Day in ET
     * @param int $ipl Planet number
     * @param int $iflag Calculation flags
     * @param array &$attr Output array [phase_angle, phase, elongation, diameter, magnitude, parallax]
     * @param string|null &$serr Error message
     * @return int SE_OK on success, error code on failure
     */
    function swe_pheno(
        float $tjd_et,
        int $ipl,
        int $iflag,
        ?array &$attr = null,
        ?string &$serr = null
    ): int {
        return PhenoFunctions::pheno($tjd_et, $ipl, $iflag, $attr, $serr);
    }
}

if (!function_exists('swe_pheno_ut')) {
    /**
     * Calculate planetary phenomena (UT version).
     * @param float $tjd_ut Julian Day in UT
     * @param int $ipl Planet number
     * @param int $iflag Calculation flags
     * @param array &$attr Output array [phase_angle, phase, elongation, diameter, magnitude, parallax]
     * @param string|null &$serr Error message
     * @return int SE_OK on success, error code on failure
     */
    function swe_pheno_ut(
        float $tjd_ut,
        int $ipl,
        int $iflag,
        ?array &$attr = null,
        ?string &$serr = null
    ): int {
        return PhenoFunctions::phenoUt($tjd_ut, $ipl, $iflag, $attr, $serr);
    }
}

if (!function_exists('swe_fixstar')) {
    /**
     * Calculate fixed star positions (ET version).
     * @param string &$star Star name (traditional name, Bayer designation, or sequential number). Modified to full name on success.
     * @param float $tjd Julian day number (ET)
     * @param int $iflag Calculation flags (SEFLG_*)
     * @param array &$xx Output array [longitude, latitude, distance, speed_long, speed_lat, speed_dist]
     * @param string|null &$serr Error message
     * @return int Flag value or ERR
     */
    function swe_fixstar(
        string &$star,
        float $tjd,
        int $iflag,
        ?array &$xx = null,
        ?string &$serr = null
    ): int {
        return FixstarFunctions::fixstar($star, $tjd, $iflag, $xx, $serr);
    }
}

if (!function_exists('swe_fixstar_ut')) {
    /**
     * Calculate fixed star positions (UT version).
     * @param string &$star Star name. Modified to full name on success.
     * @param float $tjd_ut Julian day number (UT)
     * @param int $iflag Calculation flags (SEFLG_*)
     * @param array &$xx Output array [longitude, latitude, distance, speed_long, speed_lat, speed_dist]
     * @param string|null &$serr Error message
     * @return int Flag value or ERR
     */
    function swe_fixstar_ut(
        string &$star,
        float $tjd_ut,
        int $iflag,
        ?array &$xx = null,
        ?string &$serr = null
    ): int {
        return FixstarFunctions::fixstarUt($star, $tjd_ut, $iflag, $xx, $serr);
    }
}

if (!function_exists('swe_fixstar_mag')) {
    /**
     * Get visual magnitude of a fixed star.
     * @param string &$star Star name. Modified to full name on success.
     * @param float &$mag Output magnitude
     * @param string|null &$serr Error message
     * @return int OK or ERR
     */
    function swe_fixstar_mag(
        string &$star,
        float &$mag,
        ?string &$serr = null
    ): int {
        return FixstarFunctions::fixstarMag($star, $mag, $serr);
    }
}

if (!function_exists('swe_gauquelin_sector')) {
    /**
     * Calculate Gauquelin sector position for a planet or fixed star.
     *
     * @param float $t_ut Julian day number (UT)
     * @param int $ipl Planet number (SE_SUN, etc.) - ignored if starname is set
     * @param string|null $starname Star name for fixed stars, or null/empty for planets
     * @param int $iflag Calculation flags (SEFLG_*)
     * @param int $imeth Method (0-5):
     *                    0 = geometric with latitude
     *                    1 = geometric without latitude
     *                    2 = from rise/set, disc center, no refraction
     *                    3 = from rise/set, disc center, with refraction
     *                    4 = from rise/set, no refraction
     *                    5 = from rise/set, with refraction
     * @param array $geopos Geographic position [longitude, latitude, height]
     * @param float $atpress Atmospheric pressure (mbar), 0 = default 1013.25
     * @param float $attemp Atmospheric temperature (°C)
     * @param float &$dgsect Output: Gauquelin sector position (1.0 - 36.999...)
     * @param string|null &$serr Error message
     * @return int OK or ERR
     */
    function swe_gauquelin_sector(
        float $t_ut,
        int $ipl,
        ?string $starname,
        int $iflag,
        int $imeth,
        array $geopos,
        float $atpress,
        float $attemp,
        float &$dgsect,
        ?string &$serr = null
    ): int {
        return \Swisseph\Swe\Functions\GauquelinFunctions::gauquelinSector(
            $t_ut,
            $ipl,
            $starname,
            $iflag,
            $imeth,
            $geopos,
            $atpress,
            $attemp,
            $dgsect,
            $serr
        );
    }
}

// ============================================================================
// Centisec (centiseconds) utility functions
// ============================================================================

if (!function_exists('swe_csnorm')) {
    /**
     * Normalize centisec into interval [0..360°[
     * C API: centisec swe_csnorm(centisec p);
     *
     * @param int $p Angle in centisec (1° = 360000 centisec)
     * @return int Normalized angle in [0, 129600000[
     */
    function swe_csnorm(int $p): int
    {
        return \Swisseph\Swe\Functions\CentisecFunctions::csnorm($p);
    }
}

if (!function_exists('swe_difcsn')) {
    /**
     * Distance in centisec p1 - p2, normalized to [0..360[
     * C API: centisec swe_difcsn(centisec p1, centisec p2);
     *
     * @param int $p1 First angle in centisec
     * @param int $p2 Second angle in centisec
     * @return int Normalized difference
     */
    function swe_difcsn(int $p1, int $p2): int
    {
        return \Swisseph\Swe\Functions\CentisecFunctions::difcsn($p1, $p2);
    }
}

if (!function_exists('swe_difdegn')) {
    /**
     * Distance in degrees p1 - p2, normalized to [0..360[
     * C API: double swe_difdegn(double p1, double p2);
     *
     * @param float $p1 First angle in degrees
     * @param float $p2 Second angle in degrees
     * @return float Normalized difference
     */
    function swe_difdegn(float $p1, float $p2): float
    {
        return \Swisseph\Swe\Functions\CentisecFunctions::difdegn($p1, $p2);
    }
}

if (!function_exists('swe_difcs2n')) {
    /**
     * Distance in centisec p1 - p2, normalized to [-180..180[
     * C API: centisec swe_difcs2n(centisec p1, centisec p2);
     *
     * @param int $p1 First angle in centisec
     * @param int $p2 Second angle in centisec
     * @return int Normalized difference in [-64800000, 64800000[
     */
    function swe_difcs2n(int $p1, int $p2): int
    {
        return \Swisseph\Swe\Functions\CentisecFunctions::difcs2n($p1, $p2);
    }
}

if (!function_exists('swe_difdeg2n')) {
    /**
     * Distance in degrees p1 - p2, normalized to [-180..180[
     * C API: double swe_difdeg2n(double p1, double p2);
     *
     * @param float $p1 First angle in degrees
     * @param float $p2 Second angle in degrees
     * @return float Normalized difference
     */
    function swe_difdeg2n(float $p1, float $p2): float
    {
        return \Swisseph\Swe\Functions\CentisecFunctions::difdeg2n($p1, $p2);
    }
}

if (!function_exists('swe_difrad2n')) {
    /**
     * Distance in radians p1 - p2, normalized to [-π..π[
     * C API: double swe_difrad2n(double p1, double p2);
     *
     * @param float $p1 First angle in radians
     * @param float $p2 Second angle in radians
     * @return float Normalized difference
     */
    function swe_difrad2n(float $p1, float $p2): float
    {
        return \Swisseph\Swe\Functions\CentisecFunctions::difrad2n($p1, $p2);
    }
}

if (!function_exists('swe_csroundsec')) {
    /**
     * Round centisec to seconds, but at 29°59'59" always round down
     * C API: centisec swe_csroundsec(centisec x);
     *
     * Special behavior: Prevents rounding up to next zodiac sign
     *
     * @param int $x Angle in centisec
     * @return int Rounded to full seconds (nearest 100 centisec)
     */
    function swe_csroundsec(int $x): int
    {
        return \Swisseph\Swe\Functions\CentisecFunctions::csroundsec($x);
    }
}

if (!function_exists('swe_cs2timestr')) {
    /**
     * Convert centisec time to HH:MM:SS string
     * C API: char *swe_cs2timestr(CSEC t, int sep, AS_BOOL suppressZero, char *a);
     *
     * @param int $t Time in centisec (24h = 8640000 centisec)
     * @param int $sep Separator character code (e.g. ord(':') = 58)
     * @param bool $suppressZero If true, omit ":00" seconds
     * @return string Formatted time "HH:MM:SS" or "HH:MM"
     */
    function swe_cs2timestr(int $t, int $sep = 58, bool $suppressZero = false): string
    {
        return \Swisseph\Swe\Functions\CentisecFunctions::cs2timestr($t, $sep, $suppressZero);
    }
}

if (!function_exists('swe_cs2lonlatstr')) {
    /**
     * Convert centisec angle to DDDEmm'ss" format (longitude/latitude)
     * C API: char *swe_cs2lonlatstr(CSEC t, char pchar, char mchar, char *s);
     *
     * @param int $t Angle in centisec
     * @param string $pchar Positive direction char (e.g. 'E', 'N')
     * @param string $mchar Negative direction char (e.g. 'W', 'S')
     * @return string Formatted angle "DDDEmm'ss" or "DDDWmm'ss"
     */
    function swe_cs2lonlatstr(int $t, string $pchar, string $mchar): string
    {
        return \Swisseph\Swe\Functions\CentisecFunctions::cs2lonlatstr($t, $pchar, $mchar);
    }
}

if (!function_exists('swe_cs2degstr')) {
    /**
     * Convert centisec angle to DD°mm'ss format (zodiac degree)
     * C API: char *swe_cs2degstr(CSEC t, char *a);
     *
     * Truncates to [0..30[ degrees (one zodiac sign)
     *
     * @param int $t Angle in centisec
     * @return string Formatted angle "DD°mm'ss\""
     */
    function swe_cs2degstr(int $t): string
    {
        return \Swisseph\Swe\Functions\CentisecFunctions::cs2degstr($t);
    }
}

if (!function_exists('swe_d2l')) {
    /**
     * Convert double to int32 with rounding (no overflow check)
     * C API: int32 swe_d2l(double x);
     *
     * @param float $x Value to convert
     * @return int Rounded integer value
     */
    function swe_d2l(float $x): int
    {
        return \Swisseph\Swe\Functions\MiscUtilityFunctions::d2l($x);
    }
}

if (!function_exists('swe_day_of_week')) {
    /**
     * Get day of week for Julian Day number
     * C API: int swe_day_of_week(double jd);
     *
     * Returns: 0 = Monday, 1 = Tuesday, ..., 6 = Sunday
     *
     * @param float $jd Julian Day number
     * @return int Day of week (0-6, Monday = 0)
     */
    function swe_day_of_week(float $jd): int
    {
        return \Swisseph\Swe\Functions\MiscUtilityFunctions::dayOfWeek($jd);
    }
}

if (!function_exists('swe_date_conversion')) {
    /**
     * Convert calendar date to Julian Day and validate date
     * C API: int swe_date_conversion(int y, int m, int d, double utime, char c, double *tjd);
     *
     * This function converts date+time input {y,m,d,uttime} into Julian day number.
     * It checks that the input is a legal combination of dates.
     * For illegal dates like 32 January 1993, it returns ERR but still converts
     * the date correctly (like 1 Feb 1993).
     *
     * Be aware: we always use astronomical year numbering for years before Christ:
     * - Year 0 (astronomical) = 1 BC historical
     * - Year -1 (astronomical) = 2 BC historical
     *
     * @param int $year Year (astronomical numbering)
     * @param int $month Month (1-12)
     * @param int $day Day (1-31)
     * @param float $uttime Universal Time in hours (decimal)
     * @param string $calendar 'g' = Gregorian, 'j' = Julian
     * @param float &$tjd Output: Julian Day number
     * @return int OK (0) if date is valid, ERR (-1) if invalid
     */
    function swe_date_conversion(
        int $year,
        int $month,
        int $day,
        float $uttime,
        string $calendar,
        float &$tjd
    ): int {
        return \Swisseph\Swe\Functions\MiscUtilityFunctions::dateConversion(
            $year,
            $month,
            $day,
            $uttime,
            $calendar,
            $tjd
        );
    }
}

if (!function_exists('swe_get_tid_acc')) {
    /**
     * Get tidal acceleration (used in Delta-T calculation)
     * C API: double swe_get_tid_acc(void);
     *
     * Returns the tidal acceleration value in arcsec/cy^2.
     * Default value is -25.80 (DE431).
     *
     * @return float Tidal acceleration in arcsec/cy^2
     */
    function swe_get_tid_acc(): float
    {
        return \Swisseph\Swe\Functions\MiscUtilityFunctions::getTidAcc();
    }
}

if (!function_exists('swe_set_delta_t_userdef')) {
    /**
     * Set user-defined Delta-T value
     * C API: void swe_set_delta_t_userdef(double dt);
     *
     * Overrides automatic Delta-T calculation with a user-defined value.
     * Pass SE_DELTAT_AUTOMATIC (-1e-10) to restore automatic calculation.
     *
     * Delta-T is the difference between Terrestrial Time (TT) and Universal Time (UT):
     * Delta-T = TT - UT
     *
     * @param float $dt Delta-T value in days, or SE_DELTAT_AUTOMATIC for automatic
     * @return void
     */
    function swe_set_delta_t_userdef(float $dt): void
    {
        \Swisseph\Swe\Functions\MiscUtilityFunctions::setDeltaTUserdef($dt);
    }
}

// ============================================================================
// Fixed Stars API - Legacy swe_fixstar* functions
// ============================================================================

if (!function_exists('swe_fixstar')) {
    /**
     * Calculate fixed star position for Ephemeris Time (legacy API).
     *
     * Port of C function: int32 swe_fixstar(char *star, double tjd, int32 iflag, double *xx, char *serr)
     *
     * This is the OLD API that reads star file line-by-line (slower).
     * For new code, use swe_fixstar2() instead (10-100x faster).
     *
     * @param string &$star Input: star name or number; Output: formatted "tradname,nomenclature"
     * @param float $tjd Julian Day Ephemeris Time
     * @param int $iflag Calculation flags (SEFLG_*)
     * @param array &$xx Output: 6 doubles [lon/ra, lat/dec, dist, dlon, dlat, ddist]
     * @param string|null &$serr Error message
     * @return int iflag on success, SE_ERR on error
     */
    function swe_fixstar(
        string &$star,
        float $tjd,
        int $iflag,
        array &$xx,
        ?string &$serr = null
    ): int {
        return \Swisseph\Swe\Functions\LegacyStarFunctions::fixstar($star, $tjd, $iflag, $xx, $serr);
    }
}

if (!function_exists('swe_fixstar_ut')) {
    /**
     * Calculate fixed star position for Universal Time (legacy API).
     *
     * Converts UT to ET using Delta T, then calls swe_fixstar().
     * Port of C function: int32 swe_fixstar_ut(char *star, double tjd_ut, int32 iflag, double *xx, char *serr)
     *
     * This is the OLD API that reads star file line-by-line (slower).
     * For new code, use swe_fixstar2_ut() instead (10-100x faster).
     *
     * @param string &$star Input: star name or number; Output: formatted "tradname,nomenclature"
     * @param float $tjdUt Julian Day Universal Time
     * @param int $iflag Calculation flags (SEFLG_*)
     * @param array &$xx Output: 6 doubles [lon/ra, lat/dec, dist, dlon, dlat, ddist]
     * @param string|null &$serr Error message
     * @return int iflag on success, SE_ERR on error
     */
    function swe_fixstar_ut(
        string &$star,
        float $tjdUt,
        int $iflag,
        array &$xx,
        ?string &$serr = null
    ): int {
        return \Swisseph\Swe\Functions\LegacyStarFunctions::fixstarUt($star, $tjdUt, $iflag, $xx, $serr);
    }
}

if (!function_exists('swe_fixstar_mag')) {
    /**
     * Get fixed star magnitude (legacy API).
     *
     * Port of C function: int32 swe_fixstar_mag(char *star, double *mag, char *serr)
     *
     * This is the OLD API that reads star file line-by-line (slower).
     * For new code, use swe_fixstar2_mag() instead (10-100x faster).
     *
     * @param string &$star Input: star name or number; Output: formatted "tradname,nomenclature"
     * @param float &$mag Output: star magnitude
     * @param string|null &$serr Error message
     * @return int SE_OK on success, SE_ERR on error
     */
    function swe_fixstar_mag(
        string &$star,
        float &$mag,
        ?string &$serr = null
    ): int {
        return \Swisseph\Swe\Functions\LegacyStarFunctions::fixstarMag($star, $mag, $serr);
    }
}

// ============================================================================
// Fixed Stars API - swe_fixstar2* functions (NEW, faster API)
// ============================================================================

if (!function_exists('swe_fixstar2')) {
    /**
     * Calculate fixed star position for Ephemeris Time.
     *
     * Port of C function: int32 swe_fixstar2(char *star, double tjd, int32 iflag, double *xx, char *serr)
     *
     * @param string &$star Input: star name or number; Output: formatted "tradname,nomenclature"
     * @param float $tjd Julian Day Ephemeris Time
     * @param int $iflag Calculation flags (SEFLG_*)
     * @param array &$xx Output: 6 doubles [lon/ra, lat/dec, dist, dlon, dlat, ddist]
     * @param string|null &$serr Error message
     * @return int iflag on success, SE_ERR on error
     */
    function swe_fixstar2(
        string &$star,
        float $tjd,
        int $iflag,
        array &$xx,
        ?string &$serr = null
    ): int {
        return \Swisseph\Swe\Functions\StarFunctions::fixstar2($star, $tjd, $iflag, $xx, $serr);
    }
}

if (!function_exists('swe_fixstar2_ut')) {
    /**
     * Calculate fixed star position for Universal Time.
     *
     * Converts UT to ET using Delta T, then calls swe_fixstar2().
     * Port of C function: int32 swe_fixstar2_ut(char *star, double tjd_ut, int32 iflag, double *xx, char *serr)
     *
     * @param string &$star Input: star name or number; Output: formatted "tradname,nomenclature"
     * @param float $tjdUt Julian Day Universal Time
     * @param int $iflag Calculation flags (SEFLG_*)
     * @param array &$xx Output: 6 doubles [lon/ra, lat/dec, dist, dlon, dlat, ddist]
     * @param string|null &$serr Error message
     * @return int iflag on success, SE_ERR on error
     */
    function swe_fixstar2_ut(
        string &$star,
        float $tjdUt,
        int $iflag,
        array &$xx,
        ?string &$serr = null
    ): int {
        return \Swisseph\Swe\Functions\StarFunctions::fixstar2Ut($star, $tjdUt, $iflag, $xx, $serr);
    }
}

if (!function_exists('swe_fixstar2_mag')) {
    /**
     * Get fixed star magnitude.
     *
     * Port of C function: int32 swe_fixstar2_mag(char *star, double *mag, char *serr)
     *
     * @param string &$star Input: star name or number; Output: formatted "tradname,nomenclature"
     * @param float &$mag Output: star magnitude
     * @param string|null &$serr Error message
     * @return int SE_OK on success, SE_ERR on error
     */
    function swe_fixstar2_mag(
        string &$star,
        float &$mag,
        ?string &$serr = null
    ): int {
        return \Swisseph\Swe\Functions\StarFunctions::fixstar2Mag($star, $mag, $serr);
    }
}

if (!function_exists('swe_sol_eclipse_how')) {
    /**
     * Calculate solar eclipse attributes for a given geographic location and time.
     *
     * Port of C function: int32 swe_sol_eclipse_how(double tjd_ut, int32 ifl, double *geopos, double *attr, char *serr)
     * From swecl.c lines 924-965
     *
     * @param float $tjd_ut Julian Day in Universal Time
     * @param int $ifl Calculation flags (ephemeris flags SEFLG_SWIEPH, SEFLG_JPLEPH, etc.)
     * @param array $geopos Geographic position [longitude, latitude, altitude_m]
     * @param array &$attr Output: array of 11 attributes [0-10]
     *                     attr[0]: fraction of solar diameter covered by moon (magnitude)
     *                     attr[1]: ratio of lunar diameter to solar one
     *                     attr[2]: fraction of solar disc covered by moon (obscuration)
     *                     attr[3]: diameter of core shadow in km
     *                     attr[4]: azimuth of sun at tjd
     *                     attr[5]: true altitude of sun above horizon at tjd
     *                     attr[6]: apparent altitude of sun above horizon at tjd
     *                     attr[7]: elongation of moon in degrees
     *                     attr[8]: magnitude acc. to NASA catalog
     *                     attr[9]: saros series number (if available, otherwise -99999999)
     *                     attr[10]: saros series member number (if available, otherwise -99999999)
     * @param string|null &$serr Error message
     * @return int Eclipse type flags (SE_ECL_TOTAL | SE_ECL_ANNULAR | SE_ECL_PARTIAL | SE_ECL_VISIBLE)
     *             Returns 0 if no eclipse or sun is below horizon
     *             Returns SE_ERR on error
     */
    function swe_sol_eclipse_how(
        float $tjd_ut,
        int $ifl,
        array $geopos,
        array &$attr,
        ?string &$serr = null
    ): int {
        return SolarEclipseFunctions::how($tjd_ut, $ifl, $geopos, $attr, $serr);
    }
}

if (!function_exists('swe_sol_eclipse_when_loc')) {
    /**
     * Find the next solar eclipse visible from a geographic location.
     *
     * Port of C function: int32 swe_sol_eclipse_when_loc(double tjd_start, int32 ifl, double *geopos, double *tret, double *attr, int32 backward, char *serr)
     * From swecl.c lines 2019-2039
     *
     * Searches for the next (or previous if backward=1) solar eclipse visible from the
     * specified geographic location. Returns eclipse type, all contact times, and
     * attributes at maximum.
     *
     * @param float $tjd_start Start time for search (Julian Day in UT)
     * @param int $ifl Ephemeris flags (SEFLG_SWIEPH, SEFLG_JPLEPH, etc.)
     * @param array $geopos Geographic position [longitude, latitude, altitude_m]
     *                      longitude: degrees, eastern positive
     *                      latitude: degrees, northern positive
     *                      altitude: meters above sea level
     *                      Must be between -10000 and +20000 meters
     * @param array &$tret Output: array of 7 eclipse times (all in UT)
     *                     tret[0]: time of maximum eclipse
     *                     tret[1]: time of first contact (eclipse begins)
     *                     tret[2]: time of second contact (totality begins) - 0 if partial
     *                     tret[3]: time of third contact (totality ends) - 0 if partial
     *                     tret[4]: time of fourth contact (eclipse ends)
     *                     tret[5]: time of sunrise during eclipse - 0 if not applicable
     *                     tret[6]: time of sunset during eclipse - 0 if not applicable
     * @param array &$attr Output: array of 11 eclipse attributes (same as swe_sol_eclipse_how)
     *                     attr[0]: fraction of solar diameter covered (magnitude)
     *                     attr[1]: ratio of lunar diameter to solar one
     *                     attr[2]: fraction of solar disc covered (obscuration)
     *                     attr[3]: diameter of core shadow in km
     *                     attr[4]: azimuth of sun at maximum
     *                     attr[5]: true altitude of sun above horizon at maximum
     *                     attr[6]: apparent altitude of sun above horizon at maximum
     *                     attr[7]: elongation of moon in degrees
     *                     attr[8]: magnitude acc. to NASA
     *                     attr[9]: saros series number (-99999999 if not found)
     *                     attr[10]: saros series member number (-99999999 if not found)
     * @param int $backward 0 for forward search (default), 1 for backward search
     * @param string|null &$serr Error message
     * @return int Eclipse type flags (combination of):
     *             SE_ECL_TOTAL (4): total eclipse
     *             SE_ECL_ANNULAR (8): annular eclipse
     *             SE_ECL_PARTIAL (16): partial eclipse
     *             SE_ECL_VISIBLE (128): at least one phase visible
     *             SE_ECL_MAX_VISIBLE (256): maximum phase visible
     *             SE_ECL_1ST_VISIBLE (512): first contact visible
     *             SE_ECL_2ND_VISIBLE (1024): second contact visible
     *             SE_ECL_3RD_VISIBLE (2048): third contact visible
     *             SE_ECL_4TH_VISIBLE (4096): fourth contact visible
     *             SE_ECL_CENTRAL (1): eclipse is central
     *             SE_ECL_NONCENTRAL (2): eclipse is non-central
     *             Returns 0 if no eclipse found
     *             Returns SE_ERR (-1) on error
     */
    function swe_sol_eclipse_when_loc(
        float $tjd_start,
        int $ifl,
        array $geopos,
        array &$tret,
        array &$attr,
        int $backward = 0,
        ?string &$serr = null
    ): int {
        return SolarEclipseFunctions::whenLoc($tjd_start, $ifl, $geopos, $tret, $attr, $backward, $serr);
    }
}

if (!function_exists('swe_sol_eclipse_when_glob')) {
    /**
     * Find next solar eclipse anywhere on Earth
     *
     * Port of C function: int32 swe_sol_eclipse_when_glob(double tjd_start, int32 ifl, int32 ifltype, double *tret, int32 backward, char *serr)
     *
     * Searches for the next solar eclipse of specified type(s) anywhere on Earth.
     *
     * @param float $tjd_start Starting time for search (Julian day, UT)
     * @param int $ifl Ephemeris flags (SEFLG_SWIEPH, SEFLG_JPLEPH, etc.)
     * @param int $ifltype Eclipse type to search for:
     *   - 0: any type
     *   - SE_ECL_TOTAL (4): total eclipse
     *   - SE_ECL_ANNULAR (8): annular eclipse
     *   - SE_ECL_PARTIAL (16): partial eclipse
     *   - SE_ECL_ANNULAR_TOTAL (32): hybrid (annular-total) eclipse
     *   - SE_ECL_CENTRAL (1): central eclipse
     *   - SE_ECL_NONCENTRAL (2): non-central eclipse
     *   - Combinations allowed (e.g., SE_ECL_TOTAL | SE_ECL_ANNULAR)
     * @param array &$tret Output: times of eclipse events [10]:
     *   - tret[0]: time of maximum eclipse (JD, UT)
     *   - tret[1]: time when eclipse takes place at local apparent noon (JD, UT), or 0 if no transit
     *   - tret[2]: time of eclipse begin (JD, UT)
     *   - tret[3]: time of eclipse end (JD, UT)
     *   - tret[4]: time of totality/annularity begin (JD, UT)
     *   - tret[5]: time of totality/annularity end (JD, UT)
     *   - tret[6]: time of center line begin (JD, UT)
     *   - tret[7]: time of center line end (JD, UT)
     *   - tret[8]: time when annular-total eclipse becomes total (not implemented)
     *   - tret[9]: time when annular-total eclipse becomes annular again (not implemented)
     * @param int $backward Search direction: 0 = forward, 1 = backward
     * @param string|null &$serr Error message output
     * @return int Eclipse type flags:
     *   - SE_ECL_TOTAL (4): total eclipse
     *   - SE_ECL_ANNULAR (8): annular eclipse
     *   - SE_ECL_PARTIAL (16): partial eclipse
     *   - SE_ECL_ANNULAR_TOTAL (32): hybrid eclipse
     *   - SE_ECL_CENTRAL (1): central eclipse
     *   - SE_ECL_NONCENTRAL (2): non-central eclipse
     *   - Returns SE_ERR (-1) on error
     */
    function swe_sol_eclipse_when_glob(
        float $tjd_start,
        int $ifl,
        int $ifltype,
        array &$tret,
        int $backward = 0,
        ?string &$serr = null
    ): int {
        return \Swisseph\Swe\Functions\SolarEclipseWhenGlobFunctions::sweEclipseWhenGlob(
            $tjd_start,
            $ifl,
            $ifltype,
            $tret,
            $backward,
            $serr
        );
    }
}

// Lunar eclipse wrappers
if (!function_exists('swe_lun_eclipse_how')) {
    /**
     * Compute attributes of a lunar eclipse
     *
     * Port of C function: int32 swe_lun_eclipse_how(double tjd_ut, int32 ifl, double *geopos, double *attr, char *serr)
     *
     * Calculates eclipse parameters at given time and location.
     *
     * @param float $tjd_ut Julian day in UT
     * @param int $ifl Ephemeris flags (SEFLG_SWIEPH, SEFLG_JPLEPH, etc.)
     * @param array|null $geopos Geographic position [lon, lat, alt] or null
     * @param array &$attr Output: eclipse attributes [0-10]
     *   - attr[0]: umbral magnitude
     *   - attr[1]: penumbral magnitude
     *   - attr[4]: azimuth of moon (if geopos provided)
     *   - attr[5]: true altitude of moon (if geopos provided)
     *   - attr[6]: apparent altitude of moon (if geopos provided)
     *   - attr[7]: angular distance from opposition
     *   - attr[8]: umbral magnitude (same as attr[0])
     *   - attr[9]: saros series number
     *   - attr[10]: saros series member number
     * @param string|null &$serr Output: error message
     * @return int Eclipse type:
     *   - SE_ECL_TOTAL (2): total lunar eclipse
     *   - SE_ECL_PARTIAL (4): partial lunar eclipse
     *   - SE_ECL_PENUMBRAL (8): penumbral lunar eclipse
     *   - 0: no eclipse at this time
     *   - SE_ERR (-1): error occurred
     */
    function swe_lun_eclipse_how(
        float $tjd_ut,
        int $ifl,
        ?array $geopos,
        array &$attr,
        ?string &$serr = null
    ): int {
        return \Swisseph\Swe\Functions\LunarEclipseFunctions::how($tjd_ut, $ifl, $geopos, $attr, $serr);
    }
}

if (!function_exists('swe_lun_eclipse_when')) {
    /**
     * Find the next lunar eclipse anywhere on earth
     *
     * Port from Swiss Ephemeris C library: swe_lun_eclipse_when()
     * Search for lunar eclipses globally using Meeus formulae.
     *
     * @param float $tjd_start Start time for search (JD UT)
     * @param int $ifl Ephemeris flag (SEFLG_SWIEPH, SEFLG_JPLEPH, SEFLG_MOSEPH)
     * @param int $ifltype Eclipse type to search for:
     *   - SE_ECL_TOTAL: total lunar eclipse
     *   - SE_ECL_PARTIAL: partial lunar eclipse
     *   - SE_ECL_PENUMBRAL: penumbral lunar eclipse
     *   - 0: any type of eclipse
     *   Can be combined with OR (|)
     * @param array &$tret Return array for eclipse times (declare as array with 10 elements):
     *   - [0]: time of maximum eclipse (JD UT)
     *   - [1]: (not used)
     *   - [2]: time of partial phase begin (JD UT)
     *   - [3]: time of partial phase end (JD UT)
     *   - [4]: time of totality begin (JD UT)
     *   - [5]: time of totality end (JD UT)
     *   - [6]: time of penumbral phase begin (JD UT)
     *   - [7]: time of penumbral phase end (JD UT)
     *   - [8-9]: (reserved)
     * @param int $backward Search backward in time if 1, forward if 0
     * @param string|null &$serr Error message return (optional)
     *
     * @return int Eclipse type flags:
     *   - SE_ECL_TOTAL: total lunar eclipse
     *   - SE_ECL_PARTIAL: partial lunar eclipse
     *   - SE_ECL_PENUMBRAL: penumbral lunar eclipse
     *   - 0: no eclipse found
     *   - SE_ERR (-1): error occurred
     */
    function swe_lun_eclipse_when(
        float $tjd_start,
        int $ifl,
        int $ifltype,
        array &$tret,
        int $backward,
        ?string &$serr = null
    ): int {
        return \Swisseph\Swe\Functions\LunarEclipseWhenFunctions::when($tjd_start, $ifl, $ifltype, $tret, $backward, $serr);
    }
}

if (!function_exists('swe_lun_eclipse_when_loc')) {
    /**
     * Find next lunar eclipse visible from given location
     *
     * Searches for the next lunar eclipse that is visible from a specific geographic location.
     * Returns visibility flags and adjusts contact times based on moon rise/set during the eclipse.
     *
     * @param float $tjd_start Start time for search (JD UT)
     * @param int $ifl Ephemeris flags (SEFLG_SWIEPH, SEFLG_JPLEPH, etc.)
     * @param array $geopos Geographic position [longitude_deg, latitude_deg, altitude_meters]
     *                      - longitude: East positive, West negative
     *                      - latitude: North positive, South negative
     *                      - altitude: meters above sea level (must be between -12000 and 50000 m)
     * @param array &$tret Return array for eclipse times (10 elements, JD UT):
     *   [0] = time of maximum eclipse (or moon rise/set if maximum not visible)
     *   [1] = (unused)
     *   [2] = time of partial phase begin (or 0 if moon rises after)
     *   [3] = time of partial phase end (or 0 if moon sets before)
     *   [4] = time of totality begin (or 0 if moon rises after)
     *   [5] = time of totality end (or 0 if moon sets before)
     *   [6] = time of penumbral phase begin (or 0 if moon rises after)
     *   [7] = time of penumbral phase end (or 0 if moon sets before)
     *   [8] = time of moon rise during eclipse (or 0 if moon already up)
     *   [9] = time of moon set during eclipse (or 0 if moon stays up)
     * @param array &$attr Return array for eclipse attributes (20 elements):
     *   [0] = umbral magnitude at maximum (attr[0] > 1.0 => total, 0 < attr[0] < 1.0 => partial)
     *   [1] = penumbral magnitude
     *   [2] = (unused)
     *   [3] = azimuth of Moon at maximum (degrees)
     *   [4] = true altitude of Moon at maximum (degrees, with refraction)
     *   [5] = apparent altitude of Moon at maximum (degrees, with refraction)
     *   [6] = apparent altitude at calculation time (used internally for visibility check)
     *   [7] = distance of moon from opposition in degrees
     *   [8] = eclipse magnitude (same as attr[0] for partial, = attr[1] for penumbral only)
     *   [9] = saros series number
     *   [10] = saros series member number
     * @param int $backward 1 = search backward in time, 0 = search forward
     * @param string|null &$serr Error message (if any)
     * @return int Eclipse type and visibility flags (bitwise OR):
     *   Eclipse types:
     *     SE_ECL_TOTAL (4) = total lunar eclipse
     *     SE_ECL_PARTIAL (16) = partial lunar eclipse
     *     SE_ECL_PENUMBRAL (64) = penumbral lunar eclipse only
     *   Visibility flags:
     *     SE_ECL_VISIBLE = eclipse visible from location
     *     SE_ECL_MAX_VISIBLE = maximum visible
     *     SE_ECL_PARTBEG_VISIBLE = partial phase begin visible
     *     SE_ECL_PARTEND_VISIBLE = partial phase end visible
     *     SE_ECL_TOTBEG_VISIBLE = totality begin visible
     *     SE_ECL_TOTEND_VISIBLE = totality end visible
     *     SE_ECL_PENUMBBEG_VISIBLE = penumbral phase begin visible
     *     SE_ECL_PENUMBEND_VISIBLE = penumbral phase end visible
     *   Returns SE_ERR (-1) on error
     */
    function swe_lun_eclipse_when_loc(
        float $tjd_start,
        int $ifl,
        array $geopos,
        array &$tret,
        array &$attr,
        int $backward,
        ?string &$serr = null
    ): int {
        return \Swisseph\Swe\Functions\LunarEclipseWhenLocFunctions::when($tjd_start, $ifl, $geopos, $tret, $attr, $backward, $serr);
    }
}

if (!function_exists('swe_lun_occult_when_glob')) {
    /**
     * Find next global lunar occultation of planet or star
     *
     * Searches for the next (or previous) occultation of a planet or fixed star
     * by the Moon, visible from anywhere on Earth.
     *
     * @param float $tjd_start Start time for search (JD UT)
     * @param int $ipl Planet number (SE_SUN, SE_MARS, SE_JUPITER, etc.)
     *                  For fixed stars, pass any planet number (ignored)
     * @param string|null $starname Fixed star name for swe_fixstar() (null for planets)
     * @param int $ifl Ephemeris flags (SEFLG_SWIEPH, SEFLG_JPLEPH, etc.)
     * @param int $ifltype Type of occultation to search for (0 = any):
     *   SE_ECL_TOTAL - Total occultations only
     *   SE_ECL_ANNULAR - Annular occultations only (Sun only)
     *   SE_ECL_PARTIAL - Partial occultations only
     *   SE_ECL_CENTRAL - Central occultations only
     *   SE_ECL_NONCENTRAL - Non-central occultations only
     * @param array &$tret Return array for occultation times (10 elements, JD UT):
     *   [0] = time of maximum occultation
     *   [1] = time of maximum at local apparent noon (0 if no transit)
     *   [2] = begin of occultation
     *   [3] = end of occultation
     *   [4] = begin of totality (0 if not applicable)
     *   [5] = end of totality (0 if not applicable)
     *   [6] = begin of center line (0 if not applicable)
     *   [7] = end of center line (0 if not applicable)
     * @param int $backward 1 = search backward in time, 0 = search forward
     * @param string|null &$serr Error message (if any)
     * @return int Occultation type flags (SE_ECL_TOTAL, SE_ECL_ANNULAR, etc.) or 0 if none found
     */
    function swe_lun_occult_when_glob(
        float $tjd_start,
        int $ipl,
        ?string $starname,
        int $ifl,
        int $ifltype,
        array &$tret,
        int $backward,
        ?string &$serr = null
    ): int {
        return \Swisseph\Swe\Functions\LunarOccultationWhenGlobFunctions::whenGlob(
            $tjd_start,
            $ipl,
            $starname,
            $ifl,
            $ifltype,
            $tret,
            (bool)$backward,
            $serr
        );
    }
}

if (!function_exists('swe_lun_occult_where')) {
    /**
     * Calculate geographic position of maximum lunar occultation
     *
     * Finds the geographic location where a lunar occultation of a planet or star
     * reaches its maximum. Returns the longitude, latitude, and attributes of the
     * occultation at that location.
     *
     * Port from swecl.c:606-630
     *
     * @param float $tjd_ut Julian Day Number (UT)
     * @param int $ipl Planet number (SE_SUN, SE_MOON, SE_MERCURY, etc.)
     * @param string|null $starname Fixed star name (e.g., "Aldebaran"), or null for planet
     * @param int $ifl Ephemeris flags (SEFLG_SWIEPH, SEFLG_JPLEPH, etc.)
     * @param array &$geopos Geographic position [longitude, latitude, altitude] (output)
     * @param array &$attr Eclipse attributes array (output), declare as attr[20]:
     *   attr[0] = fraction of object diameter covered by moon
     *   attr[1] = ratio of lunar diameter to object diameter
     *   attr[2] = fraction of object disc covered by moon (obscuration)
     *   attr[3] = diameter of core shadow in km
     *   attr[4] = azimuth of object
     *   attr[5] = true altitude of object
     *   attr[6] = apparent altitude of object
     *   attr[7] = angular separation of moon from object
     * @param string|null &$serr Error message (output)
     * @return int Eclipse type flags (combination of):
     *   SE_ECL_CENTRAL = central occultation
     *   SE_ECL_NONCENTRAL = non-central occultation
     *   SE_ECL_TOTAL = total occultation
     *   SE_ECL_ANNULAR = annular occultation (object larger than moon)
     *   SE_ECL_PARTIAL = partial occultation
     *   0 = no occultation at this time
     *   SE_ERR = error
     */
    function swe_lun_occult_where(
        float $tjd_ut,
        int $ipl,
        ?string $starname,
        int $ifl,
        array &$geopos,
        array &$attr,
        ?string &$serr = null
    ): int {
        return \Swisseph\Swe\Functions\LunarOccultationWhereFunctions::lunOccultWhere(
            $tjd_ut,
            $ipl,
            $starname,
            $ifl,
            $geopos,
            $attr,
            $serr
        );
    }
}

if (!function_exists('swe_sol_eclipse_where')) {
    /**
     * Calculate geographic position of solar eclipse maximum (center of shadow path)
     *
     * Finds where the central line of a solar eclipse crosses Earth's surface.
     * For total/annular eclipses, returns the point of greatest eclipse.
     * For partial eclipses, returns the point of maximum obscuration.
     *
     * Port from swecl.c:565-581
     *
     * @param float $tjd_ut Time in Julian days UT
     * @param int $ifl Ephemeris flag (SEFLG_SWIEPH, SEFLG_JPLEPH, SEFLG_MOSEPH)
     * @param array &$geopos Geographic position [longitude_deg, latitude_deg] (output):
     *   [0] = geographic longitude in degrees (east positive, west negative)
     *   [1] = geographic latitude in degrees (north positive, south negative)
     * @param array &$attr Eclipse attributes [20] (output):
     *   [0] = fraction of solar diameter covered by moon (magnitude)
     *   [1] = ratio of lunar diameter to solar diameter
     *   [2] = fraction of solar disc covered by moon (obscuration)
     *   [3] = diameter of core shadow in km (negative for annular eclipse)
     *   [4] = azimuth of sun at maximum eclipse
     *   [5] = true altitude of sun above horizon
     *   [6] = apparent altitude of sun above horizon
     *   [7] = angular distance of moon from sun in degrees
     *   [8] = magnitude according to NASA definition
     *   [9] = saros series number
     *   [10] = saros series member number
     * @param string &$serr Error message
     * @return int Eclipse type flags (combination of):
     *   SE_ECL_CENTRAL = central eclipse (umbra touches Earth)
     *   SE_ECL_NONCENTRAL = non-central eclipse (umbra axis misses Earth center)
     *   SE_ECL_TOTAL = total eclipse (moon diameter > sun diameter)
     *   SE_ECL_ANNULAR = annular eclipse (moon diameter < sun diameter)
     *   SE_ECL_PARTIAL = partial eclipse (only penumbra visible)
     *   0 = no eclipse at this time
     *   SE_ERR = error
     */
    function swe_sol_eclipse_where(
        float $tjd_ut,
        int $ifl,
        array &$geopos,
        array &$attr,
        ?string &$serr = null
    ): int {
        return \Swisseph\Swe\Functions\SolarEclipseWhereFunctions::where($tjd_ut, $ifl, $geopos, $attr, $serr);
    }
}

if (!function_exists('swe_lun_occult_when_loc')) {
    /**
     * Find next lunar occultation visible from specific geographic location
     *
     * @param float $tjdStart Search start time (Julian Day, UT)
     * @param int $ipl Planet number (SE_*)
     * @param string|null $starname Fixed star name (or null for planet)
     * @param int $ifl Ephemeris flag (SEFLG_*)
     * @param array $geopos Geographic position [longitude, latitude, altitude_m]
     * @param array &$tret Time array (output), declare as tret[10]
     * @param array &$attr Eclipse attributes (output), declare as attr[20]
     * @param int $backward Search direction (0=forward, 1=backward, SE_ECL_ONE_TRY=single)
     * @param string|null &$serr Error string (output)
     * @return int Return flags (SE_ECL_*)
     */
    function swe_lun_occult_when_loc(
        float $tjdStart,
        int $ipl,
        ?string $starname,
        int $ifl,
        array $geopos,
        array &$tret,
        array &$attr,
        int $backward,
        ?string &$serr
    ): int {
        return \Swisseph\Swe\Functions\LunarOccultationWhenLocFunctions::whenLoc(
            $tjdStart,
            $ipl,
            $starname,
            $ifl,
            $geopos,
            $tret,
            $attr,
            $backward,
            $serr
        );
    }
}

if (!function_exists('swe_gauquelin_sector')) {
    /**
     * Calculate Gauquelin sector position of a planet or fixed star
     *
     * Port of C function: int32 swe_gauquelin_sector(double t_ut, int32 ipl, char *starname, int32 iflag, int32 imeth, double *geopos, double atpress, double attemp, double *dgsect, char *serr)
     *
     * Gauquelin sectors are a system of 36 sectors based on the diurnal rotation
     * of celestial bodies, used in statistical astrology studies by Michel Gauquelin.
     *
     * Sectors are numbered 1-36:
     * - Sectors 1-18: from rise to set (above horizon)
     * - Sectors 19-36: from set to rise (below horizon)
     * - Sector 1: rise point
     * - Sector 10: upper culmination (MC)
     * - Sector 19: set point
     * - Sector 28: lower culmination (IC)
     *
     * @param float $t_ut Time in Julian days (UT)
     * @param int $ipl Planet number (SE_SUN, SE_MOON, etc.) - ignored if starname is given
     * @param string|null $starname Fixed star name, or null/empty for planets
     * @param int $iflag Ephemeris flags (SEFLG_SWIEPH | SEFLG_TOPOCTR, etc.)
     * @param int $imeth Method for calculation:
     *   - 0: Use Placidus house position with latitude
     *   - 1: Use Placidus house position without latitude (lat=0)
     *   - 2: Use rise/set of disc center (no refraction)
     *   - 3: Use rise/set of disc center (with refraction)
     *   - 4: Use rise/set without refraction (same as 2)
     * @param array $geopos Geographic position [longitude, latitude, height]:
     *   - geopos[0]: longitude in degrees (east positive)
     *   - geopos[1]: latitude in degrees (north positive)
     *   - geopos[2]: height in meters above sea level
     * @param float $atpress Atmospheric pressure in mbar (only for imeth=3)
     *   - 0 = use default 1013.25 mbar
     *   - If height > 0 and atpress=0, pressure is estimated
     * @param float $attemp Atmospheric temperature in °C (only for imeth=3)
     * @param float &$dgsect Output: Gauquelin sector position (1.0 to 37.0)
     *   - 0.0 = error (circumpolar body without rise/set)
     * @param string|null &$serr Error message
     * @return int OK (0) on success, ERR (-1) on error
     */
    function swe_gauquelin_sector(
        float $t_ut,
        int $ipl,
        ?string $starname,
        int $iflag,
        int $imeth,
        array $geopos,
        float $atpress,
        float $attemp,
        float &$dgsect,
        ?string &$serr = null
    ): int {
        return GauquelinSectorFunctions::gauquelinSector(
            $t_ut,
            $ipl,
            $starname,
            $iflag,
            $imeth,
            $geopos,
            $atpress,
            $attemp,
            $dgsect,
            $serr
        );
    }
}

if (!function_exists('swe_solcross')) {
    /**
     * Compute Sun's crossing over some longitude (Ephemeris Time)
     *
     * Port of C function: double swe_solcross(double x2cross, double jd_et, int flag, char *serr)
     *
     * Finds the next time when the Sun crosses a specified ecliptic longitude.
     * The returned time is in Ephemeris Time (ET/TT).
     *
     * @param float $x2cross Longitude to cross (degrees, 0-360)
     * @param float $jd_et Starting Julian day (Ephemeris Time)
     * @param int $flag Calculation flags:
     *   - SEFLG_HELCTR: 0=geocentric Sun, 1=heliocentric Earth
     *   - SEFLG_TRUEPOS: 0=apparent positions, 1=true positions
     *   - SEFLG_NONUT: 0=with nutation, 1=without nutation
     * @param string|null &$serr Error message
     * @return float Julian day of crossing (ET), or < $jd_et on error
     */
    function swe_solcross(
        float $x2cross,
        float $jd_et,
        int $flag,
        ?string &$serr = null
    ): float {
        return CrossingFunctions::solcross($x2cross, $jd_et, $flag, $serr);
    }
}

if (!function_exists('swe_solcross_ut')) {
    /**
     * Compute Sun's crossing over some longitude (Universal Time)
     *
     * Port of C function: double swe_solcross_ut(double x2cross, double jd_ut, int flag, char *serr)
     *
     * @param float $x2cross Longitude to cross (degrees, 0-360)
     * @param float $jd_ut Starting Julian day (Universal Time)
     * @param int $flag Calculation flags (same as swe_solcross)
     * @param string|null &$serr Error message
     * @return float Julian day of crossing (UT), or < $jd_ut on error
     */
    function swe_solcross_ut(
        float $x2cross,
        float $jd_ut,
        int $flag,
        ?string &$serr = null
    ): float {
        return CrossingFunctions::solcrossUt($x2cross, $jd_ut, $flag, $serr);
    }
}

if (!function_exists('swe_mooncross')) {
    /**
     * Compute Moon's crossing over some longitude (Ephemeris Time)
     *
     * Port of C function: double swe_mooncross(double x2cross, double jd_et, int flag, char *serr)
     *
     * @param float $x2cross Longitude to cross (degrees, 0-360)
     * @param float $jd_et Starting Julian day (Ephemeris Time)
     * @param int $flag Calculation flags:
     *   - SEFLG_TRUEPOS: 0=apparent, 1=true positions
     *   - SEFLG_NONUT: 0=with nutation, 1=without nutation
     * @param string|null &$serr Error message
     * @return float Julian day of crossing (ET), or < $jd_et on error
     */
    function swe_mooncross(
        float $x2cross,
        float $jd_et,
        int $flag,
        ?string &$serr = null
    ): float {
        return CrossingFunctions::mooncross($x2cross, $jd_et, $flag, $serr);
    }
}

if (!function_exists('swe_mooncross_ut')) {
    /**
     * Compute Moon's crossing over some longitude (Universal Time)
     *
     * Port of C function: double swe_mooncross_ut(double x2cross, double jd_ut, int flag, char *serr)
     *
     * If sidereal is chosen (SEFLG_SIDEREAL), default mode is Fagan/Bradley.
     * For different ayanamshas, call swe_set_sid_mode() first.
     *
     * @param float $x2cross Longitude to cross (degrees, 0-360)
     * @param float $jd_ut Starting Julian day (Universal Time)
     * @param int $flag Calculation flags:
     *   - SEFLG_TRUEPOS: 0=apparent, 1=true positions
     *   - SEFLG_NONUT: 0=with nutation, 1=without nutation
     *   - SEFLG_SIDEREAL: 0=tropical, 1=sidereal
     * @param string|null &$serr Error message
     * @return float Julian day of crossing (UT), or < $jd_ut on error
     */
    function swe_mooncross_ut(
        float $x2cross,
        float $jd_ut,
        int $flag,
        ?string &$serr = null
    ): float {
        return CrossingFunctions::mooncrossUt($x2cross, $jd_ut, $flag, $serr);
    }
}

if (!function_exists('swe_mooncross_node')) {
    /**
     * Compute next Moon crossing over node (Ephemeris Time)
     *
     * Port of C function: double swe_mooncross_node(double jd_et, int flag, double *xlon, double *xlat, char *serr)
     *
     * Finds when Moon crosses its orbital node (zero ecliptic latitude).
     * Returns the longitude and latitude at the crossing point.
     *
     * @param float $jd_et Starting Julian day (Ephemeris Time)
     * @param int $flag Calculation flags
     * @param float &$xlon Output: longitude at node crossing (degrees)
     * @param float &$xlat Output: latitude at node crossing (degrees, ~0)
     * @param string|null &$serr Error message
     * @return float Julian day of node crossing (ET), or < $jd_et on error
     */
    function swe_mooncross_node(
        float $jd_et,
        int $flag,
        float &$xlon,
        float &$xlat,
        ?string &$serr = null
    ): float {
        return CrossingFunctions::mooncrossNode($jd_et, $flag, $xlon, $xlat, $serr);
    }
}

if (!function_exists('swe_mooncross_node_ut')) {
    /**
     * Compute next Moon crossing over node (Universal Time)
     *
     * Port of C function: double swe_mooncross_node_ut(double jd_ut, int flag, double *xlon, double *xlat, char *serr)
     *
     * @param float $jd_ut Starting Julian day (Universal Time)
     * @param int $flag Calculation flags
     * @param float &$xlon Output: longitude at node crossing (degrees)
     * @param float &$xlat Output: latitude at node crossing (degrees, ~0)
     * @param string|null &$serr Error message
     * @return float Julian day of node crossing (UT), or < $jd_ut on error
     */
    function swe_mooncross_node_ut(
        float $jd_ut,
        int $flag,
        float &$xlon,
        float &$xlat,
        ?string &$serr = null
    ): float {
        return CrossingFunctions::mooncrossNodeUt($jd_ut, $flag, $xlon, $xlat, $serr);
    }
}

if (!function_exists('swe_helio_cross')) {
    /**
     * Compute planet's heliocentric crossing over longitude (Ephemeris Time)
     *
     * Port of C function: int32 swe_helio_cross(int ipl, double x2cross, double jd_et, int iflag, int dir, double *jd_cross, char *serr)
     *
     * Finds when a planet crosses a specified heliocentric ecliptic longitude.
     * Can search forward (dir >= 0) or backward (dir < 0).
     *
     * Note: Only for rough calculations. Not valid for Sun, Moon, nodes, or apsides.
     *
     * @param int $ipl Planet number (SE_MERCURY through SE_PLUTO, SE_CHIRON, etc.)
     * @param float $x2cross Longitude to cross (degrees, 0-360)
     * @param float $jd_et Starting Julian day (Ephemeris Time)
     * @param int $iflag Calculation flags (SEFLG_HELCTR automatically added)
     * @param int $dir Direction: >=0 for forward, <0 for backward
     * @param float &$jd_cross Output: Julian day of crossing (ET)
     * @param string|null &$serr Error message
     * @return int OK (0) on success, ERR (-1) on error
     */
    function swe_helio_cross(
        int $ipl,
        float $x2cross,
        float $jd_et,
        int $iflag,
        int $dir,
        float &$jd_cross,
        ?string &$serr = null
    ): int {
        return CrossingFunctions::helioCross($ipl, $x2cross, $jd_et, $iflag, $dir, $jd_cross, $serr);
    }
}

if (!function_exists('swe_helio_cross_ut')) {
    /**
     * Compute planet's heliocentric crossing over longitude (Universal Time)
     *
     * Port of C function: int32 swe_helio_cross_ut(int ipl, double x2cross, double jd_ut, int iflag, int dir, double *jd_cross, char *serr)
     *
     * @param int $ipl Planet number
     * @param float $x2cross Longitude to cross (degrees, 0-360)
     * @param float $jd_ut Starting Julian day (Universal Time)
     * @param int $iflag Calculation flags
     * @param int $dir Direction: >=0 for forward, <0 for backward
     * @param float &$jd_cross Output: Julian day of crossing (UT)
     * @param string|null &$serr Error message
     * @return int OK (0) on success, ERR (-1) on error
     */
    function swe_helio_cross_ut(
        int $ipl,
        float $x2cross,
        float $jd_ut,
        int $iflag,
        int $dir,
        float &$jd_cross,
        ?string &$serr = null
    ): int {
        return CrossingFunctions::helioCrossUt($ipl, $x2cross, $jd_ut, $iflag, $dir, $jd_cross, $serr);
    }
}
