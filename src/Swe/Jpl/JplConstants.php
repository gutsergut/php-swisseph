<?php

declare(strict_types=1);

namespace Swisseph\Swe\Jpl;

/**
 * JPL Ephemeris constants and body indices
 * Port of swejpl.h from Swiss Ephemeris
 */
class JplConstants
{
    // JPL body indices (modified by Swiss Ephemeris: start at 0, not 1)
    public const J_MERCURY = 0;
    public const J_VENUS = 1;
    public const J_EARTH = 2;   // Earth-Moon barycenter in JPL
    public const J_MARS = 3;
    public const J_JUPITER = 4;
    public const J_SATURN = 5;
    public const J_URANUS = 6;
    public const J_NEPTUNE = 7;
    public const J_PLUTO = 8;
    public const J_MOON = 9;     // Geocentric Moon
    public const J_SUN = 10;
    public const J_SBARY = 11;   // Solar System Barycenter
    public const J_EMB = 12;     // Earth-Moon Barycenter
    public const J_NUT = 13;     // Nutations
    public const J_LIB = 14;     // Librations

    // Return codes
    public const OK = 0;
    public const NOT_AVAILABLE = -1;
    public const BEYOND_EPH_LIMITS = -2;
    public const ERR = -1;

    // Map JPL body index to Swiss Ephemeris planet index
    public const JPL_TO_SE = [
        self::J_MERCURY => 2,   // SE_MERCURY
        self::J_VENUS => 3,     // SE_VENUS
        self::J_EARTH => 14,    // SE_EARTH
        self::J_MARS => 4,      // SE_MARS
        self::J_JUPITER => 5,   // SE_JUPITER
        self::J_SATURN => 6,    // SE_SATURN
        self::J_URANUS => 7,    // SE_URANUS
        self::J_NEPTUNE => 8,   // SE_NEPTUNE
        self::J_PLUTO => 9,     // SE_PLUTO
        self::J_MOON => 1,      // SE_MOON
        self::J_SUN => 0,       // SE_SUN
    ];

    // Map Swiss Ephemeris planet index to JPL body index
    public const SE_TO_JPL = [
        0 => self::J_SUN,       // SE_SUN
        1 => self::J_MOON,      // SE_MOON
        2 => self::J_MERCURY,   // SE_MERCURY
        3 => self::J_VENUS,     // SE_VENUS
        4 => self::J_MARS,      // SE_MARS
        5 => self::J_JUPITER,   // SE_JUPITER
        6 => self::J_SATURN,    // SE_SATURN
        7 => self::J_URANUS,    // SE_URANUS
        8 => self::J_NEPTUNE,   // SE_NEPTUNE
        9 => self::J_PLUTO,     // SE_PLUTO
        14 => self::J_EARTH,    // SE_EARTH
    ];

    // Known DE versions and their record sizes
    public const DE_RECORD_SIZES = [
        102 => 1652,    // Filled with blanks to match DE200
        200 => 1652,
        403 => 2036,
        404 => 1456,
        405 => 2036,
        406 => 1456,
        410 => 2036,
        413 => 2036,
        414 => 2036,
        418 => 2036,
        421 => 2036,
        430 => 2036,
        431 => 2036,
        440 => 2036,
        441 => 2036,
    ];

    // Segment sizes in days for known DE versions
    public const DE_SEGMENT_SIZES = [
        102 => 64.0,
        200 => 32.0,
        403 => 32.0,
        404 => 32.0,
        405 => 32.0,
        406 => 32.0,
        430 => 32.0,
        431 => 32.0,
        440 => 32.0,
        441 => 32.0,
    ];
}
