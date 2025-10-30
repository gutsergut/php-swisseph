<?php

namespace Swisseph;

final class Formatter
{
    /**
     * Convert ecliptic spherical (lonRad, latRad, dist) into xx[6] by flags.
     * Units:
     * - Default: degrees and degrees/day for angles; distance as-is; speeds must be in same units (here zeros).
     * - SEFLG_RADIANS: radians and radians/day for angles.
     * - SEFLG_EQUATORIAL: a,b represent RA,Dec (angle units per RADIANS flag), distance as-is.
     * - SEFLG_XYZ: returns rectangular [x,y,z,vx,vy,vz] (distance units as-is, speeds zeros).
     * Obliquity is computed from jd_tt when needed.
     */
    public static function eclipticSphericalToOutput(float $lonRad, float $latRad, float $dist, int $iflag, float $jd_tt): array
    {
        $isRadians = (bool)($iflag & Constants::SEFLG_RADIANS);

        // XYZ form: convert spherical to rectangular in ecliptic frame
        if ($iflag & Constants::SEFLG_XYZ) {
            $cl = cos($lonRad);
            $sl = sin($lonRad);
            $cb = cos($latRad);
            $sb = sin($latRad);
            $x = $dist * $cb * $cl;
            $y = $dist * $cb * $sl;
            $z = $dist * $sb;
            return [$x, $y, $z, 0.0, 0.0, 0.0];
        }

        $a = $lonRad;
        $b = $latRad; // angles in radians for now

        // For ecliptic angles, normalize longitude to [0, 2π)
        if (!($iflag & Constants::SEFLG_EQUATORIAL)) {
            $a = Math::normAngleRad($a);
        }

        // EQUATORIAL: convert to RA/Dec using mean obliquity
        if ($iflag & Constants::SEFLG_EQUATORIAL) {
            $eps = Obliquity::meanObliquityRadFromJdTT($jd_tt);
            [$ra, $dec, $_r] = Coordinates::eclipticToEquatorialRad($a, $b, $dist, $eps);
            $a = $ra;
            $b = $dec;
        }

        if (!$isRadians) {
            $a = Math::radToDeg($a);
            $b = Math::radToDeg($b);
            // Normalize principal angle (lon or RA) to [0, 360)
            if ($iflag & Constants::SEFLG_EQUATORIAL) {
                // RA normalization
                $a = Math::normAngleDeg($a);
            } else {
                // Ecliptic longitude normalization
                $a = Math::normAngleDeg($a);
            }
        } else {
            // In radians, ensure principal angle in [0, 2π)
            if ($iflag & Constants::SEFLG_EQUATORIAL) {
                $a = Math::normAngleRad($a);
            } else {
                $a = Math::normAngleRad($a);
            }
        }

        // Speeds are zeros for now but in correct units (deg/day or rad/day)
        $da = 0.0;
        $db = 0.0;
        $dr = 0.0;
        return [$a, $b, $dist, $da, $db, $dr];
    }
}
