<?php

declare(strict_types=1);

namespace Swisseph;

use Swisseph\SwephFile\SwedState;

/**
 * Earth Orientation Parameters (EOP) data loader and interpolator
 *
 * Port of load_dpsi_deps() and bessel() from sweph.c / swephlib.c
 *
 * This class loads dpsi/deps corrections from IERS data files
 * required for SEFLG_JPLHOR mode to reproduce JPL Horizons positions.
 *
 * Files needed:
 * - eop_1962_today.txt (EOPC04 data)
 * - eop_finals.txt (finals.all data for near future)
 */
final class EopData
{
    // Constants from swephlib.h
    public const FILE_EOPC04 = 'eop_1962_today.txt';
    public const FILE_FINALS = 'eop_finals.txt';

    // TJD when JPL Horizons dpsi/deps data starts
    public const TJD0_HORIZONS = 2437684.5;  // 1962-01-01

    // Default corrections at TJD0 (for JPLHOR_APPROX mode)
    public const DPSI_IAU1980_TJD0 = 0.064284;  // arcsec (64.284 / 1000)
    public const DEPS_IAU1980_TJD0 = 0.006151;  // arcsec (6.151 / 1000)

    // Maximum data points (100 years of daily data)
    private const MAX_DATA_POINTS = 36525;

    // MJD to TJD offset
    private const TJDOFS = 2400000.5;

    // Singleton instance
    private static ?self $instance = null;

    // EOP data arrays
    /** @var float[] dpsi corrections in arcseconds */
    private array $dpsi = [];

    /** @var float[] deps corrections in arcseconds */
    private array $deps = [];

    // Data range
    private float $tjdBeg = 0.0;
    private float $tjdEnd = 0.0;

    // Loading status: -1=error, 0=not loaded, 1=eopc04 only, 2=both files
    private int $loadStatus = 0;

    private function __construct()
    {
        // Private constructor for singleton
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Reset singleton (for testing)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Load EOP data from files
     * Port of load_dpsi_deps() from sweph.c
     *
     * @return int Load status: -1=error, 1=eopc04 only, 2=both files loaded
     */
    public function load(): int
    {
        if ($this->loadStatus > 0) {
            return $this->loadStatus;
        }

        $swed = SwedState::getInstance();
        $ephePath = $swed->getEphePath();

        // Try to load primary file (eop_1962_today.txt)
        $eopc04Path = $this->findFile(self::FILE_EOPC04, $ephePath);
        if ($eopc04Path === null) {
            $this->loadStatus = -1;
            return -1;
        }

        if (!$this->loadEopc04($eopc04Path)) {
            $this->loadStatus = -1;
            return -1;
        }

        $this->loadStatus = 1;

        // Try to load secondary file (eop_finals.txt) for near future data
        $finalsPath = $this->findFile(self::FILE_FINALS, $ephePath);
        if ($finalsPath !== null) {
            if ($this->loadFinals($finalsPath)) {
                $this->loadStatus = 2;
            }
        }

        return $this->loadStatus;
    }

    /**
     * Check if data is loaded
     */
    public function isLoaded(): bool
    {
        return $this->loadStatus > 0;
    }

    /**
     * Get dpsi correction at given TJD using Bessel interpolation
     *
     * @param float $tjd Julian date (TT)
     * @return float dpsi in arcseconds
     */
    public function getDpsi(float $tjd): float
    {
        if (!$this->isLoaded()) {
            return self::DPSI_IAU1980_TJD0;  // fallback
        }

        $t = $tjd - $this->tjdBeg;
        $n = count($this->dpsi);

        return $this->bessel($this->dpsi, $n, $t);
    }

    /**
     * Get deps correction at given TJD using Bessel interpolation
     *
     * @param float $tjd Julian date (TT)
     * @return float deps in arcseconds
     */
    public function getDeps(float $tjd): float
    {
        if (!$this->isLoaded()) {
            return self::DEPS_IAU1980_TJD0;  // fallback
        }

        $t = $tjd - $this->tjdBeg;
        $n = count($this->deps);

        return $this->bessel($this->deps, $n, $t);
    }

    /**
     * Get TJD range of loaded data
     *
     * @return array{0: float, 1: float} [tjdBeg, tjdEnd]
     */
    public function getRange(): array
    {
        return [$this->tjdBeg, $this->tjdEnd];
    }

    /**
     * Bessel interpolation
     * Port of bessel() from swephlib.c:2004-2063
     *
     * @param float[] $v Array of values
     * @param int $n Number of data points
     * @param float $t Position in array (days from tjdBeg)
     * @return float Interpolated value
     */
    private function bessel(array $v, int $n, float $t): float
    {
        if ($t <= 0) {
            return $v[0] ?? 0.0;
        }
        if ($t >= $n - 1) {
            return $v[$n - 1] ?? 0.0;
        }

        $p = floor($t);
        $iy = (int)$t;

        // Zeroth order estimate is value at start of year
        $ans = $v[$iy];
        $k = $iy + 1;

        if ($k >= $n) {
            return $ans;
        }

        // The fraction of tabulation interval
        $p = $t - $p;
        $ans += $p * ($v[$k] - $v[$iy]);

        if ($iy - 1 < 0 || $iy + 2 >= $n) {
            return $ans;  // can't do second differences
        }

        // Make table of first differences
        $d = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $k = $iy - 2;
        for ($i = 0; $i < 5; $i++) {
            if ($k < 0 || $k + 1 >= $n) {
                $d[$i] = 0.0;
            } else {
                $d[$i] = $v[$k + 1] - $v[$k];
            }
            $k++;
        }

        // Compute second differences
        for ($i = 0; $i < 4; $i++) {
            $d[$i] = $d[$i + 1] - $d[$i];
        }

        $B = 0.25 * $p * ($p - 1.0);
        $ans += $B * ($d[1] + $d[2]);

        if ($iy + 2 >= $n) {
            return $ans;
        }

        // Compute third differences
        for ($i = 0; $i < 3; $i++) {
            $d[$i] = $d[$i + 1] - $d[$i];
        }

        $B = 2.0 * $B / 3.0;
        $ans += ($p - 0.5) * $B * $d[1];

        if ($iy - 2 < 0 || $iy + 3 > $n) {
            return $ans;
        }

        // Compute fourth differences
        for ($i = 0; $i < 2; $i++) {
            $d[$i] = $d[$i + 1] - $d[$i];
        }

        $B = 0.125 * $B * ($p + 1.0) * ($p - 2.0);
        $ans += $B * ($d[0] + $d[1]);

        return $ans;
    }

    /**
     * Find file in ephemeris path
     */
    private function findFile(string $filename, string $ephePath): ?string
    {
        // Split ephepath by path separator
        $paths = explode(PATH_SEPARATOR, $ephePath);

        foreach ($paths as $path) {
            $path = rtrim($path, '/\\');
            $fullPath = $path . DIRECTORY_SEPARATOR . $filename;

            if (file_exists($fullPath) && is_readable($fullPath)) {
                return $fullPath;
            }
        }

        return null;
    }

    /**
     * Load EOPC04 file (eop_1962_today.txt)
     * Port of load_dpsi_deps() primary file loading from sweph.c:1402-1432
     *
     * File format (space separated):
     * year month day MJD x y UT1-UTC LOD dX dY ...
     * Columns 8 and 9 (0-indexed) are dpsi and deps in milliarcseconds
     */
    private function loadEopc04(string $path): bool
    {
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return false;
        }

        $n = 0;
        $mjdPrev = 0;

        foreach ($lines as $line) {
            // Skip comment/header lines
            if (preg_match('/^\s*#/', $line) || preg_match('/^\s*$/', $line)) {
                continue;
            }

            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) < 10) {
                continue;
            }

