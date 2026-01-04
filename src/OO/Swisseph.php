<?php

declare(strict_types=1);

namespace Swisseph\OO;

use Swisseph\Constants;
use Swisseph\Swe\Functions\PlanetsFunctions;
use Swisseph\Swe\Functions\HousesFunctions;
use Swisseph\Swe\Functions\SiderealFunctions;

/**
 * Object-Oriented facade for Swiss Ephemeris
 *
 * Provides a fluent, modern API on top of the C-compatible functions.
 *
 * @example
 * ```php
 * use Swisseph\OO\Swisseph;
 * use Swisseph\Constants as C;
 *
 * $sweph = new Swisseph('/path/to/ephemeris');
 *
 * // Calculate Jupiter position
 * $jupiter = $sweph->planet(C::SE_JUPITER, 2451545.0);
 * if ($jupiter->isSuccess()) {
 *     echo "Longitude: " . $jupiter->longitude . "°\n";
 *     echo "Latitude: " . $jupiter->latitude . "°\n";
 *     echo "Distance: " . $jupiter->distance . " AU\n";
 * }
 *
 * // Calculate houses
 * $houses = $sweph->houses(2451545.0, 50.0, 10.0, 'P');
 * echo "Ascendant: " . $houses->ascendant . "°\n";
 * echo "MC: " . $houses->mc . "°\n";
 * echo "House 1: " . $houses->getCusp(1) . "°\n";
 * ```
 */
final class Swisseph
{
    private int $defaultFlags = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;

    public function __construct(?string $ephePath = null)
    {
        if ($ephePath !== null) {
            \swe_set_ephe_path($ephePath); // Use global function
        }
    }

    /**
     * Set default calculation flags
     */
    public function setDefaultFlags(int $flags): self
    {
        $this->defaultFlags = $flags;
        return $this;
    }

    /**
     * Calculate planet position
     *
     * @param int $planet Planet ID (Constants::SE_SUN, SE_MOON, etc.)
     * @param float $julianDay Julian Day in TT (Terrestrial Time)
     * @param int|null $flags Calculation flags (null = use defaults)
     */
    public function planet(int $planet, float $julianDay, ?int $flags = null): CalcResult
    {
        $flags ??= $this->defaultFlags;

        $xx = [];
        $serr = null;
        $iflag = PlanetsFunctions::calc($julianDay, $planet, $flags, $xx, $serr);

        return new CalcResult($iflag, $xx, $serr);
    }

    /**
     * Calculate planet position using Universal Time
     *
     * @param int $planet Planet ID
     * @param float $julianDayUt Julian Day in UT (Universal Time)
     * @param int|null $flags Calculation flags
     */
    public function planetUt(int $planet, float $julianDayUt, ?int $flags = null): CalcResult
    {
        $flags ??= $this->defaultFlags;

        $xx = [];
        $serr = null;
        $iflag = PlanetsFunctions::calcUt($julianDayUt, $planet, $flags, $xx, $serr);

        return new CalcResult($iflag, $xx, $serr);
    }

    /**
     * Calculate houses and angles
     *
     * @param float $julianDayUt Julian Day in UT
     * @param float $latitude Geographic latitude in degrees
     * @param float $longitude Geographic longitude in degrees
     * @param string $houseSystem House system code ('P', 'K', 'O', etc.)
     */
    public function houses(
        float $julianDayUt,
        float $latitude,
        float $longitude,
        string $houseSystem = 'P'
    ): HousesResult {
        $cusps = [];
        $ascmc = [];
        $flag = HousesFunctions::houses($julianDayUt, $latitude, $longitude, $houseSystem, $cusps, $ascmc);

        return new HousesResult($flag, $cusps, $ascmc);
    }

    /**
     * Calculate houses with extended options
     *
     * @param float $julianDayUt Julian Day in UT
     * @param int $flags Calculation flags
     * @param float $latitude Geographic latitude
     * @param float $longitude Geographic longitude
     * @param string $houseSystem House system code
     */
    public function housesEx(
        float $julianDayUt,
        int $flags,
        float $latitude,
        float $longitude,
        string $houseSystem = 'P'
    ): HousesResult {
        $cusps = [];
        $ascmc = [];
        $flag = HousesFunctions::housesEx($julianDayUt, $flags, $latitude, $longitude, $houseSystem, $cusps, $ascmc);

        return new HousesResult($flag, $cusps, $ascmc);
    }

    /**
     * Convert date to Julian Day
     *
     * @param int $year Year
     * @param int $month Month (1-12)
     * @param int $day Day (1-31)
     * @param float $hour Hour (0-24, with decimals)
     * @param int $calendar SE_GREG_CAL or SE_JUL_CAL
     */
    public function julianDay(
        int $year,
        int $month,
        int $day,
        float $hour = 12.0,
        int $calendar = Constants::SE_GREG_CAL
    ): float {
        return \swe_julday($year, $month, $day, $hour, $calendar);
    }

