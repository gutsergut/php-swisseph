<?php

declare(strict_types=1);

namespace Swisseph\Moshier;

/**
 * Constants for Moshier semi-analytical planetary theory
 *
 * Port of constants from swemplan.c
 *
 * Source: Swiss Ephemeris by Astrodienst AG
 * Original by Steve Moshier, modified for SWISSEPH by Dieter Koch
 *
 * @see с-swisseph/swisseph/swemplan.c
 */
final class MoshierConstants
{
    /**
     * Time scale factor: 10000 Julian years in days
     *
     * Formula: 365.25 * 10000 = 3652500 days
     * Used to convert Julian Date to T in units of 10000 Julian years
     *
     * @see swemplan.c:67
     */
    public const TIMESCALE = 3652500.0;

    /**
     * Moshier planetary ephemeris date limits
     * From -3000 to +3000 Julian years
     *
     * @see sweph.h:138-139
     */
    public const MOSHPLEPH_START = 625673.5;    // -3000 = JD 625673.5
    public const MOSHPLEPH_END   = 2818930.5;   // +3000 = JD 2818930.5

    /**
     * Moshier lunar ephemeris date limits
     * From -3000 to +3000 Julian years
     *
     * @see sweph.h:140-141
     */
    public const MOSHLUEPH_START = 625673.5;
    public const MOSHLUEPH_END   = 2818930.5;

    /**
     * Fictitious planets geo flag
     * @see swemplan.c:72
     */
    public const FICT_GEO = 1;

    /**
     * Gaussian gravitational constant for Earth only
     * k = 0.01720209895 → k² = 0.0000298122353216
     *
     * @see swemplan.c:73
     */
    public const KGAUSS_GEO = 0.0000298122353216;

    /**
     * Julian date of J2000.0 epoch
     */
    public const J2000 = 2451545.0;

    /**
     * Arc seconds per full circle (360° = 1296000")
     */
    public const ARCSEC_PER_CIRCLE = 1296000.0;

    /**
     * Mean orbital frequencies from Simon et al (1994)
     * Arc seconds per 10000 Julian years
     *
     * Indices: 0=Mercury, 1=Venus, 2=Earth, 3=Mars, 4=Jupiter,
     *          5=Saturn, 6=Uranus, 7=Neptune, 8=Pluto
     *
     * @see swemplan.c:86-95
     */
    public const FREQS = [
        53810162868.8982,   // Mercury
        21066413643.3548,   // Venus
        12959774228.3429,   // Earth
        6890507749.3988,    // Mars
        1092566037.7991,    // Jupiter
        439960985.5372,     // Saturn
        154248119.3933,     // Uranus
        78655032.0744,      // Neptune
        52272245.1795,      // Pluto
    ];

    /**
     * Mean orbital phases from Simon et al (1994)
     * Arc seconds at J2000.0
     *
     * @see swemplan.c:99-109
     */
    public const PHASES = [
        252.25090552 * 3600.0,   // Mercury = 908127.198720"
        181.97980085 * 3600.0,   // Venus   = 655132.803060"
        100.46645683 * 3600.0,   // Earth   = 361679.524588"
        355.43299958 * 3600.0,   // Mars    = 1279558.798488"
        34.35151874 * 3600.0,    // Jupiter = 123665.467464"
        50.07744430 * 3600.0,    // Saturn  = 180278.799480"
        314.05500511 * 3600.0,   // Uranus  = 1130598.018396"
        304.34866548 * 3600.0,   // Neptune = 1095655.195728"
        860492.1546,             // Pluto   = 860492.1546"
    ];

    /**
     * Planet index mapping from external to Moshier internal
     * pnoext2int[external] = moshier_index
     *
     * SEI_EARTH(0) → EMB(2), SEI_MOON(1) → EMB(2), etc.
     *
     * @see swemplan.c:80
     */
    public const PNOEXT2MSH = [
        2,  // SEI_EARTH  → Earth-Moon barycenter
        2,  // SEI_MOON   → Earth-Moon barycenter (Moon needs special handling)
        0,  // SEI_MERCURY → Mercury
        1,  // SEI_VENUS   → Venus
        3,  // SEI_MARS    → Mars
        4,  // SEI_JUPITER → Jupiter
        5,  // SEI_SATURN  → Saturn
        6,  // SEI_URANUS  → Uranus
        7,  // SEI_NEPTUNE → Neptune
        8,  // SEI_PLUTO   → Pluto
    ];

    /**
     * Number of planets in Moshier tables
     */
    public const NUM_PLANETS = 9;

    /**
     * Internal planet indices (SEI_*)
     * These match sweph.h definitions
     *
     * @see sweph.h:133-144
     */
    public const SEI_EMB = 0;        // Earth-Moon barycenter (in context of Moshier)
    public const SEI_EARTH = 0;      // Earth (alias for SEI_EMB, separate handling)
    public const SEI_SUN = 0;        // Sun (alias for SEI_EMB position, but special handling)
    public const SEI_MOON = 1;
    public const SEI_MERCURY = 2;
    public const SEI_VENUS = 3;
    public const SEI_MARS = 4;
    public const SEI_JUPITER = 5;
    public const SEI_SATURN = 6;
    public const SEI_URANUS = 7;
    public const SEI_NEPTUNE = 8;
    public const SEI_PLUTO = 9;
    public const SEI_SUNBARY = 10;   // Barycentric sun (for deflection calculations)

    /**
     * Maximum number of arguments per harmonic term
     */
    public const MAX_ARGS = 9;

    /**
     * Normalize angle to range 0 to 1296000 arcseconds (360°)
     *
     * @param float $x Angle in arcseconds
     * @return float Normalized angle in arcseconds [0, 1296000)
     *
     * @see swemplan.c:69 mods3600()
     */
    public static function mods3600(float $x): float
    {
        return $x - 1.296e6 * floor($x / 1.296e6);
    }
}
