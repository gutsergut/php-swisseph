<?php

namespace Swisseph;

final class Coordinates
{
    public static function eclipticToEquatorialRad(float $lon, float $lat, float $dist, float $epsilonRad): array
    {
        $cl = cos($lon);
        $sl = sin($lon);
        $cb = cos($lat);
        $sb = sin($lat);
        $ce = cos($epsilonRad);
        $se = sin($epsilonRad);

        $x_eq = $cb * $cl;
        $y_eq = $cb * $sl * $ce - $sb * $se;
        $z_eq = $cb * $sl * $se + $sb * $ce;

        $ra = atan2($y_eq, $x_eq);
        if ($ra < 0) {
            $ra += Math::TWO_PI;
        }
        $r_xy = sqrt($x_eq * $x_eq + $y_eq * $y_eq);
        $dec = atan2($z_eq, $r_xy);
        return [$ra, $dec, $dist];
    }

    public static function equatorialToEclipticRad(float $ra, float $dec, float $dist, float $epsilonRad): array
    {
        $ca = cos($ra);
        $sa = sin($ra);
        $cd = cos($dec);
        $sd = sin($dec);
        $ce = cos($epsilonRad);
        $se = sin($epsilonRad);

        $x_ecl = $cd * $ca;
        $y_ecl = $cd * $sa * $ce + $sd * $se;
        $z_ecl = -$cd * $sa * $se + $sd * $ce;

        $lon = atan2($y_ecl, $x_ecl);
        if ($lon < 0) {
            $lon += Math::TWO_PI;
        }
        $r_xy = sqrt($x_ecl * $x_ecl + $y_ecl * $y_ecl);
        $lat = atan2($z_ecl, $r_xy);
        return [$lon, $lat, $dist];
    }

    public static function equatorialToEcliptic(float $x, float $y, float $z, float $epsilonRad): array
    {
        $ce = cos($epsilonRad);
        $se = sin($epsilonRad);
        return [$x, $y * $ce + $z * $se, -$y * $se + $z * $ce];
    }

    /**
     * Rotate cartesian coordinates around X-axis by angle eps (radians).
     * Port of swi_coortrf() from swephlib.c:279-292
     *
     * @param array $xpo Input cartesian coordinates [x, y, z]
     * @param array $xpn Output cartesian coordinates [x, y, z]
     * @param float $eps Rotation angle in radians
     */
    public static function coortrf(array $xpo, array &$xpn, float $eps): void
    {
        $sineps = sin($eps);
        $coseps = cos($eps);
        self::coortrf2($xpo, $xpn, $sineps, $coseps);
    }

    public static function coortrf2(array $xpo, array &$xpn, float $sineps, float $coseps): void
    {
        // Use temporary array to handle case when $xpo and $xpn reference the same array
        // This matches C implementation in swephlib.c swi_coortrf2() which uses double x[3]
        $x = [
            $xpo[0],
            $xpo[1] * $coseps + $xpo[2] * $sineps,
            -$xpo[1] * $sineps + $xpo[2] * $coseps,
        ];

        // Assign only first 3 elements, preserving any additional elements in $xpn
        $xpn[0] = $x[0];
        $xpn[1] = $x[1];
        $xpn[2] = $x[2];
    }

    public static function cartPol(array $x, array &$l): void
    {
        if ($x[0] === 0.0 && $x[1] === 0.0 && $x[2] === 0.0) {
            $l = [0.0, 0.0, 0.0];
            return;
        }
        $rxy = $x[0] * $x[0] + $x[1] * $x[1];
        $rad = sqrt($rxy + $x[2] * $x[2]);
        $rxy = sqrt($rxy);
        $lon = atan2($x[1], $x[0]);
        if ($lon < 0.0) {
            $lon += Math::TWO_PI;
        }
        $lat = $rxy === 0.0 ? (($x[2] >= 0) ? M_PI / 2 : -M_PI / 2) : atan($x[2] / $rxy);
        $l = [$lon, $lat, $rad];
    }

    public static function polCart(array $l, array &$x): void
    {
        $cosl1 = cos($l[1]);
        $x = [
            $l[2] * $cosl1 * cos($l[0]),
            $l[2] * $cosl1 * sin($l[0]),
            $l[2] * sin($l[1]),
        ];
    }