    /**
     * Convert Julian Day to date
     *
     * @param float $julianDay Julian Day
     * @param int $calendar SE_GREG_CAL or SE_JUL_CAL
     * @return array{year: int, month: int, day: int, hour: float}
     */
    public function dateFromJulianDay(float $julianDay, int $calendar = Constants::SE_GREG_CAL): array
    {
        $result = \swe_revjul($julianDay, $calendar);

        return [
            'year' => $result['y'],
            'month' => $result['m'],
            'day' => $result['d'],
            'hour' => $result['ut'],
        ];
    }

    /**
     * Set sidereal mode
     *
     * @param int $sidMode Sidereal mode ID (Constants::SE_SIDM_*)
     * @param float $t0 Reference JD (for custom ayanamsha)
     * @param float $ayanT0 Ayanamsha value at t0 (for custom)
     */
    public function setSiderealMode(int $sidMode, float $t0 = 0.0, float $ayanT0 = 0.0): self
    {
        \swe_set_sid_mode($sidMode, $t0, $ayanT0);
        return $this;
    }

    /**
     * Enable sidereal calculations
     */
    public function enableSidereal(): self
    {
        $this->defaultFlags |= Constants::SEFLG_SIDEREAL;
        return $this;
    }

    /**
     * Disable sidereal calculations
     */
    public function disableSidereal(): self
    {
        $this->defaultFlags &= ~Constants::SEFLG_SIDEREAL;
        return $this;
    }

    /**
     * Enable topocentric calculations
     *
     * @param float $longitude Observer longitude in degrees
     * @param float $latitude Observer latitude in degrees
     * @param float $altitude Observer altitude in meters
     */
    public function setTopocentric(float $longitude, float $latitude, float $altitude): self
    {
        \swe_set_topo($longitude, $latitude, $altitude); // Use global function
        $this->defaultFlags |= Constants::SEFLG_TOPOCTR;
        return $this;
    }

    /**
     * Disable topocentric calculations
     */
    public function disableTopocentric(): self
    {
        $this->defaultFlags &= ~Constants::SEFLG_TOPOCTR;
        return $this;
    }

    /**
     * Enable equatorial coordinates (RA/Dec instead of Lon/Lat)
     */
    public function enableEquatorial(): self
    {
        $this->defaultFlags |= Constants::SEFLG_EQUATORIAL;
        return $this;
    }

    /**
     * Disable equatorial coordinates
     */
    public function disableEquatorial(): self
    {
        $this->defaultFlags &= ~Constants::SEFLG_EQUATORIAL;
        return $this;
    }

    /**
     * Fluent builder: Calculate Sun
     */
    public function sun(float $julianDay): CalcResult
    {
        return $this->planet(Constants::SE_SUN, $julianDay);
    }

    /**
     * Fluent builder: Calculate Moon
     */
    public function moon(float $julianDay): CalcResult
    {
        return $this->planet(Constants::SE_MOON, $julianDay);
    }

    /**
     * Fluent builder: Calculate Mercury
     */
    public function mercury(float $julianDay): CalcResult
    {
        return $this->planet(Constants::SE_MERCURY, $julianDay);
    }

    /**
     * Fluent builder: Calculate Venus
     */
    public function venus(float $julianDay): CalcResult
    {
        return $this->planet(Constants::SE_VENUS, $julianDay);
    }

    /**
     * Fluent builder: Calculate Mars
     */
    public function mars(float $julianDay): CalcResult
    {
        return $this->planet(Constants::SE_MARS, $julianDay);
    }

    /**
     * Fluent builder: Calculate Jupiter
     */
    public function jupiter(float $julianDay): CalcResult
    {
        return $this->planet(Constants::SE_JUPITER, $julianDay);
    }

    /**
     * Fluent builder: Calculate Saturn
     */
    public function saturn(float $julianDay): CalcResult
    {
        return $this->planet(Constants::SE_SATURN, $julianDay);
    }

    /**
     * Fluent builder: Calculate Uranus
     */
    public function uranus(float $julianDay): CalcResult
    {
        return $this->planet(Constants::SE_URANUS, $julianDay);
    }

    /**
     * Fluent builder: Calculate Neptune
     */
    public function neptune(float $julianDay): CalcResult
    {
        return $this->planet(Constants::SE_NEPTUNE, $julianDay);
    }

    /**
     * Fluent builder: Calculate Pluto
     */
    public function pluto(float $julianDay): CalcResult
    {
        return $this->planet(Constants::SE_PLUTO, $julianDay);
    }

    /**
     * Fluent builder: Calculate Mean Node
     */
    public function meanNode(float $julianDay): CalcResult
    {
        return $this->planet(Constants::SE_MEAN_NODE, $julianDay);
    }

    /**
     * Fluent builder: Calculate True Node
     */
    public function trueNode(float $julianDay): CalcResult
    {
        return $this->planet(Constants::SE_TRUE_NODE, $julianDay);
    }

    /**
     * Fluent builder: Calculate Chiron
     */
    public function chiron(float $julianDay): CalcResult
    {
        return $this->planet(Constants::SE_CHIRON, $julianDay);
    }
}
