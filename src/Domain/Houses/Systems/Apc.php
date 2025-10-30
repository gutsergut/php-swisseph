<?php

namespace Swisseph\Domain\Houses\Systems;

use Swisseph\Domain\Houses\HouseSystem;
use Swisseph\Houses;
use Swisseph\Math;

/**
 * APC houses ('Y') implementation.
 * Ported from Swiss Ephemeris (apc_sector). Works in radians internally.
 */
final class Apc implements HouseSystem
{
    public function cusps(
        float $armc_rad,
        float $geolat_rad,
        float $eps_rad,
        float $asc_rad = NAN,
        float $mc_rad = NAN
    ): array {
        if (!is_finite($asc_rad) || !is_finite($mc_rad)) {
            [$asc_rad, $mc_rad] = Houses::ascMcFromArmc($armc_rad, $geolat_rad, $eps_rad);
        }
        $cusps = array_fill(0, 13, 0.0);
        // compute all cusps via apcSector (1..12)
        for ($i = 1; $i <= 12; $i++) {
            $cusps[$i] = $this->apcSector($i, $geolat_rad, $eps_rad, $armc_rad);
        }
        // Ensure basic axes exactly match provided Asc/MC
        $cusps[1] = Math::normAngleRad($asc_rad);
        // Near poles MC from apcSector may be inaccurate; enforce MC/IC per SWE
        $cusps[10] = Math::normAngleRad($mc_rad);
        $cusps[4] = Math::normAngleRad($mc_rad + Math::PI);
        return $cusps;
    }

    /**
     * Port of SWE apc_sector() from degrees to radians.
     * n: house number 1..12; ph: latitude [rad]; e: obliquity [rad]; az: armc [rad]
     * Returns ecliptic longitude of cusp in radians [0, 2π).
     */
    private function apcSector(int $n, float $ph, float $e, float $az): float
    {
        $VERY_SMALL = 1e-12; // in radians
        $pi = Math::PI;
        $tan = fn(float $x) => tan($x);
        $sin = fn(float $x) => sin($x);
        $cos = fn(float $x) => cos($x);
        $atan = fn(float $x) => atan($x);
        $atan2 = fn(float $y, float $x) => atan2($y, $x);

        // Handle polar latitudes
        if (abs(abs($ph) - $pi/2) < $VERY_SMALL) {
            $kv = 0.0;
            $dasc = 0.0;
        } else {
            // kv: ascensional difference of the ascendant
            $kv = $atan($tan($ph) * $tan($e) * $cos($az) / (1 + $tan($ph) * $tan($e) * $sin($az)));
            // dasc: declination of the ascendant
            if (abs($ph) < $VERY_SMALL) {
                // avoid singularity at equator
                $dasc = ($pi/2 - $VERY_SMALL);
                if ($ph < 0) $dasc = -$dasc;
            } else {
                $dasc = $atan($sin($kv) / $tan($ph));
            }
        }

        // below/above horizon half and k index
        $is_below_hor = ($n < 8); // includes 1 and 7
        $k = $is_below_hor ? $n - 1 : $n - 13;

        // right ascension of cusp point on APC circle
        if ($is_below_hor) {
            $a = $kv + $az + $pi/2 + $k * (($pi/2) - $kv) / 3.0;
        } else {
            $a = $kv + $az + $pi/2 + $k * (($pi/2) + $kv) / 3.0;
        }
        $a = Math::normAngleRad($a);

        // transform to ecliptic longitude
        $num = $tan($dasc) * $tan($ph) * $sin($az) + $sin($a);
        $den = $cos($e) * ($tan($dasc) * $tan($ph) * $cos($az) + $cos($a)) + $sin($e) * $tan($ph) * $sin($az - $a);
        $lon = $atan2($num, $den);
        return Math::normAngleRad($lon);
    }
}
