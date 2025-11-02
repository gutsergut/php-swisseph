<?php

namespace Swisseph\Swe\Functions;

use Swisseph\Math;
use Swisseph\ErrorCodes;
use Swisseph\Constants;
use Swisseph\Coordinates;

final class TransformFunctions
{
    /**
     * Rotate polar coordinates around X-axis by angle eps (degrees).
     * C API: void swe_cotrans(double *xpo, double *xpn, double eps);
     * Input: $xpo = [lon, lat, radius] in degrees; Output: $xpn = [lon, lat, radius] in degrees.
     *
     * Algorithm (matching swephlib.c:223-247):
     * 1. Convert input angles from degrees to radians
     * 2. Set radius = 1
     * 3. Convert polar → cartesian (swi_polcart)
     * 4. Rotate around X-axis (swi_coortrf)
     * 5. Convert cartesian → polar (swi_cartpol)
     * 6. Convert angles back to degrees
     * 7. Copy input radius to output
     */
    public static function cotrans(array $xpo, ?array &$xpn, float $eps_deg, ?string &$serr = null): int
    {
        $serr = null;
        if (count($xpo) < 2) {
            $serr = ErrorCodes::compose(ErrorCodes::INVALID_ARG, 'xpo size');
            return Constants::SE_ERR;
        }

        // Prepare array: [lon_rad, lat_rad, radius=1, 0, 0, 0]
        $x = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $x[0] = Math::degToRad($xpo[0]);
        $x[1] = Math::degToRad($xpo[1]);
        $x[2] = 1.0;

        // Convert polar → cartesian
        Coordinates::polCart($x, $x);

        // Rotate around X-axis
        $eps_rad = Math::degToRad($eps_deg);
        Coordinates::coortrf($x, $x, $eps_rad);

        // Convert cartesian → polar
        Coordinates::cartPol($x, $x);

        // Convert back to degrees and copy input radius
        $xpn = [
            Math::radToDeg($x[0]),
            Math::radToDeg($x[1]),
            $xpo[2] ?? 1.0
        ];

        return 0;
    }

    /**
     * Rotate rectangular coordinates and velocities around X-axis by angle eps (degrees).
     * C API: void swe_cotrans_sp(double *xpo, double *xpn, double eps);
     * Input: $xpo6 = [x,y,z, xd,yd,zd]; Output: $xpn6 = [x,y,z, xd,yd,zd].
     */
    public static function cotransSp(array $xpo6, ?array &$xpn6, float $eps_deg, ?string &$serr = null): int
    {
        $serr = null;
        if (count($xpo6) < 6) {
            $serr = ErrorCodes::compose(ErrorCodes::INVALID_ARG, 'xpo6 size');
            return Constants::SE_ERR;
        }
        $pos = [$xpo6[0], $xpo6[1], $xpo6[2]];
        $vel = [$xpo6[3], $xpo6[4], $xpo6[5]];
        $rc1 = self::cotrans($pos, $p2, $eps_deg, $e1);
        $rc2 = self::cotrans($vel, $v2, $eps_deg, $e2);
        if ($rc1 != 0 || $rc2 != 0) {
            $serr = $e1 ?? ($e2 ?? 'cotrans_sp error');
            return Constants::SE_ERR;
        }
        $xpn6 = [$p2[0], $p2[1], $p2[2], $v2[0], $v2[1], $v2[2]];
        return 0;
    }
}
