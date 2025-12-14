<?php

declare(strict_types=1);

namespace Swisseph\Swe;

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\Moshier\MoshierConstants;

/**
 * Light-time corrections: deflection, aberration, bias.
 *
 * Full port of swi_deflect_light(), swi_aberr_light(), swi_bias() from swephlib.c/sweph.c
 *
 * @see sweph.c lines 3646-3890
 * @see swephlib.c lines 2205-2290
 */
final class LightTime
{
    /**
     * Astronomical Unit in meters (DE431)
     */
    public const AUNIT = 1.49597870700e+11;

    /**
     * Speed of light in m/s
     */
    public const CLIGHT = 2.99792458e+8;

    /**
     * Heliocentric gravitational constant G * M(sun) in m^3/sec^2
     */
    public const HELGRAVCONST = 1.32712440017987e+20;

    /**
     * Sun radius in radians: 959.63" converted to radians
     */
    public const SUN_RADIUS = (959.63 / 3600.0) * (M_PI / 180.0);

    /**
     * Speed integration interval for deflection
     */
    public const DEFL_SPEED_INTV = 0.0000005;

    /**
     * Speed integration interval for aberration (PLAN_SPEED_INTV)
     */
    public const PLAN_SPEED_INTV = 0.0001;

    /**
     * Effective mass factor table for light deflection near the Sun.
     * Array of [r, m_eff] where r is fraction of Sun radius.
     *
     * @see sweph.c eff_arr[] lines 5912-6019
     */
    private const EFF_ARR = [
        [1.000, 1.000000],
        [0.990, 0.999979],
        [0.980, 0.999940],
        [0.970, 0.999881],
        [0.960, 0.999811],
        [0.950, 0.999724],
        [0.940, 0.999622],
        [0.930, 0.999497],
        [0.920, 0.999354],
        [0.910, 0.999192],
        [0.900, 0.999000],
        [0.890, 0.998786],
        [0.880, 0.998535],
        [0.870, 0.998242],
        [0.860, 0.997919],
        [0.850, 0.997571],
        [0.840, 0.997198],
        [0.830, 0.996792],
        [0.820, 0.996316],
        [0.810, 0.995791],
        [0.800, 0.995226],
        [0.790, 0.994625],
        [0.780, 0.993991],
        [0.770, 0.993326],
        [0.760, 0.992598],
        [0.750, 0.991770],
        [0.740, 0.990873],
        [0.730, 0.989919],
        [0.720, 0.988912],
        [0.710, 0.987856],
        [0.700, 0.986755],
        [0.690, 0.985610],
        [0.680, 0.984398],
        [0.670, 0.982986],
        [0.660, 0.981437],
        [0.650, 0.979779],
        [0.640, 0.978024],
        [0.630, 0.976182],
        [0.620, 0.974256],
        [0.610, 0.972253],
        [0.600, 0.970174],
        [0.590, 0.968024],
        [0.580, 0.965594],
        [0.570, 0.962797],
        [0.560, 0.959758],
        [0.550, 0.956515],
        [0.540, 0.953088],
        [0.530, 0.949495],
        [0.520, 0.945741],
        [0.510, 0.941838],
        [0.500, 0.937790],
        [0.490, 0.933563],
        [0.480, 0.928668],
        [0.470, 0.923288],
        [0.460, 0.917527],
        [0.450, 0.911432],
        [0.440, 0.905035],
        [0.430, 0.898353],
        [0.420, 0.891022],
        [0.410, 0.882940],
        [0.400, 0.874312],
        [0.390, 0.865206],
        [0.380, 0.855423],
        [0.370, 0.844619],
        [0.360, 0.833074],
        [0.350, 0.820876],
        [0.340, 0.808031],
        [0.330, 0.793962],
        [0.320, 0.778931],
        [0.310, 0.763021],
        [0.300, 0.745815],
        [0.290, 0.727557],
        [0.280, 0.708234],
        [0.270, 0.687583],
        [0.260, 0.665741],
        [0.250, 0.642597],
        [0.240, 0.618252],
        [0.230, 0.592586],
        [0.220, 0.565747],
        [0.210, 0.537697],
        [0.200, 0.508554],
        [0.190, 0.478420],
        [0.180, 0.447322],
        [0.170, 0.415454],
        [0.160, 0.382892],
        [0.150, 0.349955],
        [0.140, 0.316691],
        [0.130, 0.283565],
        [0.120, 0.250431],
        [0.110, 0.218327],
        [0.100, 0.186794],
        [0.090, 0.156287],
        [0.080, 0.128421],
        [0.070, 0.102237],
        [0.060, 0.077393],
        [0.050, 0.054833],
        [0.040, 0.036361],
        [0.030, 0.020953],
        [0.020, 0.009645],
        [0.010, 0.002767],
        [0.000, 0.000000],
    ];

