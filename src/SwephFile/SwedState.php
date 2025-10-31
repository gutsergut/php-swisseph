<?php

namespace Swisseph\SwephFile;

/**
 * Simplified port of struct swe_data from sweph.h
 *
 * Global state for Swiss Ephemeris file reader.
 * Contains only essential fields needed for file-based ephemeris calculation.
 */
final class SwedState
{
    /** Singleton instance */
    private static ?self $instance = null;

    /** Ephemeris path */
    public string $ephepath = '';

    /** File data for ephemeris files (SEI_NEPHFILES = 7) */
    public array $fidat = [];

    /** Planet data (SEI_NPLANETS = 18) */
    public array $pldat = [];

    /** General constants (clight, aunit, etc.) */
    public GenConst $gcdat;

    /** Nutation matrix and parameters */
    public array $nutMatrix = [];

    /** Nutation matrix velocity (for speed calculations) */
    public array $nutMatrixVelocity = [];

    /** Sine of nutation in obliquity */
    public float $snut = 0.0;

    /** Cosine of nutation in obliquity */
    public float $cnut = 1.0;

    /** Nutation in longitude (dpsi) */
    public float $dpsi = 0.0;

    /** Nutation in obliquity (deps) */
    public float $deps = 0.0;

    /** Julian day for which nutation was calculated */
    public float $tnut = 0.0;

    /** Obliquity of ecliptic for current date */
    public \Swisseph\EpsilonData $oec;

    /** Obliquity of ecliptic for J2000 */
    public \Swisseph\EpsilonData $oec2000;

    private function __construct()
    {
        // Initialize file data array (7 files)
        for ($i = 0; $i < 7; $i++) {
            $this->fidat[$i] = new FileData();
        }

        // Initialize planet data array (18 planets)
        for ($i = 0; $i < 18; $i++) {
            $this->pldat[$i] = new PlanData();
        }

        // Initialize general constants
        $this->gcdat = new GenConst();

        // Initialize nutation matrices (3x3 matrices as flat arrays)
        $this->nutMatrix = array_fill(0, 9, 0.0);
        $this->nutMatrixVelocity = array_fill(0, 9, 0.0);

        // Initialize obliquity data
        $this->oec = new \Swisseph\EpsilonData();
        $this->oec2000 = new \Swisseph\EpsilonData();
        // Pre-calculate J2000 obliquity
        $this->oec2000->calculate(2451545.0); // J2000.0
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Set ephemeris path
     */
    public function setEphePath(string $path): void
    {
        $this->ephepath = $path;
    }
}
