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
use Swisseph\Swe\Functions\TransformFunctions;
use Swisseph\Swe\Functions\NodesApsidesFunctions;
use Swisseph\Swe\Functions\OrbitalElementsFunctions;
use Swisseph\Swe\Functions\PhenoFunctions;
use Swisseph\Swe\Functions\FixstarFunctions;

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
    }
}
if (!function_exists('swe_set_tid_acc')) {
    function swe_set_tid_acc(float $tacc): void
    {
        State::setTidAcc($tacc);
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
     * Convert between equatorial/ecliptic and horizontal coordinates. Units: degrees.
     * mode: Constants::SE_EQU2HOR | SE_ECL2HOR | SE_HOR2EQU | SE_HOR2ECL
     * xin: [a, b] depending on mode
     * geopos: [lon_deg (east+), lat_deg, alt_m]
     */
    function swe_azalt(
        float $jd_ut,
        int $mode,
        array $xin,
        array $geopos,
        float $atpress,
        float $attemp,
        array &$xout,
        ?string &$serr = null
    ): int {
        return HorizonFunctions::azalt($jd_ut, $mode, $xin, $geopos, $atpress, $attemp, $xout, $serr);
    }
}
if (!function_exists('swe_azalt_rev')) {
    /**
     * Reverse transformation: horizontal (azimuth/altitude) → equatorial or ecliptic.
     * Mirrors C API: swe_azalt_rev(tjd_ut, calc_flag, geopos, xin, xout)
     */
    function swe_azalt_rev(
        float $jd_ut,
        int $mode,
        array $geopos,
        array $xin,
        array &$xout,
        ?string &$serr = null
    ): int {
        return HorizonFunctions::azalt_rev($jd_ut, $mode, $xin, $geopos, $xout, $serr);
    }
}
if (!function_exists('swe_refrac')) {
    /**
     * Atmospheric refraction: true<->apparent altitude conversion.
     * dir: Constants::SE_TRUE_TO_APP (0) or SE_APP_TO_TRUE (1)
     * Returns refracted altitude (degrees).
     */
    function swe_refrac(float $alt_deg, float $atpress, float $attemp, int $dir): float
    {
        return HorizonFunctions::refrac($alt_deg, $atpress, $attemp, $dir);
    }
}

if (!function_exists('swe_refrac_extended')) {
    /**
     * Extended atmospheric refraction with lapse rate.
     *
     * @param float $inalt Altitude in degrees (true or apparent, depending on calc_flag)
     * @param float $geoalt Observer altitude above sea level in meters
     * @param float $atpress Atmospheric pressure in millibars (hectopascals)
     * @param float $attemp Atmospheric temperature in degrees Celsius
     * @param float $lapse_rate Temperature lapse rate (dT/dh) in K/m
     * @param int $calc_flag SE_TRUE_TO_APP (0) or SE_APP_TO_TRUE (1)
     * @param array|null $dret Optional return array with 4 elements:
     *                         [0] = true altitude
     *                         [1] = apparent altitude
     *                         [2] = refraction value
     *                         [3] = dip of horizon
     * @return float Calculated altitude (apparent or true, depending on calc_flag)
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
        return HorizonFunctions::refracExtended(
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

// Coordinate transforms: cotrans
if (!function_exists('swe_cotrans')) {
    /**
     * Rotate rectangular coordinates around X-axis by angle eps (deg).
     */
    function swe_cotrans(array $xpo, float $eps, array &$xpn): int
    {
        return TransformFunctions::cotrans($xpo, $eps, $xpn, $err);
    }
}
if (!function_exists('swe_cotrans_sp')) {
    /**
     * Rotate rectangular coordinates and velocities around X-axis by angle eps (deg).
     */
    function swe_cotrans_sp(array $xpo6, float $eps, array &$xpn6): int
    {
        return TransformFunctions::cotransSp($xpo6, $eps, $xpn6, $err);
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
     * @param float $tjd_lmt Julian Day in Local Mean Time
     * @param float $geolon Geographic longitude (degrees, positive East)
     * @param float &$tjd_lat Output: Julian Day in Local Apparent Time
     * @param string|null &$serr Error message
     * @return int SE_OK on success, SE_ERR on error
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
     * @param float $tjd_lat Julian Day in Local Apparent Time
     * @param float $geolon Geographic longitude (degrees, positive East)
     * @param float &$tjd_lmt Output: Julian Day in Local Mean Time
     * @param string|null &$serr Error message
     * @return int SE_OK on success, SE_ERR on error
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