    /**
     * Effective mass factor for photon passing the sun at distance r (fraction of Rsun).
     * Used for light deflection when planet appears near the Sun.
     *
     * @see sweph.c meff() lines 6021-6035
     *
     * @param float $r Distance from Sun center as fraction of Sun radius
     * @return float Effective mass factor (0..1)
     */
    public static function meff(float $r): float
    {
        if ($r <= 0) {
            return 0.0;
        }
        if ($r >= 1) {
            return 1.0;
        }

        // Find interval
        $eff = self::EFF_ARR;
        $i = 0;
        while ($eff[$i][0] > $r) {
            $i++;
        }

        // Linear interpolation
        $f = ($r - $eff[$i - 1][0]) / ($eff[$i][0] - $eff[$i - 1][0]);
        $m = $eff[$i - 1][1] + $f * ($eff[$i][1] - $eff[$i - 1][1]);

        return $m;
    }

    /**
     * Helper: dot product of two 3-vectors.
     */
    private static function dotProd(array $a, array $b): float
    {
        return $a[0] * $b[0] + $a[1] * $b[1] + $a[2] * $b[2];
    }

    /**
     * Helper: sum of squares (square_sum)
     */
    private static function squareSum(array $x): float
    {
        return $x[0] * $x[0] + $x[1] * $x[1] + $x[2] * $x[2];
    }

