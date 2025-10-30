<?php

declare(strict_types=1);

namespace Swisseph\Data;

/**
 * JPL Horizons right ascension corrections
 *
 * These corrections are applied when using JPL Horizons approximate mode.
 * They represent empirical adjustments to match JPL Horizons ephemeris output.
 *
 * Reference: swephlib.c lines 2160-2171
 *
 * The dcor_ra_jpl array contains correction values in milliarcseconds (mas)
 * for right ascension, indexed by year offset from DCOR_RA_JPL_TJD0.
 */
class JplHorizonsCorrections
{
    /**
     * Default offset for JPL Horizons in milliarcseconds
     */
    public const OFFSET_JPLHORIZONS = -52.3;

    /**
     * Reference Julian date for JPL Horizons corrections (1962-01-01)
     */
    public const DCOR_RA_JPL_TJD0 = 2437846.5;

    /**
     * Number of correction data points
     */
    public const NDCOR_RA_JPL = 51;

    /**
     * Right ascension correction table in milliarcseconds
     *
     * Indexed by year offset from DCOR_RA_JPL_TJD0.
     * Each value represents the RA correction for that year.
     *
     * Coverage: 1962-2012 (51 years, one value per year)
     *
     * @var array<float>
     */
    public const DCOR_RA_JPL = [
        -51.257, -51.103, -51.065, -51.503, -51.224, -50.796, -51.161, -51.181,
        -50.932, -51.064, -51.182, -51.386, -51.416, -51.428, -51.586, -51.766,
        -52.038, -52.370, -52.553, -52.397, -52.340, -52.676, -52.348, -51.964,
        -52.444, -52.364, -51.988, -52.212, -52.370, -52.523, -52.541, -52.496,
        -52.590, -52.629, -52.788, -53.014, -53.053, -52.902, -52.850, -53.087,
        -52.635, -52.185, -52.588, -52.292, -51.796, -51.961, -52.055, -52.134,
        -52.165, -52.141, -52.255,
    ];
}