    public static function polCartSp(array $l, array &$x): void
    {
        $lon = $l[0];
        $lat = $l[1];
        $r = $l[2];
        $dlon = $l[3] ?? 0.0;
        $dlat = $l[4] ?? 0.0;
        $dr = $l[5] ?? 0.0;

        $cl = cos($lon);
        $sl = sin($lon);
        $cb = cos($lat);
        $sb = sin($lat);

        $x0 = $r * $cb * $cl;
        $y0 = $r * $cb * $sl;
        $z0 = $r * $sb;

        $dx = $dr * $cb * $cl - $r * $sb * $dlat * $cl - $r * $cb * $sl * $dlon;
        $dy = $dr * $cb * $sl - $r * $sb * $dlat * $sl + $r * $cb * $cl * $dlon;
        $dz = $dr * $sb + $r * $cb * $dlat;

        $x = [$x0, $y0, $z0, $dx, $dy, $dz];
    }

    /**
     * Convert from cartesian to polar coordinates with speed
     * Port of swi_cartpol_sp() from swephlib.c
     *
     * @param array $x Cartesian coordinates [x, y, z, dx, dy, dz]
     * @param array $l Output polar coordinates [lon, lat, r, dlon, dlat, dr] in radians
     */
    public static function cartPolSp(array $x, array &$l): void
    {
        // Handle zero position
        if ($x[0] === 0.0 && $x[1] === 0.0 && $x[2] === 0.0) {
            $l = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
            $l[5] = sqrt($x[3] * $x[3] + $x[4] * $x[4] + $x[5] * $x[5]);
            $speed = [$x[3], $x[4], $x[5]];
            $lSpeed = [];
            self::cartPol($speed, $lSpeed);
            $l[0] = $lSpeed[0];
            $l[1] = $lSpeed[1];
            return;
        }

        // Handle zero speed
        if ($x[3] === 0.0 && $x[4] === 0.0 && $x[5] === 0.0) {
            $l = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
            $pos = [$x[0], $x[1], $x[2]];
            $lPos = [];
            self::cartPol($pos, $lPos);
            $l[0] = $lPos[0];
            $l[1] = $lPos[1];
            $l[2] = $lPos[2];
            return;
        }

        // Position
        $rxy = $x[0] * $x[0] + $x[1] * $x[1];
        $r = sqrt($rxy + $x[2] * $x[2]);
        $rxy = sqrt($rxy);
        $lon = atan2($x[1], $x[0]);
        if ($lon < 0.0) {
            $lon += Math::TWO_PI;
        }
        $lat = atan($x[2] / $rxy);

        // Speed
        $coslon = $x[0] / $rxy;
        $sinlon = $x[1] / $rxy;
        $coslat = $rxy / $r;
        $sinlat = $x[2] / $r;

        $xx3 = $x[3] * $coslon + $x[4] * $sinlon;
        $xx4 = -$x[3] * $sinlon + $x[4] * $coslon;
        $dlon = $xx4 / $rxy;

        $xx4 = -$sinlat * $xx3 + $coslat * $x[5];
        $xx5 = $coslat * $xx3 + $sinlat * $x[5];
        $dlat = $xx4 / $r;
        $dr = $xx5;

        $l = [$lon, $lat, $r, $dlon, $dlat, $dr];
    }

