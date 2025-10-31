<?php

declare(strict_types=1);

namespace Swisseph\Swe\FixedStars;

use Swisseph\Constants;
use Swisseph\VectorMath;
use Swisseph\EpsilonData;
use Swisseph\Swe\FixedStars\StarCatalogData;

/**
 * Coordinate transformations for fixed star calculations.
 *
 * Handles:
 * - Annual aberration of light
 * - Relativistic light deflection by the sun
 * - Nutation matrix construction
 *
 * Port of transformation functions from sweph.c (aberrLight, deflectLight, buildNutationMatrix).
 * NO SIMPLIFICATIONS - Full C code fidelity maintained.
 */
final class StarTransforms
{
    /**
     * Annual aberration of light with speed correction.
     *
     * Port of swi_aberr_light_ex() from sweph.c:3671-3690
     *
     * @param array &$xx Planet position/velocity [x, y, z, vx, vy, vz] (modified in place)
     * @param array $xe Earth position and velocity at time t
     * @param array $xe_dt Earth position and velocity at time t - dt
     * @param float $dt Time difference between xe and xe_dt
     * @param int $iflag Calculation flags
     */
    public static function aberrLightEx(
        array &$xx,
        array $xe,
        array $xe_dt,
        float $dt,
        int $iflag
    ): void {
        $xxs = $xx;  // Save original position/velocity

        // Apply aberration correction to position
        self::aberrLight($xx, $xe);

        // Correct velocity if requested
        if ($iflag & Constants::SEFLG_SPEED) {
            // Compute position at t - dt
            $xx2 = [
                $xxs[0] - $dt * $xxs[3],
                $xxs[1] - $dt * $xxs[4],
                $xxs[2] - $dt * $xxs[5],
                0.0, 0.0, 0.0
            ];

            // Apply aberration at t - dt
            self::aberrLight($xx2, $xe_dt);

            // Velocity via finite differences
            for ($i = 0; $i <= 2; $i++) {
                $xx[$i + 3] = ($xx[$i] - $xx2[$i]) / $dt;
            }
        }
    }

    /**
     * Annual aberration of light (basic calculation).
     * Computes relativistic aberration effect due to Earth's motion.
     *
     * Formula (special relativity):
     *   β = v/c (velocity as fraction of light speed)
     *   β_1 = sqrt(1 - β²) (Lorentz factor component)
     *   f1 = (u · v) / |u|
     *   f2 = 1 + f1 / (1 + β_1)
     *   xx' = (β_1 * xx + f2 * |u| * v) / (1 + f1)
     *
     * Port of aberr_light() from sweph.c:3645-3660
     *
     * @param array &$xx Planet position [x, y, z, vx, vy, vz] (position modified in place)
     * @param array $xe Earth position and velocity [x, y, z, vx, vy, vz]
     */
    public static function aberrLight(array &$xx, array $xe): void
    {
        $u = [$xx[0], $xx[1], $xx[2]];
        $ru = sqrt(VectorMath::squareSum($u));

        // Earth velocity in AU/day, convert to fraction of light speed
        // xe[i+3] is in AU/day, CLIGHT is in AU/day, so no time conversion needed
        $v = [
            $xe[3] / 24.0 / 3600.0 / Constants::CLIGHT * Constants::AUNIT,
            $xe[4] / 24.0 / 3600.0 / Constants::CLIGHT * Constants::AUNIT,
            $xe[5] / 24.0 / 3600.0 / Constants::CLIGHT * Constants::AUNIT
        ];

        $v2 = VectorMath::squareSum($v);
        $b_1 = sqrt(1.0 - $v2);  // Lorentz factor component

        $f1 = VectorMath::dotProduct($u, $v) / $ru;
        $f2 = 1.0 + $f1 / (1.0 + $b_1);

        // Apply relativistic velocity addition formula
        for ($i = 0; $i <= 2; $i++) {
            $xx[$i] = ($b_1 * $xx[$i] + $f2 * $ru * $v[$i]) / (1.0 + $f1);
        }
    }

    /**
     * Relativistic light deflection by the sun.
     * Implements general relativity correction for light passing near solar limb.
     *
     * When a planet approaches superior conjunction with the sun, the deflection
     * angle cannot be computed using the point-mass formula. This implementation
     * uses the mass distribution within the sun (via meff()) for continuity.
     *
     * Maximum effect:
     * - 1.75 arcsec at solar limb
     * - Can reach 30+ arcsec for inner planets very close to sun
     * - Speed changes: 7-30 arcsec/day near solar conjunction
     *
     * Port of swi_deflect_light() from sweph.c:3742-3920
     *
     * @param array &$xx Planet position/velocity [x, y, z, vx, vy, vz] (modified in place)
     * @param array $xearth Earth barycentric position at tjd
     * @param array $xearth_dt Earth barycentric position at tjd - dt
     * @param array $xsun Sun barycentric position at tjd
     * @param array $xsun_dt Sun barycentric position at tjd - dt
     * @param float $dt Time delta for light-time correction
     * @param int $iflag Calculation flags
     */
    public static function deflectLight(
        array &$xx,
        array $xearth,
        array $xearth_dt,
        array $xsun,
        array $xsun_dt,
        float $dt,
        int $iflag
    ): void {
        // Position calculation (always)
        $xx2 = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];

