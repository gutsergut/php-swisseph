<?php

declare(strict_types=1);

namespace Swisseph\OO;

/**
 * Result of houses calculation with fluent API
 *
 * @property-read array<int, float> $cusps House cusps (1-12 or 1-36 for Gauquelin)
 * @property-read float $ascendant Ascendant angle in degrees
 * @property-read float $mc Midheaven (MC) in degrees
 * @property-read float $armc ARMC (sidereal time) in degrees
 * @property-read float $vertex Vertex in degrees
 * @property-read float $equatorialAscendant Equatorial ascendant in degrees
 * @property-read float $coAscendantKoch Co-ascendant (Koch) in degrees
 * @property-read float $coAscendantMunkasey Co-ascendant (Munkasey) in degrees
 * @property-read float $polarAscendant Polar ascendant in degrees
 */
final class HousesResult
{
    /**
     * @param array<int, float> $cusps
     * @param array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float, 6: float, 7: float, 8?: float, 9?: float} $ascmc
     */
    public function __construct(
        public readonly int $flag,
        public readonly array $cusps,
        public readonly array $ascmc,
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

    public function getCusps(): array
    {
        return $this->cusps;
    }

    public function getCusp(int $house): float
    {
        if (!isset($this->cusps[$house])) {
            throw new \InvalidArgumentException("House {$house} does not exist");
        }
        return $this->cusps[$house];
    }

    public function getAscendant(): float
    {
        return $this->ascmc[0];
    }

    public function getMc(): float
    {
        return $this->ascmc[1];
    }

    public function getArmc(): float
    {
        return $this->ascmc[2];
    }

    public function getVertex(): float
    {
        return $this->ascmc[3];
    }

    public function getEquatorialAscendant(): float
    {
        return $this->ascmc[4];
    }

    public function getCoAscendantKoch(): float
    {
        return $this->ascmc[5];
    }

    public function getCoAscendantMunkasey(): float
    {
        return $this->ascmc[6];
    }

    public function getPolarAscendant(): float
    {
        return $this->ascmc[7];
    }

    /**
     * Get solar noon declination (for Sunshine houses)
     */
    public function getSunDeclinationAtNoon(): ?float
    {
        return $this->ascmc[9] ?? null;
    }

    /**
     * Get all angles as associative array
     *
     * @return array{ascendant: float, mc: float, armc: float, vertex: float, equatorialAscendant: float, coAscendantKoch: float, coAscendantMunkasey: float, polarAscendant: float}
     */
    public function getAngles(): array
    {
        return [
            'ascendant' => $this->ascmc[0],
            'mc' => $this->ascmc[1],
            'armc' => $this->ascmc[2],
            'vertex' => $this->ascmc[3],
            'equatorialAscendant' => $this->ascmc[4],
            'coAscendantKoch' => $this->ascmc[5],
            'coAscendantMunkasey' => $this->ascmc[6],
            'polarAscendant' => $this->ascmc[7],
        ];
    }

    /**
     * Magic getter for property-style access
     */
    public function __get(string $name): mixed
    {
        return match ($name) {
            'cusps' => $this->getCusps(),
            'ascendant' => $this->getAscendant(),
            'mc' => $this->getMc(),
            'armc' => $this->getArmc(),
            'vertex' => $this->getVertex(),
            'equatorialAscendant' => $this->getEquatorialAscendant(),
            'coAscendantKoch' => $this->getCoAscendantKoch(),
            'coAscendantMunkasey' => $this->getCoAscendantMunkasey(),
            'polarAscendant' => $this->getPolarAscendant(),
            default => throw new \InvalidArgumentException("Property {$name} does not exist")
        };
    }

    public function __isset(string $name): bool
    {
        return in_array($name, [
            'cusps', 'ascendant', 'mc', 'armc', 'vertex',
            'equatorialAscendant', 'coAscendantKoch',
            'coAscendantMunkasey', 'polarAscendant'
        ], true);
    }
}