    /**
     * Apply nutation transformation to coordinates
     * Port of swi_nutate() from sweph.c:3591
     *
     * Transforms coordinates by applying nutation rotation matrix.
     * If backward is true, applies inverse transformation.
     *
     * @param array $xx Input/output coordinates [x, y, z, dx, dy, dz]
     * @param array $nutMatrix Nutation matrix (3x3 as flat array [9])
     * @param array $nutMatrixVelocity Nutation velocity matrix (3x3 as flat array [9]) for speed correction
     * @param int $iflag Flags (SEFLG_SPEED)
     * @param bool $backward If true, transpose matrix (inverse transformation)
     */
    public static function nutate(
        array &$xx,
        array $nutMatrix,
        array $nutMatrixVelocity,
        int $iflag,
        bool $backward = false
    ): void {
        $x = [0.0, 0.0, 0.0];
        $xv = [0.0, 0.0, 0.0];

        // Apply nutation matrix to position
        for ($i = 0; $i <= 2; $i++) {
            if ($backward) {
                // Transpose: matrix[i][j] → matrix[j][i]
                // Flat array: matrix[row*3 + col] → matrix[col*3 + row]
                $x[$i] = $xx[0] * $nutMatrix[$i * 3 + 0] +
                         $xx[1] * $nutMatrix[$i * 3 + 1] +
                         $xx[2] * $nutMatrix[$i * 3 + 2];
            } else {
                // Normal: matrix[0][i], matrix[1][i], matrix[2][i]
                // Flat array: matrix[0*3 + i], matrix[1*3 + i], matrix[2*3 + i]
                $x[$i] = $xx[0] * $nutMatrix[0 * 3 + $i] +
                         $xx[1] * $nutMatrix[1 * 3 + $i] +
                         $xx[2] * $nutMatrix[2 * 3 + $i];
            }
        }

        if ($iflag & Constants::SEFLG_SPEED) {
            // Apply nutation matrix to speed
            for ($i = 0; $i <= 2; $i++) {
                if ($backward) {
                    $x[$i + 3] = $xx[3] * $nutMatrix[$i * 3 + 0] +
                                 $xx[4] * $nutMatrix[$i * 3 + 1] +
                                 $xx[5] * $nutMatrix[$i * 3 + 2];
                } else {
                    $x[$i + 3] = $xx[3] * $nutMatrix[0 * 3 + $i] +
                                 $xx[4] * $nutMatrix[1 * 3 + $i] +
                                 $xx[5] * $nutMatrix[2 * 3 + $i];
                }
            }

            // Apparent motion due to change of nutation during day (makes 0.01" difference)
            // NUT_SPEED_INTV = 0.0001 (from C code)
            if (!empty($nutMatrixVelocity)) {
                $nutSpeedIntv = 0.0001;
                for ($i = 0; $i <= 2; $i++) {
                    if ($backward) {
                        $xv[$i] = $xx[0] * $nutMatrixVelocity[$i * 3 + 0] +
                                  $xx[1] * $nutMatrixVelocity[$i * 3 + 1] +
                                  $xx[2] * $nutMatrixVelocity[$i * 3 + 2];
                    } else {
                        $xv[$i] = $xx[0] * $nutMatrixVelocity[0 * 3 + $i] +
                                  $xx[1] * $nutMatrixVelocity[1 * 3 + $i] +
                                  $xx[2] * $nutMatrixVelocity[2 * 3 + $i];
                    }
                    // New speed = rotated speed + change in position due to nutation velocity
                    $xx[3 + $i] = $x[3 + $i] + ($x[$i] - $xv[$i]) / $nutSpeedIntv;
                }
            } else {
                for ($i = 0; $i <= 2; $i++) {
                    $xx[3 + $i] = $x[3 + $i];
                }
            }
        }

        // Update position
        for ($i = 0; $i <= 2; $i++) {
            $xx[$i] = $x[$i];
        }
    }

    /**
     * Convert polar coordinates to cartesian (functional alias for polCart)
     *
     * @param array $polar [longitude_rad, latitude_rad, radius, ...]
     * @return array [x, y, z, ...]
     */
    public static function polarToCartesian(array $polar, bool $withSpeed = false): array
    {
        $result = [];
        if ($withSpeed) {
            self::polCartSp($polar, $result);
        } else {
            self::polCart($polar, $result);
        }
        return $result;
    }

    /**
     * Convert cartesian coordinates to polar (functional alias for cartPol)
     *
     * @param array $cartesian [x, y, z, ...]
     * @return array [longitude_rad, latitude_rad, radius, ...]
     */
    public static function cartesianToPolar(array $cartesian): array
    {
        $result = [];
        self::cartPol($cartesian, $result);
        return $result;
    }

    /**
     * Rotate vector around specified axis
     * Port of swi_coortrf2() from swephlib.c
     *
     * @param array $vec Input vector [x, y, z, ...]
     * @param float $angle Rotation angle in radians
     * @param int $axis Rotation axis (0=X, 1=Y, 2=Z)
     * @return array Rotated vector
     */
    public static function rotateVector(array $vec, float $angle, int $axis = 0): array
    {
        $sineps = sin($angle);
        $coseps = cos($angle);
        $result = $vec; // Copy original array to preserve extra elements

        if ($axis === 0) {
            // Rotation around X axis (ecliptic <-> equator)
            $result[0] = $vec[0];
            $result[1] = $vec[1] * $coseps + $vec[2] * $sineps;
            $result[2] = -$vec[1] * $sineps + $vec[2] * $coseps;
        } elseif ($axis === 1) {
            // Rotation around Y axis
            $result[0] = $vec[0] * $coseps - $vec[2] * $sineps;
            $result[1] = $vec[1];
            $result[2] = $vec[0] * $sineps + $vec[2] * $coseps;
        } else {
            // Rotation around Z axis (default)
            $result[0] = $vec[0] * $coseps + $vec[1] * $sineps;
            $result[1] = -$vec[0] * $sineps + $vec[1] * $coseps;
            $result[2] = $vec[2];
        }

        return $result;
    }