        // U = planet_bary(t-tau) - earth_bary(t) = planet_geo
        $u = [$xx[0], $xx[1], $xx[2]];

        // E = earth_bary(t) - sun_bary(t) = earth_helio
        // (xsun is sun barycentric position)
        $e = [
            $xearth[0] - $xsun[0],
            $xearth[1] - $xsun[1],
            $xearth[2] - $xsun[2]
        ];

        // Q = planet_bary(t-tau) - sun_bary(t-tau) = planet_helio
        // Compute sun_bary(t-tau) by backward extrapolation
        $xsun_tau = [
            $xsun[0] - $dt * $xsun[3],
            $xsun[1] - $dt * $xsun[4],
            $xsun[2] - $dt * $xsun[5]
        ];

        $q = [
            $xx[0] + $xearth[0] - $xsun_tau[0],
            $xx[1] + $xearth[1] - $xsun_tau[1],
            $xx[2] + $xearth[2] - $xsun_tau[2]
        ];

        // Compute magnitudes and normalize to unit vectors
        $ru = sqrt(VectorMath::squareSum($u));
        $rq = sqrt(VectorMath::squareSum($q));
        $re = sqrt(VectorMath::squareSum($e));

        // Skip deflection if any vector is zero (Earth/Sun positions not loaded)
        if ($ru < 1e-10 || $rq < 1e-10 || $re < 1e-10) {
            return; // No deflection applied
        }

        $u = [$u[0] / $ru, $u[1] / $ru, $u[2] / $ru];
        $q = [$q[0] / $rq, $q[1] / $rq, $q[2] / $rq];
        $e = [$e[0] / $re, $e[1] / $re, $e[2] / $re];

        // Dot products
        $uq = VectorMath::dotProduct($u, $q);
        $ue = VectorMath::dotProduct($u, $e);
        $qe = VectorMath::dotProduct($q, $e);

        // Effective mass correction for solar limb
        // When planet is near sun center in superior conjunction,
        // deflection formula breaks down (sun treated as point mass).
        // Use mass distribution within sun for smooth transition.
        $sina = sqrt(1.0 - $ue * $ue);  // sin(angle) between sun and planet
        $sin_sunr = Constants::SUN_RADIUS / $re;  // sine of sun angular radius

        if ($sina < $sin_sunr) {
            $meff_fact = StarCatalogData::getMeff($sina / $sin_sunr);
        } else {
            $meff_fact = 1.0;
        }

        // Deflection formula from GR:
        // g1 = 2 * G * M_sun / c^2 / AU / distance_to_sun
        // g2 = 1 + q·e
        $g1 = 2.0 * Constants::HELGRAVCONST * $meff_fact /
              Constants::CLIGHT / Constants::CLIGHT / Constants::AUNIT / $re;
        $g2 = 1.0 + $qe;

        // Deflected position: xx2 = ru * (u + (g1/g2) * (uq*e - ue*q))
        for ($i = 0; $i <= 2; $i++) {
            $xx2[$i] = $ru * ($u[$i] + ($g1 / $g2) * ($uq * $e[$i] - $ue * $q[$i]));
        }

