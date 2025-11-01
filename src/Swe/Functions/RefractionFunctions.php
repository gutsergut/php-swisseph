<?php

declare(strict_types=1);

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;

/**
 * Atmospheric refraction calculations.
 * Port of refraction functions from swecl.c
 */
class RefractionFunctions
{
    /**
     * Thread-local storage for lapse rate (like TLS in C)
     */
    private static float $constLapseRate = Constants::SE_LAPSE_RATE;

    /**
     * Calculate atmospheric refraction for a given altitude.
     * Port of swe_refrac() from swecl.c:2887
     *
     * Transforms true altitude to apparent altitude (SE_TRUE_TO_APP) or
     * apparent altitude to true altitude (SE_APP_TO_TRUE).
     *
     * Uses algorithm from Meeus, "Astronomische Algorithmen" (German edition), p. 114ff.
     *
     * @param float $inalt Input altitude in degrees (true or apparent, depending on calc_flag)
     * @param float $atpress Atmospheric pressure in millibars (hectopascals)
     * @param float $attemp Atmospheric temperature in degrees Celsius
     * @param int $calc_flag Either SE_TRUE_TO_APP (0) or SE_APP_TO_TRUE (1)
     * @return float Output altitude in degrees (apparent or true, depending on calc_flag)
     */
    public static function refrac(float $inalt, float $atpress, float $attemp, int $calc_flag): float
    {
        // Pressure-temperature correction factor
        $pt_factor = $atpress / 1010.0 * 283.0 / (273.0 + $attemp);

        if ($calc_flag === Constants::SE_TRUE_TO_APP) {
            // True to apparent altitude
            $trualt = $inalt;

            if ($trualt > 15) {
                // For high altitudes (> 15°)
                // Formula: R = 58.276" * tan(zenith) - 0.0824" * tan³(zenith)
                $a = tan((90 - $trualt) * Constants::DEGTORAD);
                $refr = (58.276 * $a - 0.0824 * $a * $a * $a);
                $refr *= $pt_factor / 3600.0;
            } elseif ($trualt > -5) {
                // For low altitudes (-5° to 15°)
                // The following tan is not defined for a value
                // of trualt near -5.00158 and 89.89158
                $a = $trualt + 10.3 / ($trualt + 5.11);
                if ($a + 1e-10 >= 90) {
                    $refr = 0;
                } else {
                    $refr = 1.02 / tan($a * Constants::DEGTORAD);
                }
                $refr *= $pt_factor / 60.0;
            } else {
                // Below -5°: no refraction
                $refr = 0;
            }

            $appalt = $trualt;
            if ($appalt + $refr > 0) {
                $appalt += $refr;
            }

            return $appalt;
        } else {
            // Apparent to true altitude (SE_APP_TO_TRUE)
            $appalt = $inalt;

            // The following tan is not defined for a value
            // of inalt near -4.3285 and 89.9225
            $a = $appalt + 7.31 / ($appalt + 4.4);
            if ($a + 1e-10 >= 90) {
                $refr = 0;
            } else {
                $refr = 1.00 / tan($a * Constants::DEGTORAD);
                $refr -= 0.06 * sin(14.7 * $refr + 13);
            }
            $refr *= $pt_factor / 60.0;

            $trualt = $appalt;
            if ($appalt - $refr > 0) {
                $trualt = $appalt - $refr;
            }

            return $trualt;
        }
    }

