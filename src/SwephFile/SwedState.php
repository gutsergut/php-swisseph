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

    /** JPL ephemeris DE number (e.g., 431, 406) */
    public int $jpldenum = 0;

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

    /** Precession model (for ayanamsa calculations) */
    private int $precessionModel = 0;

    /** Astronomical models configuration (precession, nutation, sidereal time, etc.) */
    public array $astroModels = [];

    /** Topocentric observer data */
    public TopoData $topd;

    /** Whether topocentric position was set */
    public bool $geoposIsSet = false;

    /** Nutation interpolation flag */
    public bool $do_interpolate_nut = false;

    /** Nutation interpolation cache */
    public object $interpol;

    private function __construct()
    {
        // Initialize file data array (7 files)
        for ($i = 0; $i < 7; $i++) {
            $this->fidat[$i] = new FileData();
        }

        // Initialize planet data array (SEI_NPLANETS = 18: indices 0-17)
        for ($i = 0; $i < 18; $i++) {
            $this->pldat[$i] = new PlanData();
        }

        // Initialize general constants
        $this->gcdat = new GenConst();

        // Initialize topocentric data
        $this->topd = new TopoData();

        // Initialize nutation interpolation cache
        $this->interpol = (object)[
            'tjd_nut0' => 0.0,
            'tjd_nut2' => 0.0,
            'nut_dpsi0' => 0.0,
            'nut_dpsi1' => 0.0,
            'nut_dpsi2' => 0.0,
            'nut_deps0' => 0.0,
            'nut_deps1' => 0.0,
            'nut_deps2' => 0.0,
        ];

        // Initialize nutation matrices (3x3 matrices as flat arrays)
        $this->nutMatrix = array_fill(0, 9, 0.0);
        $this->nutMatrixVelocity = array_fill(0, 9, 0.0);

        // Initialize obliquity data
        $this->oec = new \Swisseph\EpsilonData();
        $this->oec2000 = new \Swisseph\EpsilonData();
        // Pre-calculate J2000 obliquity
        $this->oec2000->calculate(2451545.0); // J2000.0

        // Initialize astronomical models array with defaults (NSE_MODELS = 8)
        $this->astroModels = array_fill(0, \Swisseph\Constants::NSE_MODELS, 0);
        // Set default models
        $this->astroModels[\Swisseph\Constants::SE_MODEL_PREC_SHORTTERM] =
            \Swisseph\Constants::SEMOD_PREC_DEFAULT_SHORT;
        $this->astroModels[\Swisseph\Constants::SE_MODEL_NUT] = \Swisseph\Constants::SEMOD_NUT_DEFAULT;
        $this->astroModels[\Swisseph\Constants::SE_MODEL_SIDT] = \Swisseph\Constants::SEMOD_SIDT_DEFAULT;
        $this->astroModels[\Swisseph\Constants::SE_MODEL_BIAS] = \Swisseph\Constants::SEMOD_BIAS_DEFAULT;
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

    /**
     * Get current precession model
     */
    public function getPrecessionModel(): int
    {
        return $this->precessionModel;
    }

    /**
     * Set precession model
     */
    public function setPrecessionModel(int $model): void
    {
        $this->precessionModel = $model;
    }

    /**
     * Ensure nutation state (dpsi, deps, matrices) is computed for given JD and flags.
     * Mirrors C logic of caching swed.nut for the date.
     */
    public function ensureNutation(float $tjd, int $iflag, float $seps, float $ceps): void
    {
        if ($this->tnut === $tjd && !empty($this->nutMatrix)) {
            return;
        }
        // Select model from flags and compute nutation
        $model = \Swisseph\Nutation::selectModelFromFlags($iflag);
        [$dpsi, $deps] = \Swisseph\Nutation::calc($tjd, $model, true);
        $this->dpsi = $dpsi;
        $this->deps = $deps;
        $this->tnut = $tjd;

        // Build nutation matrix using mean obliquity (seps/ceps)
        $epsMean = atan2($seps, $ceps);
        $this->nutMatrix = \Swisseph\NutationMatrix::build($dpsi, $deps, $epsMean, $seps, $ceps);
        // Fallback small-angle rotation params
        $this->snut = sin($dpsi);
        $this->cnut = cos($dpsi);

        // Compute nutation velocity matrix (matrix at t - NUT_SPEED_INTV)
        // Port of logic from sweph.c around computation of xv for nutation speed correction.
        $dt = \Swisseph\Constants::NUT_SPEED_INTV;
        $tPrev = $tjd - $dt;
        // Nutation at previous time
        [$dpsiPrev, $depsPrev] = \Swisseph\Nutation::calc($tPrev, $model, true);
        // Mean obliquity at previous time (do not overwrite global oec)
        $epsMeanPrev = \Swisseph\Obliquity::calc($tPrev, $iflag);
        $sepsPrev = sin($epsMeanPrev);
        $cepsPrev = cos($epsMeanPrev);
        $this->nutMatrixVelocity = \Swisseph\NutationMatrix::build(
            $dpsiPrev,
            $depsPrev,
            $epsMeanPrev,
            $sepsPrev,
            $cepsPrev
        );
    }
}
