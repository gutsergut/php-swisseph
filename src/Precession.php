<?php

declare(strict_types=1);

namespace Swisseph;

/**
 * Precession calculations
 * Port of swi_precess from swephlib.c
 */
class Precession
{
    // Precession model constants (from sweodef.h) - PUBLIC for external use
    public const SEMOD_PREC_IAU_1976 = 1;
    public const SEMOD_PREC_LASKAR_1986 = 2;
    public const SEMOD_PREC_WILL_EPS_LASK = 3;
    public const SEMOD_PREC_WILLIAMS_1994 = 4;
    public const SEMOD_PREC_SIMON_1994 = 5;
    public const SEMOD_PREC_IAU_2000 = 6;
    public const SEMOD_PREC_BRETAGNON_2003 = 7;
    public const SEMOD_PREC_IAU_2006 = 8;
    public const SEMOD_PREC_VONDRAK_2011 = 9;
    public const SEMOD_PREC_OWEN_1990 = 10;
    public const SEMOD_PREC_NEWCOMB = 11;

    public const SEMOD_PREC_DEFAULT = self::SEMOD_PREC_VONDRAK_2011;
    public const SEMOD_PREC_DEFAULT_SHORT = self::SEMOD_PREC_IAU_2006;

    // Century limits for short-term models
    private const PREC_IAU_1976_CTIES = 5.0;
    private const PREC_IAU_2000_CTIES = 5.0;
    private const PREC_IAU_2006_CTIES = 5.0;

    // Julian date constants
    private const J2000 = 2451545.0;
    private const B1850 = 2396758.203;
    private const J1900 = 2415020.0;

    private const DEGTORAD = M_PI / 180.0;

    /**
     * Main precession function
     * Port of swi_precess() from swephlib.c
     *
     * @param array $R Rectangular equatorial coordinates [x, y, z] (modified in place)
     * @param float $J Julian date (TT)
     * @param int $iflag Flags (e.g., SEFLG_JPLHOR)
     * @param int $direction -1 = from J2000 to J, +1 = from J to J2000
     * @param int|null $precModel Optional precession model (SEMOD_PREC_*), null = auto
     * @return int 0 on success
     */
    public static function precess(array &$R, float $J, int $iflag, int $direction, ?int $precModel = null): int
    {
        if ($J === self::J2000) {
            return 0;
        }

        $T = ($J - self::J2000) / 36525.0;

        // If model specified, use it
        if ($precModel !== null) {
            return self::precess1($R, $J, $direction, $precModel);
        }

        // Auto-select model: use IAU 2006 for short-term (<5 centuries from J2000)
        // and Vondrak 2011 for long-term
        if (abs($T) <= self::PREC_IAU_2006_CTIES) {
            return self::precess1($R, $J, $direction, self::SEMOD_PREC_IAU_2006);
        } else {
            // For long-term, we'd need precess_3 (Vondrak)
            // For now, fallback to IAU 2006
            return self::precess1($R, $J, $direction, self::SEMOD_PREC_IAU_2006);
        }
    }

