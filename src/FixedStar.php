<?php

namespace Swisseph;

/**
 * Fixed star data structure.
 * Port of struct fixed_star from sweph.h:773-780
 */
class FixedStar
{
    /** Max length for star names */
    public const STAR_LENGTH = 40;

    /** Search key (may be prefixed with comma) */
    public string $skey = '';

    /** Traditional star name (e.g., "Aldebaran") */
    public string $starname = '';

    /** Bayer/Flamsteed designation (e.g., "alTau") */
    public string $starbayer = '';

    /** Star catalog number */
    public string $starno = '';

    /** Epoch of coordinates (Julian day) */
    public float $epoch = 0.0;

    /** Right ascension (degrees) */
    public float $ra = 0.0;

    /** Declination (degrees) */
    public float $de = 0.0;

    /** Proper motion in RA (arcsec/century) */
    public float $ramot = 0.0;

    /** Proper motion in Dec (arcsec/century) */
    public float $demot = 0.0;

    /** Radial velocity (km/sec) */
    public float $radvel = 0.0;

    /** Parallax (arcsec) */
    public float $parall = 0.0;

    /** Visual magnitude */
    public float $mag = 0.0;
}
