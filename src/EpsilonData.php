<?php

declare(strict_types=1);

namespace Swisseph;

/**
 * Obliquity of the ecliptic data
 *
 * Port of struct epsilon from sweph.h:601-605
 * Stores mean obliquity and its trigonometric values for a specific Julian day.
 */
class EpsilonData
{
    /** Julian day for which epsilon was calculated */
    public float $teps = 0.0;

    /** Mean obliquity of ecliptic in radians */
    public float $eps = 0.0;

    /** sin(eps) */
    public float $seps = 0.0;

    /** cos(eps) */
    public float $ceps = 1.0;

    /**
     * Calculate and update epsilon for given Julian day
     *
     * @param float $jd Julian day (TT)
     * @param int $iflag Calculation flags
     */
    public function calculate(float $jd, int $iflag = 0): void
    {
        $this->teps = $jd;
        $this->eps = Obliquity::calc($jd, $iflag);
        $this->seps = sin($this->eps);
        $this->ceps = cos($this->eps);
    }

    /**
     * Check if epsilon needs recalculation for given Julian day
     *
     * @param float $jd Julian day
     * @return bool True if recalculation needed
     */
    public function needsUpdate(float $jd): bool
    {
        return $this->teps !== $jd;
    }
}