    /**
     * Precession using polynomial models (IAU 1976, 2000, 2006, Bretagnon, Newcomb)
     * Port of precess_1() from swephlib.c
     *
     * @param array $R Rectangular coordinates [x, y, z]
     * @param float $J Julian date
     * @param int $direction -1 or +1
     * @param int $precMethod Model to use
     * @return int 0 on success
     */
    private static function precess1(array &$R, float $J, int $direction, int $precMethod): int
    {
        if ($J === self::J2000) {
            return 0;
        }

        $T = ($J - self::J2000) / 36525.0;
        $Z = 0.0;
        $z = 0.0;
        $TH = 0.0;

        if ($precMethod === self::SEMOD_PREC_IAU_1976) {
            $Z =  (( 0.017998 * $T + 0.30188) * $T + 2306.2181) * $T * self::DEGTORAD / 3600;
            $z =  (( 0.018203 * $T + 1.09468) * $T + 2306.2181) * $T * self::DEGTORAD / 3600;
            $TH = ((-0.041833 * $T - 0.42665) * $T + 2004.3109) * $T * self::DEGTORAD / 3600;
        } elseif ($precMethod === self::SEMOD_PREC_IAU_2000) {
            $Z =  (((((- 0.0000002 * $T - 0.0000327) * $T + 0.0179663) * $T + 0.3019015) * $T + 2306.0809506) * $T + 2.5976176) * self::DEGTORAD / 3600;
            $z =  (((((- 0.0000003 * $T - 0.000047) * $T + 0.0182237) * $T + 1.0947790) * $T + 2306.0803226) * $T - 2.5976176) * self::DEGTORAD / 3600;
            $TH = ((((-0.0000001 * $T - 0.0000601) * $T - 0.0418251) * $T - 0.4269353) * $T + 2004.1917476) * $T * self::DEGTORAD / 3600;
        } elseif ($precMethod === self::SEMOD_PREC_IAU_2006) {
            $Z =  (((((- 0.0000003173 * $T - 0.000005971) * $T + 0.01801828) * $T + 0.2988499) * $T + 2306.083227) * $T + 2.650545) * self::DEGTORAD / 3600;
            $z =  (((((- 0.0000002904 * $T - 0.000028596) * $T + 0.01826837) * $T + 1.0927348) * $T + 2306.077181) * $T - 2.650545) * self::DEGTORAD / 3600;
            $TH = ((((-0.00000011274 * $T - 0.000007089) * $T - 0.04182264) * $T - 0.4294934) * $T + 2004.191903) * $T * self::DEGTORAD / 3600;
        } elseif ($precMethod === self::SEMOD_PREC_BRETAGNON_2003) {
            $Z =  ((((((-0.00000000013 * $T - 0.0000003040) * $T - 0.000005708) * $T + 0.01801752) * $T + 0.3023262) * $T + 2306.080472) * $T + 2.72767) * self::DEGTORAD / 3600;
            $z =  ((((((-0.00000000005 * $T - 0.0000002486) * $T - 0.000028276) * $T + 0.01826676) * $T + 1.0956768) * $T + 2306.076070) * $T - 2.72767) * self::DEGTORAD / 3600;
            $TH = ((((((0.000000000009 * $T + 0.00000000036) * $T -0.0000001127) * $T - 0.000007291) * $T - 0.04182364) * $T - 0.4266980) * $T + 2004.190936) * $T * self::DEGTORAD / 3600;
        } elseif ($precMethod === self::SEMOD_PREC_NEWCOMB) {
            // Newcomb according to Kinoshita 1975
            $mills = 365242.198782; // tropical millennia
            $t1 = (self::J2000 - self::B1850) / $mills;
            $t2 = ($J - self::B1850) / $mills;
            $T_newcomb = $t2 - $t1;
            $T2 = $T_newcomb * $T_newcomb;
            $T3 = $T2 * $T_newcomb;

            $Z1 = 23035.5548 + 139.720 * $t1 + 0.069 * $t1 * $t1;
            $Z = $Z1 * $T_newcomb + (30.242 - 0.269 * $t1) * $T2 + 17.996 * $T3;
            $z = $Z1 * $T_newcomb + (109.478 - 0.387 * $t1) * $T2 + 18.324 * $T3;
            $TH = (20051.125 - 85.294 * $t1 - 0.365 * $t1 * $t1) * $T_newcomb + (-42.647 - 0.365 * $t1) * $T2 - 41.802 * $T3;

            $Z *= self::DEGTORAD / 3600.0;
            $z *= self::DEGTORAD / 3600.0;
            $TH *= self::DEGTORAD / 3600.0;
        } else {
            return 0;
        }

        // Apply rotation matrices
        $sinth = sin($TH);
        $costh = cos($TH);
        $sinZ = sin($Z);
        $cosZ = cos($Z);
        $sinz = sin($z);
        $cosz = cos($z);

        $A = $cosZ * $costh;
        $B = $sinZ * $costh;

        $x = [0.0, 0.0, 0.0];

        if ($direction < 0) { // From J2000.0 to J
            $x[0] = ($A * $cosz - $sinZ * $sinz) * $R[0]
                  - ($B * $cosz + $cosZ * $sinz) * $R[1]
                  - $sinth * $cosz * $R[2];
            $x[1] = ($A * $sinz + $sinZ * $cosz) * $R[0]
                  - ($B * $sinz - $cosZ * $cosz) * $R[1]
                  - $sinth * $sinz * $R[2];
            $x[2] = $cosZ * $sinth * $R[0]
                  - $sinZ * $sinth * $R[1]
                  + $costh * $R[2];
        } else { // From J to J2000.0
            $x[0] = ($A * $cosz - $sinZ * $sinz) * $R[0]
                  + ($A * $sinz + $sinZ * $cosz) * $R[1]
                  + $cosZ * $sinth * $R[2];
            $x[1] = - ($B * $cosz + $cosZ * $sinz) * $R[0]
                    - ($B * $sinz - $cosZ * $cosz) * $R[1]
                    - $sinZ * $sinth * $R[2];
            $x[2] = - $sinth * $cosz * $R[0]
                    - $sinth * $sinz * $R[1]
                    + $costh * $R[2];
        }

        $R[0] = $x[0];
        $R[1] = $x[1];
        $R[2] = $x[2];

        return 0;
    }