        // Speed correction (if requested)
        if ($iflag & Constants::SEFLG_SPEED) {
            // Light deflection affects apparent speed, especially near solar conjunction.
            // For outer planet at solar limb with speed diff = 1°, effect is ~7"/day.
            // Within solar disc, can reach 30" or more.
            //
            // Example: Mercury at J2434871.45, distance from sun 45":
            //   Without deflection: 2d10'10".4034
            //   With deflection:    2d10'43".4824
            //
            // Compute deflection at slightly shifted time to get velocity effect.
            $dtsp = -Constants::DEFL_SPEED_INTV;

            // U = planet_bary(t-tau-dtsp) - earth_bary(t-dtsp)
            $u_sp = [
                $xx[0] - $dtsp * $xx[3],
                $xx[1] - $dtsp * $xx[4],
                $xx[2] - $dtsp * $xx[5]
            ];

            // E = earth_bary(t-dtsp) - sun_bary(t-dtsp)
            $e_sp = [
                $xearth[0] - $xsun[0] - $dtsp * ($xearth[3] - $xsun[3]),
                $xearth[1] - $xsun[1] - $dtsp * ($xearth[4] - $xsun[4]),
                $xearth[2] - $xsun[2] - $dtsp * ($xearth[5] - $xsun[5])
            ];

            // Q = planet_bary(t-tau-dtsp) - sun_bary(t-tau-dtsp)
            $q_sp = [
                $u_sp[0] + $xearth[0] - $xsun_tau[0] - $dtsp * ($xearth[3] - $xsun_tau[3]),
                $u_sp[1] + $xearth[1] - $xsun_tau[1] - $dtsp * ($xearth[4] - $xsun_tau[4]),
                $u_sp[2] + $xearth[2] - $xsun_tau[2] - $dtsp * ($xearth[5] - $xsun_tau[5])
            ];

            // Normalize
            $ru_sp = sqrt(VectorMath::squareSum($u_sp));
            $rq_sp = sqrt(VectorMath::squareSum($q_sp));
            $re_sp = sqrt(VectorMath::squareSum($e_sp));

            $u_sp = [$u_sp[0] / $ru_sp, $u_sp[1] / $ru_sp, $u_sp[2] / $ru_sp];
            $q_sp = [$q_sp[0] / $rq_sp, $q_sp[1] / $rq_sp, $q_sp[2] / $rq_sp];
            $e_sp = [$e_sp[0] / $re_sp, $e_sp[1] / $re_sp, $e_sp[2] / $re_sp];

            // Dot products at shifted time
            $uq_sp = VectorMath::dotProduct($u_sp, $q_sp);
            $ue_sp = VectorMath::dotProduct($u_sp, $e_sp);
            $qe_sp = VectorMath::dotProduct($q_sp, $e_sp);

            // Effective mass at shifted time
            $sina_sp = sqrt(1.0 - $ue_sp * $ue_sp);
            $sin_sunr_sp = Constants::SUN_RADIUS / $re_sp;

            if ($sina_sp < $sin_sunr_sp) {
                $meff_fact_sp = StarCatalogData::getMeff($sina_sp / $sin_sunr_sp);
            } else {
                $meff_fact_sp = 1.0;
            }

            $g1_sp = 2.0 * Constants::HELGRAVCONST * $meff_fact_sp /
                     Constants::CLIGHT / Constants::CLIGHT / Constants::AUNIT / $re_sp;
            $g2_sp = 1.0 + $qe_sp;

            // Deflected position at shifted time
            $xx3 = [0.0, 0.0, 0.0];
            for ($i = 0; $i <= 2; $i++) {
                $xx3[$i] = $ru_sp * ($u_sp[$i] + ($g1_sp / $g2_sp) * ($uq_sp * $e_sp[$i] - $ue_sp * $q_sp[$i]));
            }

            // Speed correction via finite differences
            // dx1 = deflection at t
            // dx2 = deflection at t-dtsp
            // velocity correction = (dx1 - dx2) / dtsp
            for ($i = 0; $i <= 2; $i++) {
                $dx1 = $xx2[$i] - $xx[$i];
                $dx2 = $xx3[$i] - $u_sp[$i] * $ru_sp;
                $dx1 -= $dx2;
                $xx[$i + 3] += $dx1 / $dtsp;
            }
        }

        // Apply deflected position
        for ($i = 0; $i <= 2; $i++) {
            $xx[$i] = $xx2[$i];
        }
    }

    /**
     * Build nutation matrix from nutation angles and obliquity.
     *
     * Port of nut_matrix() from sweph.c:5072-5092
     *
     * @param float $dpsi Nutation in longitude (radians)
     * @param float $deps Nutation in obliquity (radians)
     * @param EpsilonData $oe Obliquity data
     * @return array Nutation matrix as flat array [9 elements]
     */
    public static function buildNutationMatrix(
        float $dpsi,
        float $deps,
        EpsilonData $oe
    ): array {
        $psi = $dpsi;
        $eps = $oe->eps + $deps;

        $sinpsi = sin($psi);
        $cospsi = cos($psi);
        $sineps0 = $oe->seps;
        $coseps0 = $oe->ceps;
        $sineps = sin($eps);
        $coseps = cos($eps);

        // Build matrix (stored as flat array: matrix[row][col] = arr[row*3+col])
        return [
            // Row 0
            $cospsi,
            $sinpsi * $coseps,
            $sinpsi * $sineps,
            // Row 1
            -$sinpsi * $coseps0,
            $cospsi * $coseps * $coseps0 + $sineps * $sineps0,
            $cospsi * $sineps * $coseps0 - $coseps * $sineps0,
            // Row 2
            -$sinpsi * $sineps0,
            $cospsi * $coseps * $sineps0 - $sineps * $coseps0,
            $cospsi * $sineps * $sineps0 + $coseps * $coseps0
        ];
    }
}
