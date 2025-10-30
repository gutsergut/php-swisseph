<?php

namespace Swisseph\SwephFile;

/**
 * Constants from sweph.h for Swiss Ephemeris file reader
 */
final class SwephConstants
{
    /** Planet indices for internal use - MUST match C code sweph.h:133-145 */
    public const SEI_EMB = 0;        // Earth-Moon Barycenter
    public const SEI_EARTH = 0;      // Earth (same slot as EMB, computed from EMB - Moon)
    public const SEI_SUN = 0;        // Sun (same slot as EMB initially)
    public const SEI_MOON = 1;       // Moon
    public const SEI_MERCURY = 2;
    public const SEI_VENUS = 3;
    public const SEI_MARS = 4;
    public const SEI_JUPITER = 5;
    public const SEI_SATURN = 6;
    public const SEI_URANUS = 7;
    public const SEI_NEPTUNE = 8;
    public const SEI_PLUTO = 9;
    public const SEI_SUNBARY = 10;   // Barycentric Sun
    public const SEI_ANYBODY = 13;   // Any asteroid
    public const SEI_CHIRON = 14;
    public const SEI_PHOLUS = 15;
    public const SEI_CERES = 16;
    public const SEI_PALLAS = 17;
    public const SEI_JUNO = 18;
    public const SEI_VESTA = 19;

    /** Number of planets */
    public const SEI_NPLANETS = 18;

    /** File indices */
    public const SEI_FILE_PLANET = 0;
    public const SEI_FILE_MOON = 1;
    public const SEI_FILE_MAIN_AST = 2;
    public const SEI_FILE_ANY_AST = 3;
    public const SEI_FILE_FIXSTAR = 4;

    /** Number of ephemeris files */
    public const SEI_NEPHFILES = 7;

    /** Maximum planets per file */
    public const SEI_FILE_NMAXPLAN = 50;

    /** Flags for plan_data.iflg */
    public const SEI_FLG_HELIO = 1;      // Heliocentric (vs barycentric)
    public const SEI_FLG_ROTATE = 2;     // Coefficients referred to orbital plane
    public const SEI_FLG_ELLIPSE = 4;    // Reference ellipse used
    public const SEI_FLG_EMBHEL = 8;     // EMB heliocentric

    /** Maximum character length for strings */
    public const AS_MAXCH = 256;

    /** Byte order flags */
    public const SEI_FILE_BIGENDIAN = 0;
    public const SEI_FILE_NOREORD = 512;
    public const SEI_FILE_LITENDIAN = 1024;
    public const SEI_FILE_REORD = 2048;

    /** Version-related constants */
    public const SE_FILE_SUFFIX = ".se1";

    /**
     * Planet number mapping: external (SE_*) to internal (SEI_*)
     * Port of pnoext2int[] from sweph.c:182
     */
    public const PNOEXT2INT = [
        0 => self::SEI_SUN,      // SE_SUN
        1 => self::SEI_MOON,     // SE_MOON
        2 => self::SEI_MERCURY,  // SE_MERCURY
        3 => self::SEI_VENUS,    // SE_VENUS
        4 => self::SEI_MARS,     // SE_MARS
        5 => self::SEI_JUPITER,  // SE_JUPITER
        6 => self::SEI_SATURN,   // SE_SATURN
        7 => self::SEI_URANUS,   // SE_URANUS
        8 => self::SEI_NEPTUNE,  // SE_NEPTUNE
        9 => self::SEI_PLUTO,    // SE_PLUTO
        // 10-13: reserved
        14 => self::SEI_EARTH,   // SE_EARTH
        15 => self::SEI_CHIRON,  // SE_CHIRON
        16 => self::SEI_PHOLUS,  // SE_PHOLUS
        17 => self::SEI_CERES,   // SE_CERES
        18 => self::SEI_PALLAS,  // SE_PALLAS
        19 => self::SEI_JUNO,    // SE_JUNO
        20 => self::SEI_VESTA,   // SE_VESTA
    ];
}
