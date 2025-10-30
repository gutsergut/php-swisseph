<?php

declare(strict_types=1);

namespace Swisseph\Domain\NodesApsides;

/**
 * VSOP87 mean orbital elements for planets Mercury-Neptune
 * Port from Swiss Ephemeris swecl.c mean element tables
 */
class PlanetaryElements
{
    /**
     * Longitude of ascending node coefficients [a0, a1, a2, a3] in degrees
     * For planet i: node = a0 + a1*T + a2*T^2 + a3*T^3, T = centuries from J2000
     */
    public const EL_NODE = [
        [48.330893,  1.1861890,  0.00017587,  0.000000211],  // Mercury
        [76.679920,  0.9011190,  0.00040665, -0.000000080],  // Venus
        [0.0,        0.0,        0.0,         0.0],          // Earth
        [49.558093,  0.7720923,  0.00001605,  0.000002325],  // Mars
        [100.464441, 1.0209550,  0.00040117,  0.000000569],  // Jupiter
        [113.665524, 0.8770970, -0.00012067, -0.000002380],  // Saturn
        [74.005947,  0.5211258,  0.00133982,  0.000018516],  // Uranus
        [131.784057, 1.1022057,  0.00026006, -0.000000636],  // Neptune
    ];

    /**
     * Argument of perihelion coefficients [a0, a1, a2, a3] in degrees
     */
    public const EL_PERI = [
        [77.456119,   1.5564775,  0.00029589,  0.000000056],  // Mercury
        [131.563707,  1.4022188, -0.00107337, -0.000005315],  // Venus
        [102.937348,  1.7195269,  0.00045962,  0.000000499],  // Earth
        [336.060234,  1.8410331,  0.00013515,  0.000000318],  // Mars
        [14.331309,   1.6126668,  0.00103127, -0.000004569],  // Jupiter
        [93.056787,   1.9637694,  0.00083757,  0.000004899],  // Saturn
        [173.005159,  1.4863784,  0.00021450,  0.000000433],  // Uranus
        [48.123691,   1.4262677,  0.00037918, -0.000000003],  // Neptune
    ];

    /**
     * Inclination coefficients [a0, a1, a2, a3] in degrees
     */
    public const EL_INCL = [
        [7.004986,   0.0018215, -0.00001809,  0.000000053],  // Mercury
        [3.394662,   0.0010037, -0.00000088, -0.000000007],  // Venus
        [0.0,        0.0,        0.0,         0.0],          // Earth
        [1.849726,  -0.0006010,  0.00001276, -0.000000006],  // Mars
        [1.303270,  -0.0054966,  0.00000465, -0.000000004],  // Jupiter
        [2.488878,  -0.0037363, -0.00001516,  0.000000089],  // Saturn
        [0.773196,   0.0007744,  0.00003749, -0.000000092],  // Uranus
        [1.769952,  -0.0093082, -0.00000708,  0.000000028],  // Neptune
    ];

    /**
     * Eccentricity coefficients [a0, a1, a2, a3]
     */
    public const EL_ECCE = [
        [0.20563175,  0.000020406, -0.0000000284, -0.00000000017],  // Mercury
        [0.00677188, -0.000047766,  0.0000000975,  0.00000000044],  // Venus
        [0.01670862, -0.000042037, -0.0000001236,  0.00000000004],  // Earth
        [0.09340062,  0.000090483, -0.0000000806, -0.00000000035],  // Mars
        [0.04849485,  0.000163244, -0.0000004719, -0.00000000197],  // Jupiter
        [0.05550862, -0.000346818, -0.0000006456,  0.00000000338],  // Saturn
        [0.04629590, -0.000027337,  0.0000000790,  0.00000000025],  // Uranus
        [0.00898809,  0.000006408, -0.0000000008, -0.00000000005],  // Neptune
    ];

    /**
     * Semi-major axis coefficients [a0, a1, a2, a3] in AU
     */
    public const EL_SEMA = [
        [0.387098310,  0.0,           0.0,            0.0],  // Mercury
        [0.723329820,  0.0,           0.0,            0.0],  // Venus
        [1.000001018,  0.0,           0.0,            0.0],  // Earth
        [1.523679342,  0.0,           0.0,            0.0],  // Mars
        [5.202603191,  0.0000001913,  0.0,            0.0],  // Jupiter
        [9.554909596,  0.0000021389,  0.0,            0.0],  // Saturn
        [19.218446062, -0.0000000372, 0.00000000098,  0.0],  // Uranus
        [30.110386869, -0.0000001663, 0.00000000069,  0.0],  // Neptune
    ];

    /**
     * Mapping from Swiss Ephemeris planet ID to element table index
     * indices: SUN=0, MOON=1, MERCURY=2, VENUS=3, MARS=4, JUPITER=5, SATURN=6, URANUS=7, NEPTUNE=8, PLUTO=9, ...
     */
    public const IPL_TO_ELEM = [2, 0, 0, 1, 3, 4, 5, 6, 7, 0, 0, 0, 0, 0, 2];

    /**
     * Planet masses: reciprocal mass ratios relative to Sun
     * From plmass array in swecl.c
     */
    public const PLMASS = [
        6023600,        // Mercury
        408523.719,     // Venus
        328900.5,       // Earth and Moon
        3098703.59,     // Mars
        1047.348644,    // Jupiter
        3497.9018,      // Saturn
        22902.98,       // Uranus
        19412.26,       // Neptune
        136566000,      // Pluto
    ];

    /**
     * Evaluate polynomial: a0 + a1*t + a2*t^2 + a3*t^3
     *
     * @param array $coeffs [a0, a1, a2, a3]
     * @param float $t Time variable (centuries from J2000)
     * @return float Result
     */
    public static function evalPoly(array $coeffs, float $t): float
    {
        return $coeffs[0] + $coeffs[1] * $t + $coeffs[2] * $t * $t + $coeffs[3] * $t * $t * $t;
    }
}