    /**
     * Relativistic light deflection by the Sun.
     *
     * Full port of swi_deflect_light() from sweph.c lines 3742-3890.
     *
     * @param array &$xx Planet geocentric position [x,y,z,dx,dy,dz] in AU, will be modified
     * @param float $dt Light-time in days
     * @param int $iflag Calculation flags
     */
    public static function deflectLight(array &$xx, float $dt, int $iflag): void
    {
        $swed = SwedState::getInstance();
        $pedp = &$swed->pldat[MoshierConstants::SEI_EARTH];
        $psdp = &$swed->pldat[MoshierConstants::SEI_SUNBARY];
        $iephe = $pedp->iephe;

        // Earth position
        $xearth = [];
        for ($i = 0; $i <= 5; $i++) {
            $xearth[$i] = $pedp->x[$i];
        }

        // Add topocentric offset if needed
        if ($iflag & Constants::SEFLG_TOPOCTR) {
            for ($i = 0; $i <= 5; $i++) {
                $xearth[$i] += $swed->topd->xobs[$i] ?? 0.0;
            }
        }

        // U = planetbary(t-tau) - earthbary(t) = planetgeo
        $u = [$xx[0], $xx[1], $xx[2], 0.0, 0.0, 0.0];

        // Eh = earthbary(t) - sunbary(t) = earthhel
        // For Moshier, psdp->x is Sun heliocentric which is [0,0,0]
        // so e = xearth directly
        $e = [0.0, 0.0, 0.0];
        if ($iephe === Constants::SEFLG_JPLEPH || $iephe === Constants::SEFLG_SWIEPH) {
            for ($i = 0; $i <= 2; $i++) {
                $e[$i] = $xearth[$i] - ($psdp->x[$i] ?? 0.0);
            }
        } else {
            for ($i = 0; $i <= 2; $i++) {
                $e[$i] = $xearth[$i];
            }
        }

        // Q = planetbary(t-tau) - sunbary(t-tau) = 'planethel'
        // first compute sunbary(t-tau)
        $xsun = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        if ($iephe === Constants::SEFLG_JPLEPH || $iephe === Constants::SEFLG_SWIEPH) {
            for ($i = 0; $i <= 2; $i++) {
                // sufficient precision
                $xsun[$i] = ($psdp->x[$i] ?? 0.0) - $dt * ($psdp->x[$i + 3] ?? 0.0);
            }
            for ($i = 3; $i <= 5; $i++) {
                $xsun[$i] = $psdp->x[$i] ?? 0.0;
            }
        } else {
            for ($i = 0; $i <= 5; $i++) {
                $xsun[$i] = $psdp->x[$i] ?? 0.0;
            }
        }

        // Q = planet geocentric + earth - sun
        $q = [0.0, 0.0, 0.0];
        for ($i = 0; $i <= 2; $i++) {
            $q[$i] = $xx[$i] + $xearth[$i] - $xsun[$i];
        }

        $ru = sqrt(self::squareSum($u));
        $rq = sqrt(self::squareSum($q));
        $re = sqrt(self::squareSum($e));

        if ($ru < 1e-15 || $rq < 1e-15 || $re < 1e-15) {
            return; // Safety check
        }

        // Normalize
        for ($i = 0; $i <= 2; $i++) {
            $u[$i] /= $ru;
            $q[$i] /= $rq;
            $e[$i] /= $re;
        }

        $uq = self::dotProd($u, $q);
        $ue = self::dotProd($u, $e);
        $qe = self::dotProd($q, $e);

        // When planet approaches center of sun, use meff correction
        $sina = sqrt(1.0 - $ue * $ue); // sin(angle) between sun and planet
        $sin_sunr = self::SUN_RADIUS / $re; // sine of sun radius

        if ($sina < $sin_sunr) {
            $meff_fact = self::meff($sina / $sin_sunr);
        } else {
            $meff_fact = 1.0;
        }

        // Gravitational deflection formula
        $g1 = 2.0 * self::HELGRAVCONST * $meff_fact / self::CLIGHT / self::CLIGHT / self::AUNIT / $re;
        $g2 = 1.0 + $qe;

        // Compute deflected position
        $xx2 = [0.0, 0.0, 0.0];
        for ($i = 0; $i <= 2; $i++) {
            $xx2[$i] = $ru * ($u[$i] + $g1 / $g2 * ($uq * $e[$i] - $ue * $q[$i]));
        }

        // Speed correction
        if ($iflag & Constants::SEFLG_SPEED) {
            $dtsp = -self::DEFL_SPEED_INTV;

            // U at t + dtsp
            $u2 = [0.0, 0.0, 0.0];
            for ($i = 0; $i <= 2; $i++) {
                $u2[$i] = $xx[$i] - $dtsp * $xx[$i + 3];
            }

            // E at t + dtsp
            $e2 = [0.0, 0.0, 0.0];
            if ($iephe === Constants::SEFLG_JPLEPH || $iephe === Constants::SEFLG_SWIEPH) {
                for ($i = 0; $i <= 2; $i++) {
                    $e2[$i] = $xearth[$i] - ($psdp->x[$i] ?? 0.0) -
                        $dtsp * ($xearth[$i + 3] - ($psdp->x[$i + 3] ?? 0.0));
                }
            } else {
                for ($i = 0; $i <= 2; $i++) {
                    $e2[$i] = $xearth[$i] - $dtsp * $xearth[$i + 3];
                }
            }

            // Q at t + dtsp
            $q2 = [0.0, 0.0, 0.0];
            for ($i = 0; $i <= 2; $i++) {
                $q2[$i] = $u2[$i] + $xearth[$i] - $xsun[$i] -
                    $dtsp * ($xearth[$i + 3] - $xsun[$i + 3]);
            }

            $ru2 = sqrt(self::squareSum($u2));
            $rq2 = sqrt(self::squareSum($q2));
            $re2 = sqrt(self::squareSum($e2));

            if ($ru2 > 1e-15 && $rq2 > 1e-15 && $re2 > 1e-15) {
                for ($i = 0; $i <= 2; $i++) {
                    $u2[$i] /= $ru2;
                    $q2[$i] /= $rq2;
                    $e2[$i] /= $re2;
                }

                $uq2 = self::dotProd($u2, $q2);
                $ue2 = self::dotProd($u2, $e2);
                $qe2 = self::dotProd($q2, $e2);

                $sina2 = sqrt(1.0 - $ue2 * $ue2);
                $sin_sunr2 = self::SUN_RADIUS / $re2;

                if ($sina2 < $sin_sunr2) {
                    $meff_fact2 = self::meff($sina2 / $sin_sunr2);
                } else {
                    $meff_fact2 = 1.0;
                }

                $g1_2 = 2.0 * self::HELGRAVCONST * $meff_fact2 / self::CLIGHT / self::CLIGHT / self::AUNIT / $re2;
                $g2_2 = 1.0 + $qe2;

                $xx3 = [0.0, 0.0, 0.0];
                for ($i = 0; $i <= 2; $i++) {
                    $xx3[$i] = $ru2 * ($u2[$i] + $g1_2 / $g2_2 * ($uq2 * $e2[$i] - $ue2 * $q2[$i]));
                }

                // Apply speed correction
                for ($i = 0; $i <= 2; $i++) {
                    $dx1 = $xx2[$i] - $xx[$i];
                    $dx2 = $xx3[$i] - $u2[$i] * $ru2;
                    $dx1 -= $dx2;
                    $xx[$i + 3] += $dx1 / $dtsp;
                }
            }
        }

        // Apply deflected position
        for ($i = 0; $i <= 2; $i++) {
            $xx[$i] = $xx2[$i];
        }
    }

