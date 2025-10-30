<?php

namespace Swisseph\Domain\Houses;

use Swisseph\Domain\Houses\Systems\Equal;
use Swisseph\Domain\Houses\Systems\EqualMc;
use Swisseph\Domain\Houses\Systems\EqualAries;
use Swisseph\Domain\Houses\Systems\Placidus;
use Swisseph\Domain\Houses\Systems\Koch;
use Swisseph\Domain\Houses\Systems\Porphyry;
use Swisseph\Domain\Houses\Systems\Campanus;
use Swisseph\Domain\Houses\Systems\Regiomontanus;
use Swisseph\Domain\Houses\Systems\WholeSign;
use Swisseph\Domain\Houses\Systems\Alcabitius;
use Swisseph\Domain\Houses\Systems\Vehlow;
use Swisseph\Domain\Houses\Systems\Morinus;
use Swisseph\Domain\Houses\Systems\Horizontal;
use Swisseph\Domain\Houses\Systems\Topocentric;
use Swisseph\Domain\Houses\Systems\Sripati;
use Swisseph\Domain\Houses\Systems\Meridian;
use Swisseph\Domain\Houses\Systems\Krusinski;
use Swisseph\Domain\Houses\Systems\Carter;
use Swisseph\Domain\Houses\Systems\PullenSD;
use Swisseph\Domain\Houses\Systems\PullenSR;
use Swisseph\Domain\Houses\Systems\Sunshine;
use Swisseph\Domain\Houses\Systems\Apc;
use Swisseph\Domain\Houses\Systems\SavardA;

/**
 * Реестр систем домов: код -> стратегия.
 * Поначалу регистрируем только Equal, дальше дополняем.
 */
final class Registry
{
    /** @var array<string, HouseSystem> */
    private array $map;

    public function __construct()
    {
        $this->map = [
            'E' => new Equal(),
            'A' => new Equal(),
            'D' => new EqualMc(),
            'N' => new EqualAries(),
            'F' => new Carter(),
            'P' => new Placidus(),
            'K' => new Koch(),
            'O' => new Porphyry(),
            'C' => new Campanus(),
            'R' => new Regiomontanus(),
            'W' => new WholeSign(),
            'B' => new Alcabitius(),
            'V' => new Vehlow(),
            'M' => new Morinus(),
            'H' => new Horizontal(),
            'T' => new Topocentric(),
            'S' => new Sripati(),
            'X' => new Meridian(),
            'U' => new Krusinski(),
            'L' => new PullenSD(),
            'Q' => new PullenSR(),
            'I' => new Sunshine(),
            'i' => new Sunshine(),
            'Y' => new Apc(),
            'J' => new SavardA(),
        ];
    }

    public function get(string $code): ?HouseSystem
    {
        // Вариант 'i' (Sunshine/Makransky) — единственный, где допустима строчная буква
        $key = ($code === 'i') ? 'i' : strtoupper($code);
        return $this->map[$key] ?? null;
    }
}
