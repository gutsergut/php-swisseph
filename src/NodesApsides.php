<?php

declare(strict_types=1);

namespace Swisseph;

use Swisseph\Domain\NodesApsides\LunarMeanCalculator;
use Swisseph\Domain\NodesApsides\MeanCalculator;
use Swisseph\Domain\NodesApsides\OsculatingCalculator;
use Swisseph\Domain\NodesApsides\PlanetaryElements;

/**
 * Nodes and Apsides facade - delegates to specialized calculators
 * Port of swe_nod_aps() from Swiss Ephemeris swecl.c
 */
class NodesApsides
{
    private static array $cachedNasc = [];
    private static array $cachedNdsc = [];
    private static array $cachedPeri = [];
    private static array $cachedAphe = [];
    private static bool $isTrueNodaps = false;

    public static function compute(
        float $tjdEt,
        int $ipl,
        int $iflag,
        int $method,
        ?string &$serr
    ): int {
        $doFocalPoint = (bool)($method & Constants::SE_NODBIT_FOPOINT);
        $withSpeed = (bool)($iflag & Constants::SEFLG_SPEED);

        // Default (method==0) or explicit MEAN
        if ($method === 0 || ($method & Constants::SE_NODBIT_MEAN)) {
            self::$isTrueNodaps = false;
            if ($ipl === Constants::SE_MOON) {
                LunarMeanCalculator::calculate(
                    $tjdEt,
                    self::$cachedNasc,
                    self::$cachedNdsc,
                    self::$cachedPeri,
                    self::$cachedAphe,
                    $doFocalPoint,
                    $withSpeed,
                    $iflag
                );
            } else {
                $iplx = PlanetaryElements::IPL_TO_ELEM[$ipl] ?? null;
                if ($iplx === null || $iplx === 0) {
                    $serr = sprintf('Mean nodes/apsides not supported for planet %d', $ipl);
                    return Constants::SE_ERR;
                }

                MeanCalculator::calculate(
                    $tjdEt,
                    $iplx,
                    self::$cachedNasc,
                    self::$cachedNdsc,
                    self::$cachedPeri,
                    self::$cachedAphe,
                    $doFocalPoint,
                    $withSpeed,
                    $iflag
                );
            }
            return $iflag;
        }

        if ($method & (Constants::SE_NODBIT_OSCU | Constants::SE_NODBIT_OSCU_BAR)) {
            self::$isTrueNodaps = true;
            $useBary = (bool)($method & Constants::SE_NODBIT_OSCU_BAR);
            $result = OsculatingCalculator::calculate(
                $tjdEt,
                $ipl,
                $iflag,
                self::$cachedNasc,
                self::$cachedNdsc,
                self::$cachedPeri,
                self::$cachedAphe,
                $doFocalPoint,
                $withSpeed,
                $useBary,
                $serr
            );

            if (!$result) {
                return Constants::SE_ERR;
            }
            return $iflag;
        }

        $serr = sprintf('Invalid method %d for planet %d', $method, $ipl);
        return Constants::SE_ERR;
    }

    public static function getResults(): array
    {
        return [
            self::$cachedNasc,
            self::$cachedNdsc,
            self::$cachedPeri,
            self::$cachedAphe,
        ];
    }

    public static function isTrueNodaps(): bool
    {
        return self::$isTrueNodaps;
    }
}
