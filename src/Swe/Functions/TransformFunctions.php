<?php

namespace Swisseph\Swe\Functions;

use Swisseph\Math;
use Swisseph\ErrorCodes;
use Swisseph\Constants;

final class TransformFunctions
{
    /**
     * Rotate rectangular coordinates around X-axis by angle eps (degrees).
     * Input: $xpo = [x, y, z]; Output: $xpn = [x, y, z].
     */
    public static function cotrans(array $xpo, float $eps_deg, ?array &$xpn = null, ?string &$serr = null): int
    {
        $serr = null;
        if (count($xpo) < 3) {
            $serr = ErrorCodes::compose(ErrorCodes::INVALID_ARG, 'xpo size');
            return Constants::SE_ERR;
        }
        $x = (float)$xpo[0];
        $y = (float)$xpo[1];
        $z = (float)$xpo[2];
        $a = Math::degToRad($eps_deg);
        $ca = cos($a);
        $sa = sin($a);
        $yp = $y * $ca - $z * $sa;
        $zp = $y * $sa + $z * $ca;
        $xpn = [$x, $yp, $zp];
        return 0;
    }

    /**
     * Rotate rectangular coordinates and velocities around X-axis by angle eps (degrees).
     * Input: $xpo6 = [x,y,z, xd,yd,zd]; Output: $xpn6 = [x,y,z, xd,yd,zd].
     */
    public static function cotransSp(array $xpo6, float $eps_deg, ?array &$xpn6 = null, ?string &$serr = null): int
    {
        $serr = null;
        if (count($xpo6) < 6) {
            $serr = ErrorCodes::compose(ErrorCodes::INVALID_ARG, 'xpo6 size');
            return Constants::SE_ERR;
        }
        $pos = [$xpo6[0], $xpo6[1], $xpo6[2]];
        $vel = [$xpo6[3], $xpo6[4], $xpo6[5]];
        $rc1 = self::cotrans($pos, $eps_deg, $p2, $e1);
        $rc2 = self::cotrans($vel, $eps_deg, $v2, $e2);
        if ($rc1 != 0 || $rc2 != 0) {
            $serr = $e1 ?? ($e2 ?? 'cotrans_sp error');
            return Constants::SE_ERR;
        }
        $xpn6 = [$p2[0], $p2[1], $p2[2], $v2[0], $v2[1], $v2[2]];
        return 0;
    }
}
