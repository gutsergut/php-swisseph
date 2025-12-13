<?php

declare(strict_types=1);

namespace Swisseph;

/**
 * Mean obliquity of the ecliptic calculations
 *
 * Port of swi_epsiln() from swephlib.c
 * Supports multiple precession models:
 * - IAU 1976 (Lieske et al. 1977)
 * - IAU 2000 (Capitaine et al. 2003)
 * - IAU 2006 (Capitaine et al. 2003)
 * - Newcomb (historical)
 * - Bretagnon 2003
 * - Simon 1994
 * - Williams 1994
 * - Laskar 1986
 *
 * Note: Owen 1990 and Vondrak 2011 models require additional tables
 * and will be implemented in future iterations.
 */
final class Obliquity
{
    // Model constants (from swephexp.h)
    private const SEMOD_PREC_IAU_1976 = 1;
    private const SEMOD_PREC_LASKAR_1986 = 2;
    private const SEMOD_PREC_WILL_EPS_LASK = 3;
    private const SEMOD_PREC_WILLIAMS_1994 = 4;
    private const SEMOD_PREC_SIMON_1994 = 5;
    private const SEMOD_PREC_IAU_2000 = 6;
    private const SEMOD_PREC_BRETAGNON_2003 = 7;
    private const SEMOD_PREC_IAU_2006 = 8;
    private const SEMOD_PREC_NEWCOMB = 9;
    private const SEMOD_PREC_VONDRAK_2011 = 10;
    private const SEMOD_PREC_OWEN_1990 = 11;

    // Vondrak 2011 constants
    private const NPOL_PEPS = 4;
    private const NPER_PEPS = 10;
    private const J2000 = 2451545.0;
    private const AS2R = Math::DEG_TO_RAD / 3600.0;
    private const D2PI = 2.0 * M_PI;
    private const EPS0 = 84381.406 * self::AS2R;

    // Vondrak 2011 polynomial coefficients for obliquity (pepol[NPOL_PEPS][2])
    // [0] = precession, [1] = obliquity
    private const PEPOL = [
        [+8134.017132, +84028.206305],
        [+5043.0520035, +0.3624445],
        [-0.00710733, -0.00004039],
        [+0.000000271, -0.000000110]
    ];

    // Vondrak 2011 periodic coefficients for obliquity (peper[5][NPER_PEPS])
    // [0] = periods, [1-4] = amplitudes
    private const PEPER = [
        [+409.90, +396.15, +537.22, +402.90, +417.15, +288.92, +4043.00, +306.00, +277.00, +203.00],
        [-6908.287473, -3198.706291, +1453.674527, -857.748557, +1173.231614, -156.981465, +371.836550, -216.619040, +193.691479, +11.891524],
        [+753.872780, -247.805823, +379.471484, -53.880558, -90.109153, -353.600190, -63.115353, -28.248187, +17.703387, +38.911307],
        [-2845.175469, +449.844989, -1255.915323, +886.736783, +418.887514, +997.912441, -240.979710, +76.541307, -36.788069, -170.964086],
        [-1704.720302, -862.308358, +447.832178, -889.571909, +190.402846, -56.564991, -296.222622, -75.859952, +67.473503, +3.014055]
    ];

    private const SEMOD_PREC_DEFAULT = self::SEMOD_PREC_VONDRAK_2011;
    private const SEMOD_PREC_DEFAULT_SHORT = self::SEMOD_PREC_VONDRAK_2011;

    // Time limits for short-term models (in Julian centuries from J2000)
    private const PREC_IAU_1976_CTIES = 2.0;
    private const PREC_IAU_2000_CTIES = 2.0;
    private const PREC_IAU_2006_CTIES = 2.0;