    /**
     * Rotate coordinates (in place) using precomputed sin/cos.
     * Port of swi_coortrf2() from swephlib.c:299-309
     *
     * @param array &$xpn Coordinates to rotate [x, y, z, ...] (modified in place)
     * @param float $sineps Sine of rotation angle
     * @param float $coseps Cosine of rotation angle
     */
    public static function rotate(array &$xpn, float $sineps, float $coseps): void
    {
        $x0 = $xpn[0];
        $x1 = $xpn[1] * $coseps + $xpn[2] * $sineps;
        $x2 = -$xpn[1] * $sineps + $xpn[2] * $coseps;
        $xpn[0] = $x0;
        $xpn[1] = $x1;
        $xpn[2] = $x2;
    }

    /**
     * Wrapper for swi_deflect_light() - light deflection by the sun (3-param version)
     * Port of swi_deflect_light() from sweph.c:3742-3920
     *
     * Automatically retrieves Earth and Sun positions from SwedState.
     *
     * @param array &$xx Planet position/velocity [x, y, z, vx, vy, vz] (modified in place)
     * @param float $dt Time delta for light-time correction
     * @param int $iflag Calculation flags
     */
    public static function deflectLight(array &$xx, float $dt, int $iflag): void
    {
        $swed = \Swisseph\SwephFile\SwedState::getInstance();
        $pedp = $swed->pldat[\Swisseph\SwephFile\SwephConstants::SEI_EARTH] ?? null;
        $psdp = $swed->pldat[\Swisseph\SwephFile\SwephConstants::SEI_SUNBARY] ?? null;

        if ($pedp === null || $psdp === null) {
            // If Earth/Sun data not available, skip deflection
            return;
        }

        $xearth = $pedp->x;
        $xsun = $psdp->x;

        // Apply topocentric correction if needed
        if (($iflag & Constants::SEFLG_TOPOCTR) && isset($swed->topd->xobs)) {
            for ($i = 0; $i <= 5; $i++) {
                $xearth[$i] += $swed->topd->xobs[$i];
            }
        }

        // For speed calculation, need xearth and xsun at t - dt
        $xearth_dt = $xearth; // Simplified: assume Earth doesn't move much in dt
        $xsun_dt = [
            $xsun[0] - $dt * $xsun[3],
            $xsun[1] - $dt * $xsun[4],
            $xsun[2] - $dt * $xsun[5],
            $xsun[3],
            $xsun[4],
            $xsun[5]
        ];

        // Call the full implementation
        \Swisseph\Swe\FixedStars\StarTransforms::deflectLight(
            $xx,
            $xearth,
            $xearth_dt,
            $xsun,
            $xsun_dt,
            $dt,
            $iflag
        );
    }

    /**
     * Wrapper for swi_aberr_light() - annual aberration of light (2-param version)
     * Port of swi_aberr_light() from sweph.c:3692-3718
     *
     * Automatically retrieves Earth position from SwedState.
     *
     * @param array &$xx Planet position/velocity [x, y, z, vx, vy, vz] (modified in place)
     * @param array $xxctr Center planet position (usually for velocity correction)
     * @param int $iflag Calculation flags
     */
    public static function aberrLight(array &$xx, array $xxctr, int $iflag): void
    {
        $swed = \Swisseph\SwephFile\SwedState::getInstance();
        $pedp = $swed->pldat[\Swisseph\SwephFile\SwephConstants::SEI_EARTH] ?? null;

        if ($pedp === null) {
            // If Earth data not available, skip aberration
            return;
        }

        $xearth = $pedp->x;

        // Apply topocentric correction if needed
        if (($iflag & \Swisseph\Constants::SEFLG_TOPOCTR) && isset($swed->topd->xobs)) {
            for ($i = 0; $i <= 5; $i++) {
                $xearth[$i] += $swed->topd->xobs[$i];
            }
        }

        // Call the full implementation
        \Swisseph\Swe\FixedStars\StarTransforms::aberrLight($xx, $xearth);
    }
}
















































































