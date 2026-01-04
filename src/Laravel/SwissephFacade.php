<?php

declare(strict_types=1);

namespace Swisseph\Laravel;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Swisseph\OO\CalcResult planet(int $planet, float $julianDay, ?int $flags = null)
 * @method static \Swisseph\OO\CalcResult planetUt(int $planet, float $julianDayUt, ?int $flags = null)
 * @method static \Swisseph\OO\HousesResult houses(float $julianDayUt, float $latitude, float $longitude, string $houseSystem = 'P')
 * @method static \Swisseph\OO\HousesResult housesEx(float $julianDayUt, int $flags, float $latitude, float $longitude, string $houseSystem = 'P')
 * @method static float julianDay(int $year, int $month, int $day, float $hour = 12.0, int $calendar = \Swisseph\Constants::SE_GREG_CAL)
 * @method static array dateFromJulianDay(float $julianDay, int $calendar = \Swisseph\Constants::SE_GREG_CAL)
 * @method static \Swisseph\OO\Swisseph setSiderealMode(int $sidMode, float $t0 = 0.0, float $ayanT0 = 0.0)
 * @method static \Swisseph\OO\Swisseph enableSidereal()
 * @method static \Swisseph\OO\Swisseph disableSidereal()
 * @method static \Swisseph\OO\Swisseph setTopocentric(float $longitude, float $latitude, float $altitude)
 * @method static \Swisseph\OO\Swisseph disableTopocentric()
 * @method static \Swisseph\OO\Swisseph enableEquatorial()
 * @method static \Swisseph\OO\Swisseph disableEquatorial()
 * @method static \Swisseph\OO\CalcResult sun(float $julianDay)
 * @method static \Swisseph\OO\CalcResult moon(float $julianDay)
 * @method static \Swisseph\OO\CalcResult mercury(float $julianDay)
 * @method static \Swisseph\OO\CalcResult venus(float $julianDay)
 * @method static \Swisseph\OO\CalcResult mars(float $julianDay)
 * @method static \Swisseph\OO\CalcResult jupiter(float $julianDay)
 * @method static \Swisseph\OO\CalcResult saturn(float $julianDay)
 * @method static \Swisseph\OO\CalcResult uranus(float $julianDay)
 * @method static \Swisseph\OO\CalcResult neptune(float $julianDay)
 * @method static \Swisseph\OO\CalcResult pluto(float $julianDay)
 * @method static \Swisseph\OO\CalcResult meanNode(float $julianDay)
 * @method static \Swisseph\OO\CalcResult trueNode(float $julianDay)
 * @method static \Swisseph\OO\CalcResult chiron(float $julianDay)
 *
 * @see \Swisseph\OO\Swisseph
 */
class SwissephFacade extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'swisseph';
    }
}