    /**
     * Extended refraction function with observer altitude and lapse rate.
     * Port of swe_refrac_extended() from swecl.c:3035
     *
     * This function is more accurate than swe_refrac():
     * - Allows correct calculation of refraction for altitudes above sea level > 0
     * - Handles negative apparent heights (below ideal horizon)
     * - Allows manipulation of the refraction constant via lapse rate
     *
     * Created thanks to and with the help of archaeoastronomer Victor Reijs.
     *
     * @param float $inalt Altitude of object above geometric horizon in degrees
     *                     (geometric horizon = plane perpendicular to gravity)
     * @param float $geoalt Altitude of observer above sea level in meters
     * @param float $atpress Atmospheric pressure in millibars (hectopascals)
     * @param float $attemp Atmospheric temperature in degrees Celsius
     * @param float $lapse_rate Temperature lapse rate (dT/dh) in degrees K per meter
     * @param int $calc_flag Either SE_TRUE_TO_APP or SE_APP_TO_TRUE
     * @param array|null $dret Optional output array of 4 doubles:
     *                         [0] = true altitude (if possible, else input value)
     *                         [1] = apparent altitude (if possible, else input value)
     *                         [2] = refraction value in degrees
     *                         [3] = dip of the horizon in degrees
     *                         The body is above the horizon if dret[0] != dret[1]
     * @return float Apparent altitude (SE_TRUE_TO_APP) or true altitude (SE_APP_TO_TRUE)
     */
    public static function refracExtended(
        float $inalt,
        float $geoalt,
        float $atpress,
        float $attemp,
        float $lapse_rate,
        int $calc_flag,
        ?array &$dret = null
    ): float {
        // Calculate dip of the horizon
        $dip = self::calcDip($geoalt, $atpress, $attemp, $lapse_rate);

        // Make sure that inalt <= 90
        $inalt_work = $inalt;
        if ($inalt_work > 90) {
            $inalt_work = 180 - $inalt_work;
        }

        if ($calc_flag === Constants::SE_TRUE_TO_APP) {
            // True to apparent altitude
            if ($inalt_work < -10) {
                if ($dret !== null) {
                    $dret[0] = $inalt_work;
                    $dret[1] = $inalt_work;
                    $dret[2] = 0;
                    $dret[3] = $dip;
                }
                return $inalt_work;
            }

            // Newton iteration to find apparent altitude
            $y = $inalt_work;
            $D = 0.0;
            $yy0 = 0;
            $D0 = $D;

            for ($i = 0; $i < 5; $i++) {
                $D = self::calcAstronomicalRefr($y, $atpress, $attemp);
                $N = $y - $yy0;
                $yy0 = $D - $D0 - $N; // denominator of derivative

                if ($N != 0.0 && $yy0 != 0.0) {
                    // Newton iteration with numerically estimated derivative
                    $N = $y - $N * ($inalt_work + $D - $y) / $yy0;
                } else {
                    // Can't do it on first pass
                    $N = $inalt_work + $D;
                }

                $yy0 = $y;
                $D0 = $D;
                $y = $N;
            }

            $refr = $D;

            if ($inalt_work + $refr < $dip) {
                if ($dret !== null) {
                    $dret[0] = $inalt_work;
                    $dret[1] = $inalt_work;
                    $dret[2] = 0;
                    $dret[3] = $dip;
                }
                return $inalt_work;
            }

            if ($dret !== null) {
                $dret[0] = $inalt_work;
                $dret[1] = $inalt_work + $refr;
                $dret[2] = $refr;
                $dret[3] = $dip;
            }

            return $inalt_work + $refr;
        } else {
            // Apparent to true altitude (SE_APP_TO_TRUE)
            $refr = self::calcAstronomicalRefr($inalt_work, $atpress, $attemp);
            $trualt = $inalt_work - $refr;

            if ($dret !== null) {
                if ($inalt_work > $dip) {
                    $dret[0] = $trualt;
                    $dret[1] = $inalt_work;
                    $dret[2] = $refr;
                    $dret[3] = $dip;
                } else {
                    $dret[0] = $inalt_work;
                    $dret[1] = $inalt_work;
                    $dret[2] = 0;
                    $dret[3] = $dip;
                }
            }

            // Apparent altitude cannot be below dip.
            // True altitude is only returned if apparent altitude is higher than dip.
            // Otherwise the apparent altitude is returned.
            // Bug fix dieter, 4 feb 2020: changed from trualt > dip to inalt >= dip
            if ($inalt_work >= $dip) {
                return $trualt;
            } else {
                return $inalt_work;
            }
        }
    }

    /**
     * Set the atmospheric lapse rate (temperature gradient).
     * Port of swe_set_lapse_rate() from swecl.c:2988
     *
     * @param float $lapse_rate Temperature lapse rate (dT/dh) in degrees K per meter
     *                          Default is SE_LAPSE_RATE = 0.0065 K/m
     */
    public static function setLapseRate(float $lapse_rate): void
    {
        self::$constLapseRate = $lapse_rate;
    }

    /**
     * Calculate the astronomical refraction.
     * Port of calc_astronomical_refr() from swecl.c:3095
     *
     * Formula by Sinclair (better for apparent altitudes < 0),
     * from Bennett, G.G. (1982), "The calculation of astronomical refraction in marine navigation",
     * Journal of Inst. Navigation, No. 35, page 255-259, especially page 256.
     *
     * @param float $inalt Apparent altitude of object in degrees
     * @param float $atpress Atmospheric pressure in millibars (hectopascals)
     * @param float $attemp Atmospheric temperature in degrees Celsius
     * @return float Refraction in degrees
     */
    private static function calcAstronomicalRefr(float $inalt, float $atpress, float $attemp): float
    {
        if ($inalt > 17.904104638432) {
            // For continuous function, instead of '>15'
            $r = 0.97 / tan($inalt * Constants::DEGTORAD);
        } else {
            $r = (34.46 + 4.23 * $inalt + 0.004 * $inalt * $inalt) /
                 (1 + 0.505 * $inalt + 0.0845 * $inalt * $inalt);
        }

        $r = (($atpress - 80) / 930 / (1 + 0.00008 * ($r + 39) * ($attemp - 10)) * $r) / 60.0;

        return $r;
    }

    /**
     * Calculate dip of the horizon.
     * Port of calc_dip() from swecl.c:3120
     *
     * Formula based on A. Thom, "Megalithic lunar observations", 1973 (page 32).
     * Conversion to metric by V. Reijs, 2000,
     * http://www.archaeocosmology.org/eng/refract.htm#Sea
     *
     * @param float $geoalt Observer altitude above sea level in meters
     * @param float $atpress Atmospheric pressure in millibars (hectopascals)
     * @param float $attemp Atmospheric temperature in degrees Celsius
     * @param float $lapse_rate Temperature lapse rate (dT/dh) in degrees K per meter
     * @return float Dip in degrees (negative value)
     */
    private static function calcDip(float $geoalt, float $atpress, float $attemp, float $lapse_rate): float
    {
        $krefr = (0.0342 + $lapse_rate) / (0.154 * 0.0238);
        $d = 1 - 1.8480 * $krefr * $atpress / (273.15 + $attemp) / (273.15 + $attemp);

        return -180.0 / M_PI * acos(1 / (1 + $geoalt / Constants::EARTH_RADIUS)) * sqrt($d);
    }
}
