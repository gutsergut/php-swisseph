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
        // compute all cusps via apcSector (returns degrees, convert to radians)
        for ($i = 1; $i <= 12; $i++) {
            $cusp_deg = $this->apcSector($i, $geolat_rad, $eps_rad, $armc_rad);
            $cusps[$i] = $cusp_deg * Math::DEG_TO_RAD;
        }
        // Ensure basic axes exactly match provided Asc/MC (swehouse.c:1811-1813)
        $cusps[10] = Math::normAngleRad($mc_rad);
        $cusps[4] = Math::normAngleRad($mc_rad + Math::PI);

        // Within polar circle: handle horizon/hemisphere adjustments (swehouse.c:1817-1827)
        if (abs($geolat_rad) >= (90.0 - abs($eps_rad * Math::RAD_TO_DEG)) * Math::DEG_TO_RAD) {
            $acmc = Math::diffAngleDeg($asc_rad * Math::RAD_TO_DEG, $mc_rad * Math::RAD_TO_DEG);
            if ($acmc < 0) {
                $asc_rad = Math::normAngleRad($asc_rad + Math::PI);
                for ($i = 1; $i <= 12; $i++) {
                    $cusps[$i] = Math::normAngleRad($cusps[$i] + Math::PI);
                }
            }
        }

        // Set cusp[1] after polar adjustment (C sets ac/mc separately but cusps[1] is asc)
        $cusps[1] = Math::normAngleRad($asc_rad);

        return $cusps;
    }

    /**
     * Port of SWE apc_sector().
     * n: house number 1..12; ph: latitude [rad]; e: obliquity [rad]; az: armc [rad]
     * Returns ecliptic longitude of cusp in DEGREES [0, 360).
     * Note: Works in radians internally but returns degrees (like C version).
     */
    private function apcSector(int $n, float $ph, float $e, float $az): float
    {
        // From swehouse.h:87 - VERY_SMALL = 1E-10 (degrees)
        $VERY_SMALL_DEG = 1e-10;
        $pi = Math::PI;
        $tan = fn(float $x) => tan($x);
        $sin = fn(float $x) => sin($x);
        $cos = fn(float $x) => cos($x);
        $atan = fn(float $x) => atan($x);
        $atan2 = fn(float $y, float $x) => atan2($y, $x);

        // Handle polar latitudes (using degree threshold like C, swehouse.c:788)
        if (abs(abs($ph) * Math::RAD_TO_DEG) > 90 - $VERY_SMALL_DEG) {
            $kv = 0.0;
            $dasc = 0.0;
        } else {
            // kv: ascensional difference of the ascendant
            $kv = $atan($tan($ph) * $tan($e) * $cos($az) / (1 + $tan($ph) * $tan($e) * $sin($az)));
            // dasc: declination of the ascendant
            if (abs($ph) * Math::RAD_TO_DEG < $VERY_SMALL_DEG) {
                // avoid singularity at equator (swehouse.c:793-796)
                $dasc = (90.0 - $VERY_SMALL_DEG) * Math::DEG_TO_RAD;
                if ($ph < 0) $dasc = -$dasc;
            } else {
                $dasc = $atan($sin($kv) / $tan($ph));
            }
        }

        // below/above horizon half and k index (swehouse.c:805-809)
        $is_below_hor = ($n < 8); // includes 1 and 7
        $k = $is_below_hor ? $n - 1 : $n - 13;

        // right ascension of cusp point on APC circle (swehouse.c:815-819)
        if ($is_below_hor) {
            $a = $kv + $az + $pi/2 + $k * (($pi/2) - $kv) / 3.0;
        } else {
            $a = $kv + $az + $pi/2 + $k * (($pi/2) + $kv) / 3.0;
        }
        $a = Math::normAngleRad($a);

        // transform to ecliptic longitude (swehouse.c:821-823)
        $num = $tan($dasc) * $tan($ph) * $sin($az) + $sin($a);
        $den = $cos($e) * ($tan($dasc) * $tan($ph) * $cos($az) + $cos($a)) + $sin($e) * $tan($ph) * $sin($az - $a);
        $lon = $atan2($num, $den);
        // Return in DEGREES like C version (swehouse.c:824)
        return Math::normAngleDeg($lon * Math::RAD_TO_DEG);
    }
}