    /**
     * Annual aberration of light (internal function without speed).
     *
     * @see sweph.c aberr_light() lines 3646-3663
     *
     * @param array &$xx Planet position, will be modified
     * @param array $xe Earth position and speed
     */
    private static function aberrLightCore(array &$xx, array $xe): void
    {
        $u = [$xx[0], $xx[1], $xx[2]];
        $ru = sqrt(self::squareSum($u));

        if ($ru < 1e-15) {
            return;
        }

        // v = Earth velocity in units of c
        // v = xe[3..5] / (24 * 3600) / CLIGHT * AUNIT
        $v = [0.0, 0.0, 0.0];
        for ($i = 0; $i <= 2; $i++) {
            $v[$i] = $xe[$i + 3] / 24.0 / 3600.0 / self::CLIGHT * self::AUNIT;
        }

        $v2 = self::squareSum($v);
        $b_1 = sqrt(1.0 - $v2); // Lorentz factor

        $f1 = self::dotProd($u, $v) / $ru;
        $f2 = 1.0 + $f1 / (1.0 + $b_1);

        // Relativistic aberration formula
        for ($i = 0; $i <= 2; $i++) {
            $xx[$i] = ($b_1 * $xx[$i] + $f2 * $ru * $v[$i]) / (1.0 + $f1);
        }
    }

    /**
     * Annual aberration of light.
     *
     * Full port of swi_aberr_light() from sweph.c lines 3698-3735.
     *
     * @param array &$xx Planet position [x,y,z,dx,dy,dz] in AU, will be modified
     * @param array $xe Earth position and speed
     * @param int $iflag Calculation flags
     */
    public static function aberrLight(array &$xx, array $xe, int $iflag): void
    {
        // Save original position
        $xxs = $xx;

        // Apply aberration
        self::aberrLightCore($xx, $xe);

        // Speed correction
        if ($iflag & Constants::SEFLG_SPEED) {
            $intv = self::PLAN_SPEED_INTV;

            // Position at t - intv
            $u = [0.0, 0.0, 0.0];
            for ($i = 0; $i <= 2; $i++) {
                $u[$i] = $xxs[$i] - $intv * $xxs[$i + 3];
            }

            // v = Earth velocity in units of c
            $v = [0.0, 0.0, 0.0];
            for ($i = 0; $i <= 2; $i++) {
                $v[$i] = $xe[$i + 3] / 24.0 / 3600.0 / self::CLIGHT * self::AUNIT;
            }

            $v2 = self::squareSum($v);
            $b_1 = sqrt(1.0 - $v2);

            $ru = sqrt(self::squareSum($u));
            if ($ru > 1e-15) {
                $f1 = self::dotProd($u, $v) / $ru;
                $f2 = 1.0 + $f1 / (1.0 + $b_1);

                $xx2 = [0.0, 0.0, 0.0];
                for ($i = 0; $i <= 2; $i++) {
                    $xx2[$i] = ($b_1 * $u[$i] + $f2 * $ru * $v[$i]) / (1.0 + $f1);
                }

                // Apply speed correction
                for ($i = 0; $i <= 2; $i++) {
                    $dx1 = $xx[$i] - $xxs[$i];
                    $dx2 = $xx2[$i] - $u[$i];
                    $dx1 -= $dx2;
                    $xx[$i + 3] += $dx1 / $intv;
                }
            }
        }
    }

