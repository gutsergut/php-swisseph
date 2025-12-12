<?php

declare(strict_types=1);

namespace Swisseph;

/**
 * Swe - Facade for Swiss Ephemeris functions
 *
 * Thin wrapper around global functions from functions.php
 * Allows object-oriented calls like Swe::swe_calc() instead of \swe_calc()
 * Also exports all constants from Constants class for convenience
 */
final class Swe
{
    // Export all planet constants
    public const SE_SUN = Constants::SE_SUN;
    public const SE_MOON = Constants::SE_MOON;
    public const SE_MERCURY = Constants::SE_MERCURY;
    public const SE_VENUS = Constants::SE_VENUS;
    public const SE_MARS = Constants::SE_MARS;
    public const SE_JUPITER = Constants::SE_JUPITER;
    public const SE_SATURN = Constants::SE_SATURN;
    public const SE_URANUS = Constants::SE_URANUS;
    public const SE_NEPTUNE = Constants::SE_NEPTUNE;
    public const SE_PLUTO = Constants::SE_PLUTO;
    public const SE_EARTH = Constants::SE_EARTH;

    // Export calculation flags
    public const SEFLG_SWIEPH = Constants::SEFLG_SWIEPH;
    public const SEFLG_JPLEPH = Constants::SEFLG_JPLEPH;
    public const SEFLG_MOSEPH = Constants::SEFLG_MOSEPH;
    public const SEFLG_HELCTR = Constants::SEFLG_HELCTR;
    public const SEFLG_TRUEPOS = Constants::SEFLG_TRUEPOS;
    public const SEFLG_NONUT = Constants::SEFLG_NONUT;
    public const SEFLG_SPEED = Constants::SEFLG_SPEED;
    public const SEFLG_TOPOCTR = Constants::SEFLG_TOPOCTR;
    public const SEFLG_EQUATORIAL = Constants::SEFLG_EQUATORIAL;
    public const SEFLG_XYZ = Constants::SEFLG_XYZ;
    public const SEFLG_RADIANS = Constants::SEFLG_RADIANS;
    public const SEFLG_BARYCTR = Constants::SEFLG_BARYCTR;
    public const SEFLG_SIDEREAL = Constants::SEFLG_SIDEREAL;
    public const SEFLG_J2000 = Constants::SEFLG_J2000;
    public const SEFLG_NOABERR = Constants::SEFLG_NOABERR;
    public const SEFLG_NOGDEFL = Constants::SEFLG_NOGDEFL;

    // Export utility constants
    public const SE_EQU2HOR = Constants::SE_EQU2HOR;
    public const SE_ECL2HOR = Constants::SE_ECL2HOR;
    public const SE_CALC_RISE = Constants::SE_CALC_RISE;
    public const SE_CALC_SET = Constants::SE_CALC_SET;
    public const SE_BIT_DISC_CENTER = Constants::SE_BIT_DISC_CENTER;
    public const SE_GREG_CAL = Constants::SE_GREG_CAL;
    public const SE_JUL_CAL = Constants::SE_JUL_CAL;
    public const SE_AST_OFFSET = Constants::SE_AST_OFFSET;

    // Export swe_revjul utility
    public static function swe_revjul(float $jd, int $gregflag): array
    {
        return \swe_revjul($jd, $gregflag);
    }

    /**
     * Calculate planetary positions
     * @see \swe_calc()
     */
    public static function swe_calc(float $jd_tt, int $ipl, int $iflag, array &$xx, ?string &$serr = null): int
    {
        return \swe_calc($jd_tt, $ipl, $iflag, $xx, $serr);
    }

    /**
     * Calculate planetary positions (UT version)
     * @see \swe_calc_ut()
     */
    public static function swe_calc_ut(float $jd_ut, int $ipl, int $iflag, array &$xx, ?string &$serr = null): int
    {
        return \swe_calc_ut($jd_ut, $ipl, $iflag, $xx, $serr);
    }

    /**
     * Calculate fixed star positions
     * @see \swe_fixstar()
     */
    public static function swe_fixstar(string $star, float $jd_tt, int $iflag, array &$xx, ?string &$serr = null): int
    {
        return \swe_fixstar($star, $jd_tt, $iflag, $xx, $serr);
    }

    /**
     * Calculate fixed star magnitude
     * @see \swe_fixstar_mag()
     */
    public static function swe_fixstar_mag(string $star, float &$mag, ?string &$serr = null): int
    {
        return \swe_fixstar_mag($star, $mag, $serr);
    }

    /**
     * Convert equatorial to horizontal coordinates
     * @see \swe_azalt()
     */
    public static function swe_azalt(
        float $tjd_ut,
        int $calc_flag,
        array $geopos,
        float $atpress,
        float $attemp,
        array $xin,
        array &$xaz
    ): void {
        \swe_azalt($tjd_ut, $calc_flag, $geopos, $atpress, $attemp, $xin, $xaz);
    }

    /**
     * Calculate planetary phenomena (phase, magnitude, etc.)
     * @see \swe_pheno_ut()
     */
    public static function swe_pheno_ut(
        float $tjd_ut,
        int $ipl,
        int $iflag,
        array &$attr,
        ?string &$serr = null
    ): int {
        return \swe_pheno_ut($tjd_ut, $ipl, $iflag, $attr, $serr);
    }

    /**
     * Calculate Delta T (TT-UT)
     * @see \swe_deltat_ex()
     */
    public static function swe_deltat_ex(float $jd_ut, int $epheflag, ?string &$serr = null): float
    {
        return \swe_deltat_ex($jd_ut, $epheflag, $serr);
    }

    /**
     * Set topocentric position
     * @see \swe_set_topo()
     */
    public static function swe_set_topo(float $lon, float $lat, float $alt): void
    {
        \swe_set_topo($lon, $lat, $alt);
    }

    /**
     * Calculate sidereal time
     * @see \swe_sidtime()
     */
    public static function swe_sidtime(float $jd_ut): float
    {
        return \swe_sidtime($jd_ut);
    }

    /**
     * Normalize degrees to 0-360
     * @see \swe_degnorm()
     */
    public static function swe_degnorm(float $deg): float
    {
        return \swe_degnorm($deg);
    }

    /**
     * Coordinate transformation (rotation)
     * @see \swe_cotrans()
     */
    public static function swe_cotrans(array $xpo, array &$xpn, float $eps): void
    {
        \swe_cotrans($xpo, $xpn, $eps);
    }

    /**
     * Calculate refraction
     * @see \swe_refrac_extended()
     */
    public static function swe_refrac_extended(
        float $inalt,
        float $geoalt,
        float $atpress,
        float $attemp,
        float $lapse_rate,
        int $calc_flag,
        ?array &$dret = null
    ): float {
        return \swe_refrac_extended($inalt, $geoalt, $atpress, $attemp, $lapse_rate, $calc_flag, $dret);
    }
}
