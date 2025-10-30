<?php

namespace Swisseph\SwephFile;

/**
 * Port of struct plan_data from sweph.h
 *
 * Contains ephemeris data for a single planet/body read from Swiss Ephemeris .se1 files
 */
final class PlanData
{
    /** Internal body number */
    public int $ibdy = 0;

    /** Bit flags: SEI_FLG_HELIO (helio vs bary), SEI_FLG_ROTATE, SEI_FLG_ELLIPSE */
    public int $iflg = 0;

    /** Number of coefficients of ephemeris polynomial (order + 1) */
    public int $ncoe = 0;

    /** File position of begin of planet's index */
    public int $lndx0 = 0;

    /** Number of index entries on file */
    public int $nndx = 0;

    /** File contains ephemeris from tfstart through tfend for this planet */
    public float $tfstart = 0.0;
    public float $tfend = 0.0;

    /** Segment size (days covered by a polynomial) */
    public float $dseg = 0.0;

    /** Epoch of elements */
    public float $telem = 0.0;

    /** Rotation parameters */
    public float $prot = 0.0;
    public float $qrot = 0.0;
    public float $dprot = 0.0;
    public float $dqrot = 0.0;

    /** Normalisation factor of Chebyshev coefficients */
    public float $rmax = 0.0;

    /** If reference ellipse is used */
    public float $peri = 0.0;
    public float $dperi = 0.0;

    /** Pointer to Chebyshev coeffs of reference ellipse (size: 2 * ncoe) */
    public ?array $refep = null;

    /** Unpacked segment info: start and end JD of current segment */
    public float $tseg0 = 0.0;
    public float $tseg1 = 0.0;

    /** Pointer to unpacked Chebyshev coeffs of segment (size: 3 * ncoe) */
    public ?array $segp = null;

    /** How many coefficients to evaluate (may be less than ncoe) */
    public int $neval = 0;

    /** Time for which previous computation was made */
    public float $teval = 0.0;

    /** Which ephemeris was used (SEFLG_SWIEPH, SEFLG_JPLEPH, etc.) */
    public int $iephe = 0;

    /** Position and speed vectors equatorial J2000 [x, y, z, dx, dy, dz] */
    public array $x = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];

    /** Flags: helio/bary, light-time, aberration, precession, etc. */
    public int $xflgs = 0;

    /** Return positions in various coordinate systems (24 elements total) */
    public array $xreturn = [];

    public function __construct()
    {
        // Initialize xreturn with 24 zeros
        $this->xreturn = array_fill(0, 24, 0.0);
    }
}
