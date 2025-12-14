<?php

declare(strict_types=1);

namespace Swisseph\Moshier;

/**
 * Planet table data structure for Moshier ephemeris
 *
 * Port of struct plantbl from sweph.h:698-706
 *
 * Each planet has polynomial coefficient tables for:
 * - Longitude (lon_tbl)
 * - Latitude (lat_tbl)
 * - Radius/distance (rad_tbl)
 *
 * And an argument table (arg_tbl) that specifies which
 * mean anomaly combinations to use for each term.
 */
final class PlanetTable
{
    /** @var array<int> Maximum harmonic for each planet argument (9 values) */
    public array $maxHarmonic = [];

    /** @var int Maximum power of T (time) in polynomial expansion */
    public int $maxPowerOfT = 0;

    /** @var array<int> Argument table */
    public array $argTbl = [];

    /** @var array<float> Longitude polynomial coefficients (arcseconds) */
    public array $lonTbl = [];

    /** @var array<float> Latitude polynomial coefficients (arcseconds) */
    public array $latTbl = [];

    /** @var array<float> Radius polynomial coefficients (AU * 10^8) */
    public array $radTbl = [];

    /** @var float Mean distance in AU */
    public float $distance = 0.0;

    /**
     * Create planet table from data arrays
     *
     * @param array<int> $maxHarmonic Maximum harmonics for each planet (9 values)
     * @param int $maxPowerOfT Maximum power of T
     * @param array<int> $argTbl Argument table
     * @param array<float> $lonTbl Longitude coefficients
     * @param array<float> $latTbl Latitude coefficients
     * @param array<float> $radTbl Radius coefficients
     * @param float $distance Mean distance in AU
     */
    public function __construct(
        array $maxHarmonic,
        int $maxPowerOfT,
        array $argTbl,
        array $lonTbl,
        array $latTbl,
        array $radTbl,
        float $distance
    ) {
        $this->maxHarmonic = $maxHarmonic;
        $this->maxPowerOfT = $maxPowerOfT;
        $this->argTbl = $argTbl;
        $this->lonTbl = $lonTbl;
        $this->latTbl = $latTbl;
        $this->radTbl = $radTbl;
        $this->distance = $distance;
    }
}
