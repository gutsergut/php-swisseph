<?php

declare(strict_types=1);

namespace Swisseph\OO;

use Swisseph\Constants;
use Swisseph\Swe\Functions\PlanetsFunctions;

/**
 * Result of planet calculation with fluent API
 *
 * @property-read float $longitude Ecliptic longitude in degrees
 * @property-read float $latitude Ecliptic latitude in degrees
 * @property-read float $distance Distance in AU
 * @property-read float $longitudeSpeed Daily speed in longitude (degrees/day)
 * @property-read float $latitudeSpeed Daily speed in latitude (degrees/day)
 * @property-read float $distanceSpeed Daily speed in distance (AU/day)
 * @property-read float $rightAscension Right ascension in degrees (if SEFLG_EQUATORIAL)
 * @property-read float $declination Declination in degrees (if SEFLG_EQUATORIAL)
 * @property-read float $x Cartesian X coordinate (if SEFLG_XYZ)
 * @property-read float $y Cartesian Y coordinate (if SEFLG_XYZ)
 * @property-read float $z Cartesian Z coordinate (if SEFLG_XYZ)
 */
final class CalcResult
{
    /**
     * @param array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float} $data
     */
    public function __construct(
        public readonly int $flag,
        public readonly array $data,
        public readonly ?string $error = null
    ) {}

    public function isSuccess(): bool
    {
        return $this->flag >= 0 && $this->error === null;
    }

    public function isError(): bool
    {
        return !$this->isSuccess();
    }

    public function getLongitude(): float
    {
        return $this->data[0];
    }

    public function getLatitude(): float
    {
        return $this->data[1];
    }

    public function getDistance(): float
    {
        return $this->data[2];
    }

    public function getLongitudeSpeed(): float
    {
        return $this->data[3];
    }

    public function getLatitudeSpeed(): float
    {
        return $this->data[4];
    }

    public function getDistanceSpeed(): float
    {
        return $this->data[5];
    }

    public function getRightAscension(): float
    {
        return $this->data[0]; // Same as longitude when SEFLG_EQUATORIAL
    }

    public function getDeclination(): float
    {
        return $this->data[1]; // Same as latitude when SEFLG_EQUATORIAL
    }

    public function getX(): float
    {
        return $this->data[0]; // When SEFLG_XYZ
    }

    public function getY(): float
    {
        return $this->data[1]; // When SEFLG_XYZ
    }

    public function getZ(): float
    {
        return $this->data[2]; // When SEFLG_XYZ
    }

    /**
     * Get all coordinates as associative array
     *
     * @return array{longitude: float, latitude: float, distance: float, longitudeSpeed: float, latitudeSpeed: float, distanceSpeed: float}
     */
    public function toArray(): array
    {
        return [
            'longitude' => $this->data[0],
            'latitude' => $this->data[1],
            'distance' => $this->data[2],
            'longitudeSpeed' => $this->data[3],
            'latitudeSpeed' => $this->data[4],
            'distanceSpeed' => $this->data[5],
        ];
    }

    /**
     * Magic getter for property-style access
     */
    public function __get(string $name): mixed
    {
        return match ($name) {
            'longitude' => $this->getLongitude(),
            'latitude' => $this->getLatitude(),
            'distance' => $this->getDistance(),
            'longitudeSpeed' => $this->getLongitudeSpeed(),
            'latitudeSpeed' => $this->getLatitudeSpeed(),
            'distanceSpeed' => $this->getDistanceSpeed(),
            'rightAscension' => $this->getRightAscension(),
            'declination' => $this->getDeclination(),
            'x' => $this->getX(),
            'y' => $this->getY(),
            'z' => $this->getZ(),
            default => throw new \InvalidArgumentException("Property {$name} does not exist")
        };
    }

    public function __isset(string $name): bool
    {
        return in_array($name, [
            'longitude', 'latitude', 'distance',
            'longitudeSpeed', 'latitudeSpeed', 'distanceSpeed',
            'rightAscension', 'declination',
            'x', 'y', 'z'
        ], true);
    }
}
