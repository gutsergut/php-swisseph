<?php

namespace Swisseph\Domain\Houses\Support;

use Swisseph\Math;

/**
 * Гварды по широтам для систем домов, которые плохо определены около полярных широт.
 */
final class LatitudeGuard
{
    /**
     * Порог широты (в радианах), выше которого некоторые системы домов плохо определены.
     * Приблизительное значение полярного круга.
     */
    public const POLAR_CIRCLE_RAD = 0.0; // будет инициализирован статически ниже

    private static float $polarCircleRad;

    /** Возвращает порог широты в радианах. */
    public static function polarCircleRad(): float
    {
        if (!isset(self::$polarCircleRad)) {
            self::$polarCircleRad = Math::degToRad(66.5);
        }
        return self::$polarCircleRad;
    }

    /** Placidus определён, если |lat| <= ~полярного круга. */
    public static function isPlacidusDefined(float $geolat_rad): bool
    {
        return abs($geolat_rad) <= self::polarCircleRad();
    }

    /** Koch определён, если |lat| <= ~полярного круга. */
    public static function isKochDefined(float $geolat_rad): bool
    {
        return abs($geolat_rad) <= self::polarCircleRad();
    }
}