    /**
     * Calculate mean obliquity of the ecliptic
     *
     * @param float $jd Julian day (TT)
     * @param int $iflag Calculation flags (SEFLG_*)
     * @param int|null $precModel Precession model (SEMOD_PREC_*), null = auto-select
     * @param int|null $precModelShort Short-term precession model, null = auto-select
     * @return float Obliquity in radians
     */
    public static function calc(
        float $jd,
        int $iflag = 0,
        ?int $precModel = null,
        ?int $precModelShort = null
    ): float {
        $T = ($jd - 2451545.0) / 36525.0;

        // Default models
        $precModel = $precModel ?? self::SEMOD_PREC_DEFAULT;
        $precModelShort = $precModelShort ?? self::SEMOD_PREC_DEFAULT_SHORT;

        // Select model based on time range and settings
        // Short-term models have priority within their valid range
        if ($precModelShort === self::SEMOD_PREC_IAU_1976 && abs($T) <= self::PREC_IAU_1976_CTIES) {
            return self::iau1976($T);
        } elseif ($precModel === self::SEMOD_PREC_IAU_1976) {
            return self::iau1976($T);
        } elseif ($precModelShort === self::SEMOD_PREC_IAU_2000 && abs($T) <= self::PREC_IAU_2000_CTIES) {
            return self::iau2000($T);
        } elseif ($precModel === self::SEMOD_PREC_IAU_2000) {
            return self::iau2000($T);
        } elseif ($precModelShort === self::SEMOD_PREC_IAU_2006 && abs($T) <= self::PREC_IAU_2006_CTIES) {
            return self::iau2006($T);
        } elseif ($precModel === self::SEMOD_PREC_NEWCOMB) {
            return self::newcomb($jd);
        } elseif ($precModel === self::SEMOD_PREC_IAU_2006) {
            return self::iau2006($T);
        } elseif ($precModel === self::SEMOD_PREC_BRETAGNON_2003) {
            return self::bretagnon2003($T);
        } elseif ($precModel === self::SEMOD_PREC_SIMON_1994) {
            return self::simon1994($T);
        } elseif ($precModel === self::SEMOD_PREC_WILLIAMS_1994) {
            return self::williams1994($T);
        } elseif ($precModel === self::SEMOD_PREC_LASKAR_1986 || $precModel === self::SEMOD_PREC_WILL_EPS_LASK) {
            return self::laskar1986($T);
        } elseif ($precModel === self::SEMOD_PREC_OWEN_1990) {
            // TODO: Implement Owen 1990 (requires owen_eps0_coef table and get_owen_t0_icof)
            // For now, fall back to IAU 2006
            return self::iau2006($T);
        } else { // SEMOD_PREC_VONDRAK_2011
            return self::vondrak2011($jd);
        }
    }

    /**
     * Legacy method for compatibility
     *
     * @param float $jd_tt Julian day (TT)
     * @return float Obliquity in radians
     */
    public static function meanObliquityRadFromJdTT(float $jd_tt): float
    {
        return self::calc($jd_tt);
    }

    /**
     * Calculate true obliquity of the ecliptic (mean + nutation)
     *
     * @param float $jd_tt Julian day (TT)
     * @return float True obliquity in radians
     */
    public static function trueObliquityRadFromJdTT(float $jd_tt): float
    {
        $eps_mean = self::calc($jd_tt);
        [$nutLon, $nutObl] = \Swisseph\Nutation::calcIau1980($jd_tt);
        return $eps_mean + $nutObl;
    }

    /**
     * IAU 1976 model (Lieske et al. 1977)
     * Valid for ±200 years from J2000
     */
    private static function iau1976(float $T): float
    {
        $eps = (((1.813e-3 * $T - 5.9e-4) * $T - 46.8150) * $T + 84381.448) * Math::DEG_TO_RAD / 3600.0;
        return $eps;
    }

    /**
     * IAU 2000 model (Capitaine et al. 2003)
     * Valid for ±200 years from J2000
     */
    private static function iau2000(float $T): float
    {
        $eps = (((1.813e-3 * $T - 5.9e-4) * $T - 46.84024) * $T + 84381.406) * Math::DEG_TO_RAD / 3600.0;
        return $eps;
    }

    /**
     * IAU 2006 model (Capitaine et al. 2003)
     * Valid for ±200 years from J2000
     */
    private static function iau2006(float $T): float
    {
        $eps = (((((-4.34e-8 * $T - 5.76e-7) * $T + 2.0034e-3) * $T - 1.831e-4)
            * $T - 46.836769) * $T + 84381.406) * Math::DEG_TO_RAD / 3600.0;
        return $eps;
    }