            $year = (int)$parts[0];
            if ($year === 0) {
                continue;  // Skip header lines
            }

            $mjd = (int)$parts[3];

            // Check for one-day steps
            if ($mjdPrev > 0 && $mjd - $mjdPrev !== 1) {
                // Non-consecutive data - return with partial load
                return $n > 0;
            }

            if ($n === 0) {
                $this->tjdBeg = $mjd + self::TJDOFS;
            }

            // dpsi and deps in column 8, 9 (milliarcseconds -> arcseconds)
            $this->dpsi[$n] = (float)$parts[8] / 1000.0;
            $this->deps[$n] = (float)$parts[9] / 1000.0;

            $n++;
            $mjdPrev = $mjd;

            if ($n >= self::MAX_DATA_POINTS) {
                break;
            }
        }

        $this->tjdEnd = $mjdPrev + self::TJDOFS;

        return $n > 0;
    }

    /**
     * Load finals.all file (eop_finals.txt) for near future estimations
     * Port of load_dpsi_deps() secondary file loading from sweph.c:1433-1469
     *
     * Finals.all has fixed column format:
     * - Column 7-12: MJD
     * - Column 168-178: dpsi Bulletin B (milliarcseconds)
     * - Column 178-188: deps Bulletin B (milliarcseconds)
     * - Column 99-108: dpsi Bulletin A (milliarcseconds)
     * - Column 118-127: deps Bulletin A (milliarcseconds)
     */
    private function loadFinals(string $path): bool
    {
        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return false;
        }

        $mjdPrev = 0;
        $n = count($this->dpsi);

        foreach ($lines as $line) {
            if (strlen($line) < 180) {
                continue;
            }

            $mjd = (int)substr($line, 7, 5);

            // Skip if already covered
            if ($mjd + self::TJDOFS <= $this->tjdEnd) {
                continue;
            }

            if ($n >= self::MAX_DATA_POINTS) {
                return true;
            }

            // Check for one-day steps
            if ($mjdPrev > 0 && $mjd - $mjdPrev !== 1) {
                return true;  // Non-consecutive, stop here
            }

            // Try Bulletin B first (more accurate)
            $dpsi = (float)trim(substr($line, 168, 10));
            $deps = (float)trim(substr($line, 178, 10));

            // If Bulletin B not available, try Bulletin A
            if ($dpsi == 0) {
                $dpsi = (float)trim(substr($line, 99, 9));
                $deps = (float)trim(substr($line, 118, 9));
            }

            if ($dpsi == 0) {
                return true;  // No more data
            }

            // Convert milliarcseconds to arcseconds
            $this->dpsi[$n] = $dpsi / 1000.0;
            $this->deps[$n] = $deps / 1000.0;

            $this->tjdEnd = $mjd + self::TJDOFS;
            $n++;
            $mjdPrev = $mjd;
        }

        return true;
    }
}