    /**
     * Frame bias: ICRS to J2000.
     *
     * Full port of swi_bias() from swephlib.c lines 2205-2290.
     *
     * For DE >= 403, reference frame is ICRS, not J2000 dynamical equator.
     * The difference is about 0.02".
     *
     * @param array &$x Coordinates [x,y,z,dx,dy,dz], will be modified
     * @param float $tjd Julian day
     * @param int $iflag Calculation flags
     * @param bool $backward If true, apply inverse transformation (J2000 to ICRS)
     */
    public static function bias(array &$x, float $tjd, int $iflag, bool $backward): void
    {
        $swed = SwedState::getInstance();

        // Get bias model from settings
        $bias_model = $swed->astroModels[Constants::SE_MODEL_BIAS] ?? 0;
        if ($bias_model === 0) {
            $bias_model = Constants::SEMOD_BIAS_DEFAULT;
        }

        if ($bias_model === Constants::SEMOD_BIAS_NONE) {
            return;
        }

        // Frame bias matrix IAU2006
        // Note: These are the values from swephlib.c
        if ($bias_model === Constants::SEMOD_BIAS_IAU2006) {
            $rb = [
                [+0.99999999999999412, +0.00000007078368695, -0.00000008056214212],
                [-0.00000007078368961, +0.99999999999999700, -0.00000003306427981],
                [+0.00000008056213978, +0.00000003306428553, +0.99999999999999634],
            ];
        } else {
            // Frame bias 2000 (default)
            $rb = [
                [+0.9999999999999942, +0.0000000707827948, -0.0000000805621738],
                [-0.0000000707827974, +0.9999999999999969, -0.0000000330604088],
                [+0.0000000805621715, +0.0000000330604145, +0.9999999999999962],
            ];
        }

        $xx = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];

        if ($backward) {
            // J2000 to ICRS: transpose matrix
            // Note: swi_approx_jplhor is called first in C, but we skip it for Moshier
            for ($i = 0; $i <= 2; $i++) {
                $xx[$i] = $x[0] * $rb[$i][0] +
                          $x[1] * $rb[$i][1] +
                          $x[2] * $rb[$i][2];
                if ($iflag & Constants::SEFLG_SPEED) {
                    $xx[$i + 3] = $x[3] * $rb[$i][0] +
                                  $x[4] * $rb[$i][1] +
                                  $x[5] * $rb[$i][2];
                }
            }
        } else {
            // ICRS to J2000
            for ($i = 0; $i <= 2; $i++) {
                $xx[$i] = $x[0] * $rb[0][$i] +
                          $x[1] * $rb[1][$i] +
                          $x[2] * $rb[2][$i];
                if ($iflag & Constants::SEFLG_SPEED) {
                    $xx[$i + 3] = $x[3] * $rb[0][$i] +
                                  $x[4] * $rb[1][$i] +
                                  $x[5] * $rb[2][$i];
                }
            }
            // Note: swi_approx_jplhor is called after in C, but we skip it for Moshier
        }

        // Copy result
        for ($i = 0; $i <= 2; $i++) {
            $x[$i] = $xx[$i];
        }
        if ($iflag & Constants::SEFLG_SPEED) {
            for ($i = 3; $i <= 5; $i++) {
                $x[$i] = $xx[$i];
            }
        }
    }
}