    /**
     * Newcomb model (historical)
     * Uses different epoch (2396758.0 instead of J2000)
     */
    private static function newcomb(float $jd): float
    {
        $Tn = ($jd - 2396758.0) / 36525.0;
        $eps = (0.0017 * $Tn * $Tn * $Tn - 0.0085 * $Tn * $Tn - 46.837 * $Tn + 84451.68) * Math::DEG_TO_RAD / 3600.0;
        return $eps;
    }

    /**
     * Bretagnon 2003 model
     */
    private static function bretagnon2003(float $T): float
    {
        $eps = ((((((-3e-11 * $T - 2.48e-8) * $T - 5.23e-7) * $T + 1.99911e-3)
            * $T - 1.667e-4) * $T - 46.836051) * $T + 84381.40880) * Math::DEG_TO_RAD / 3600.0;
        return $eps;
    }

    /**
     * Simon 1994 model
     */
    private static function simon1994(float $T): float
    {
        $eps = (((((2.5e-8 * $T - 5.1e-7) * $T + 1.9989e-3) * $T - 1.52e-4)
            * $T - 46.80927) * $T + 84381.412) * Math::DEG_TO_RAD / 3600.0;
        return $eps;
    }

    /**
     * Williams 1994 model
     */
    private static function williams1994(float $T): float
    {
        $eps = ((((-1.0e-6 * $T + 2.0e-3) * $T - 1.74e-4)
            * $T - 46.833960) * $T + 84381.409) * Math::DEG_TO_RAD / 3600.0;
        return $eps;
    }

    /**
     * Laskar 1986 model
     * Long-term model using T/10 scaling
     */
    private static function laskar1986(float $T): float
    {
        $T = $T / 10.0;
        $eps = (2.45e-10 * $T + 5.79e-9) * $T + 2.787e-7;
        $eps = ($eps * $T + 7.12e-7) * $T - 3.905e-5;
        $eps = ($eps * $T - 2.4967e-3) * $T - 5.138e-3;
        $eps = ($eps * $T + 1.99925) * $T - 0.0155;
        $eps = ($eps * $T - 468.093) * $T + 84381.448;
        $eps *= Math::DEG_TO_RAD / 3600.0;
        return $eps;
    }

    /**
     * Vondrak 2011 model (Vondrak/Capitaine/Wallace, A&A 534, A22 (2011))
     * Long-term high precision precession and obliquity
     * Port of swi_ldp_peps from swephlib.c
     *
     * @param float $jd Julian day (TT)
     * @return float Obliquity in radians
     */
    private static function vondrak2011(float $jd): float
    {
        [$dpre, $deps] = self::ldpPeps($jd);
        return $deps;
    }

    /**
     * Long-term precession and obliquity (Vondrak 2011)
     * Port of swi_ldp_peps from swephlib.c
     *
     * @param float $jd Julian day (TT)
     * @return array{0: float, 1: float} [precession, obliquity] in radians
     */
    public static function ldpPeps(float $jd): array
    {
        $t = ($jd - self::J2000) / 36525.0;
        $p = 0.0;
        $q = 0.0;

        // Periodic terms
        for ($i = 0; $i < self::NPER_PEPS; $i++) {
            $w = self::D2PI * $t;
            $a = $w / self::PEPER[0][$i];
            $s = sin($a);
            $c = cos($a);
            $p += $c * self::PEPER[1][$i] + $s * self::PEPER[3][$i];
            $q += $c * self::PEPER[2][$i] + $s * self::PEPER[4][$i];
        }

        // Polynomial terms
        $w = 1.0;
        for ($i = 0; $i < self::NPOL_PEPS; $i++) {
            $p += self::PEPOL[$i][0] * $w;
            $q += self::PEPOL[$i][1] * $w;
            $w *= $t;
        }

        // Both to radians
        $p *= self::AS2R;
        $q *= self::AS2R;

        return [$p, $q];
    }
}
