<?php

namespace Swisseph\SwephFile;

/**
 * Port of struct gen_const from sweph.h
 *
 * General astronomical constants
 */
final class GenConst
{
    /** Speed of light in AU/day */
    public float $clight = 0.0;

    /** Astronomical Unit in km */
    public float $aunit = 0.0;

    /** Heliocentric gravitational constant */
    public float $helgravconst = 0.0;

    /** Earth/Moon mass ratio */
    public float $ratme = 0.0;

    /** Sun radius in AU */
    public float $sunradius = 0.0;
}