    /**
     * Convenience method: Precess position from date to J2000
     *
     * @param float $jdTT Julian date TT
     * @param array $pos Position vector [x, y, z] in equatorial coordinates of date
     * @return array Position vector [x, y, z] in J2000 equatorial coordinates
     */
    public static function precessPositionToJ2000(float $jdTT, array $pos): array
    {
        $result = [$pos[0], $pos[1], $pos[2]];
        self::precess($result, $jdTT, 0, Constants::J_TO_J2000);
        return $result;
    }

    /**
     * Precess position vector from J2000 to date
     *
     * @param float $jdTT Julian date (TT)
     * @param array $pos Position vector [x, y, z] in J2000 equatorial coordinates
     * @return array Position vector [x, y, z] in equatorial coordinates of date
     */
    public static function precessPositionFromJ2000(float $jdTT, array $pos): array
    {
        $result = [$pos[0], $pos[1], $pos[2]];
        self::precess($result, $jdTT, 0, Constants::J2000_TO_J);
        return $result;
    }

    /**
     * Apply precession to speed vector (velocity)
     * Port of swi_precess_speed() from sweph.c
     *
     * This corrects both position and velocity for precession, adding the
     * proper motion correction to the velocity (about 0.137"/day in longitude).
     *
     * @param array $xx Position and velocity [x, y, z, vx, vy, vz] (modified in place)
     * @param float $t Julian date (TT)
     * @param int $iflag Flags
     * @param int $direction Constants::J2000_TO_J or Constants::J_TO_J2000
     * @return void
     */
    public static function precessSpeed(array &$xx, float $t, int $iflag, int $direction): void
    {
        // Get obliquity based on direction
        if ($direction === Constants::J2000_TO_J) {
            $fac = 1.0;
            // Obliquity of date
            $eps = Obliquity::calc($t, $iflag, null, null);
        } else {
            $fac = -1.0;
            // Obliquity of J2000
            $eps = Obliquity::calc(self::J2000, $iflag, null, null);
        }

        $seps = sin($eps);
        $ceps = cos($eps);
        $tprec = ($t - self::J2000) / 36525.0;

        // First correct rotation: apply precession to velocity vector
        // This costs some sines and cosines, but neglect might involve an error > 1"/day
        $vel = array_slice($xx, 3, 3);
        self::precess($vel, $t, $iflag, $direction);
        $xx[3] = $vel[0];
        $xx[4] = $vel[1];
        $xx[5] = $vel[2];

        // Then add precessional proper motion (0.137"/day)
        // Convert to ecliptic coordinates
        // Matches C: swi_coortrf2(xx, xx, oe->seps, oe->ceps);
        Coordinates::coortrf2($xx, $xx, $seps, $ceps);

        // Matches C: swi_coortrf2(xx+3, xx+3, oe->seps, oe->ceps);
        // In PHP, create temporary view of velocity part
        $vel = [$xx[3], $xx[4], $xx[5]];
        Coordinates::coortrf2($vel, $vel, $seps, $ceps);
        $xx[3] = $vel[0];
        $xx[4] = $vel[1];
        $xx[5] = $vel[2];

        // Convert to spherical (modifies xx in place)
        Coordinates::cartPolSp($xx, $xx);

        // Add precession rate to longitude velocity
        // Default: 50.290966 + 0.0222226 * tprec arcsec/year
        // Convert to radians/day: / 3600 / 365.25 * DEGTORAD
        $dpre = (50.290966 + 0.0222226 * $tprec) / 3600.0 / 365.25 * (M_PI / 180.0) * $fac;
        $xx[3] += $dpre;

        // Convert back to cartesian (modifies xx in place)
        Coordinates::polCartSp($xx, $xx);

        // Convert back to equatorial
        // Matches C: swi_coortrf2(xx, xx, -oe->seps, oe->ceps);
        Coordinates::coortrf2($xx, $xx, -$seps, $ceps);

        // Matches C: swi_coortrf2(xx+3, xx+3, -oe->seps, oe->ceps);
        $vel = [$xx[3], $xx[4], $xx[5]];
        Coordinates::coortrf2($vel, $vel, -$seps, $ceps);
        $xx[3] = $vel[0];
        $xx[4] = $vel[1];
        $xx[5] = $vel[2];
    }
}
