<?php

declare(strict_types=1);

namespace Swisseph\Nutation;

use Swisseph\Constants;
use Swisseph\Data\NutationTables1980;
use Swisseph\Math;

/**
 * IAU 1980 Nutation Model
 *
 * Port of calc_nutation_iau1980() from swephlib.c
 *
 * Calculates nutation in longitude (Δψ) and obliquity (Δε)
 * using the IAU 1980 theory with ~106 terms.
 *
 * Precision: ~0.001 arcseconds for recent epochs
 *
 * References:
 * - Wahr (1981)
 * - Seidelmann (1982)
 * - Herring (1987) corrections
 */
final class Iau1980
{
    /**
     * Calculate nutation in longitude and obliquity
     *
     * @param float $jd Julian day (TT)
     * @param bool $useHerring1987 Apply Herring 1987 corrections (default: false)
     * @return array{0: float, 1: float} [nutation_longitude_rad, nutation_obliquity_rad]
     */
    public static function calc(float $jd, bool $useHerring1987 = false): array
    {
        // Julian centuries from J2000.0
        $T = ($jd - 2451545.0) / 36525.0;
        $T2 = $T * $T;

        // Fundamental arguments (FK5), converted to radians
        // All coefficients originally in arcseconds, converted to degrees then radians

        // OM: longitude of mean ascending node of lunar orbit on ecliptic
        $OM = -6962890.539 * $T + 450160.280 + (0.008 * $T + 7.455) * $T2;
        $OM = Math::normAngleDeg($OM / 3600.0) * Math::DEG_TO_RAD;

        // MS: mean anomaly of Sun (mean longitude of Sun minus mean longitude of Sun's perigee)
        $MS = 129596581.224 * $T + 1287099.804 - (0.012 * $T + 0.577) * $T2;
        $MS = Math::normAngleDeg($MS / 3600.0) * Math::DEG_TO_RAD;

        // MM: mean anomaly of Moon
        $MM = 1717915922.633 * $T + 485866.733 + (0.064 * $T + 31.310) * $T2;
        $MM = Math::normAngleDeg($MM / 3600.0) * Math::DEG_TO_RAD;

        // FF: mean argument of latitude of Moon (F)
        $FF = 1739527263.137 * $T + 335778.877 + (0.011 * $T - 13.257) * $T2;
        $FF = Math::normAngleDeg($FF / 3600.0) * Math::DEG_TO_RAD;

        // DD: mean elongation of Moon from Sun
        $DD = 1602961601.328 * $T + 1072261.307 + (0.019 * $T - 6.891) * $T2;
        $DD = Math::normAngleDeg($DD / 3600.0) * Math::DEG_TO_RAD;        $args = [$MM, $MS, $FF, $DD, $OM];
        $ns = [3, 2, 4, 4, 2]; // number of multiples to precompute for each angle

        // Precompute sin(i*angle) and cos(i*angle) for multiple angles
        $ss = [];
        $cc = [];

        for ($k = 0; $k < 5; $k++) {
            $arg = $args[$k];
            $n = $ns[$k];
            $su = sin($arg);
            $cu = cos($arg);
            $ss[$k][0] = $su;
            $cc[$k][0] = $cu;
            $sv = 2.0 * $su * $cu;
            $cv = $cu * $cu - $su * $su;
            $ss[$k][1] = $sv;
            $cc[$k][1] = $cv;
            for ($i = 2; $i < $n; $i++) {
                $s = $su * $cv + $cu * $sv;
                $cv = $cu * $cv - $su * $sv;
                $sv = $s;
                $ss[$k][$i] = $sv;
                $cc[$k][$i] = $cv;
            }
        }

        // First terms not in table
        $C = (-0.01742 * $T - 17.1996) * $ss[4][0]; // sin(OM)
        $D = ( 0.00089 * $T +  9.2025) * $cc[4][0]; // cos(OM)

        // Iterate through nutation table
        $table = NutationTables1980::getTable();

        foreach ($table as $row) {
            // Check for end marker
            if ($row[0] === NutationTables1980::ENDMARK) {
                break;
            }

            // Skip Herring corrections if not requested
            if (!$useHerring1987 && ($row[0] === 101 || $row[0] === 102)) {
                continue;
            }

            // Combine angles: compute sin and cos of (m0*MM + m1*MS + m2*FF + m3*DD + m4*OM)
            $k1 = 0;
            $cv = 0.0;
            $sv = 0.0;

            for ($m = 0; $m < 5; $m++) {
                $j = $row[$m];
                if ($j > 100) {
                    $j = 0; // row[0] is a flag for Herring corrections
                }
                if ($j !== 0) {
                    $k = abs($j);
                    $su = $ss[$m][$k - 1]; // sin(k*angle)
                    if ($j < 0) {
                        $su = -$su;
                    }
                    $cu = $cc[$m][$k - 1]; // cos(k*angle)

                    if ($k1 === 0) { // first angle
                        $sv = $su;
                        $cv = $cu;
                        $k1 = 1;
                    } else { // combine angles
                        $sw = $su * $cv + $cu * $sv;
                        $cv = $cu * $cv - $su * $sv;
                        $sv = $sw;
                    }
                }
            }

            // Longitude coefficient (in 0.0001")
            $f = $row[5] * 0.0001;
            if ($row[6] !== 0) {
                $f += 0.00001 * $T * $row[6];
            }

            // Obliquity coefficient (in 0.0001")
            $g = $row[7] * 0.0001;
            if ($row[8] !== 0) {
                $g += 0.00001 * $T * $row[8];
            }

            // Herring corrections: coefficients in 0.00001"
            if ($row[0] >= 100) {
                $f *= 0.1;
                $g *= 0.1;
            }

            // Accumulate terms
            if ($row[0] !== 102) {
                // Normal: sin for longitude, cos for obliquity
                $C += $f * $sv;
                $D += $g * $cv;
            } else {
                // Special case (row 102): cos for longitude, sin for obliquity
                $C += $f * $cv;
                $D += $g * $sv;
            }
        }

        // Convert from arcseconds to radians
        $nutlo = [
            Math::DEG_TO_RAD * $C / 3600.0, // nutation in longitude
            Math::DEG_TO_RAD * $D / 3600.0, // nutation in obliquity
        ];

        return $nutlo;
    }
}
