<?php

namespace Swisseph;

final class PlanetHelper
{
    /**
     * Approximate Earth heliocentric rectangular ecliptic coordinates (AU)
     * using Sun geocentric spherical ecliptic as proxy: Earth_helio ~= -Sun_geo_rect
     *
     * @return array{0: float, 1: float, 2: float} [ex, ey, ez]
     */
    public static function earthHeliocRectApproxFromSun(float $jd_tt): array
    {
        [$slon, $slat, $sdist] = Sun::eclipticLonLatDist($jd_tt);
        $csl = \cos($slon);
        $ssl = \sin($slon);
        $csb = \cos($slat);
        $ssb = \sin($slat);
        $sx = $sdist * $csb * $csl;
        $sy = $sdist * $csb * $ssl;
        $sz = $sdist * $ssb; // Sun geocentric rect
        return [-$sx, -$sy, -$sz];
    }

    /**
     * Compute geocentric rectangular vector for a planet given its heliocentric rect function.
     *
     * @param float $jd_tt
     * @param callable $planetHelioRect function(float $jd_tt): array{x:float,y:float,z:float}
     * @return array{gx: float, gy: float, gz: float, dist: float, lon: float, lat: float}
     */
    public static function geocentricRectFromHeliocentric(float $jd_tt, callable $planetHelioRect): array
    {
        [$px, $py, $pz] = $planetHelioRect($jd_tt);
        [$ex, $ey, $ez] = self::earthHeliocRectApproxFromSun($jd_tt);
        $gx = $px - $ex;
        $gy = $py - $ey;
        $gz = $pz - $ez;
        $dist = \sqrt($gx * $gx + $gy * $gy + $gz * $gz);
        $lon = \atan2($gy, $gx);
        if ($lon < 0) {
            $lon += Math::TWO_PI;
        }
        $lat = \atan2($gz, \sqrt($gx * $gx + $gy * $gy));
        return [$gx, $gy, $gz, $dist, $lon, $lat];
    }

    /**
     * Produce output array xx for a planet from its heliocentric rect function, including speeds if requested.
     *
     * @param float $jd_tt
     * @param int $iflag
     * @param callable $planetHelioRect function(float $jd_tt): array{x:float,y:float,z:float}
     * @return array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float}
     */
    public static function outputForPlanetHeliocentric(float $jd_tt, int $iflag, callable $planetHelioRect): array
    {
        // Check if heliocentric coordinates requested
        $isHeliocentric = (bool)($iflag & Constants::SEFLG_HELCTR);

        if ($isHeliocentric) {
            // Heliocentric mode: use planet position directly
            [$px, $py, $pz] = $planetHelioRect($jd_tt);
            $gx = $px;
            $gy = $py;
            $gz = $pz;
        } else {
            // Geocentric mode: subtract Earth position
            [$gx, $gy, $gz, $dist, $lon, $lat] = self::geocentricRectFromHeliocentric($jd_tt, $planetHelioRect);
        }

        // Calculate spherical coordinates
        $dist = \sqrt($gx * $gx + $gy * $gy + $gz * $gz);
        $lon = \atan2($gy, $gx);
        if ($lon < 0) {
            $lon += Math::TWO_PI;
        }
        $lat = \atan2($gz, \sqrt($gx * $gx + $gy * $gy));

        $xx = Formatter::eclipticSphericalToOutput($lon, $lat, $dist, $iflag, $jd_tt);

        if (($iflag & Constants::SEFLG_SPEED) === 0) {
            return $xx;
        }

        $dt = 0.0001; // days (8.64 seconds) - matches C code PLAN_SPEED_INTV

        if ($isHeliocentric) {
            // Heliocentric velocities: differentiate planet position directly
            [$pxp, $pyp, $pzp] = $planetHelioRect($jd_tt + $dt);
            [$pxm, $pym, $pzm] = $planetHelioRect($jd_tt - $dt);
            $gxp = $pxp;
            $gyp = $pyp;
            $gzp = $pzp;
            $gxm = $pxm;
            $gym = $pym;
            $gzm = $pzm;
        } else {
            // Geocentric velocities: subtract Earth velocities
            [$pxp, $pyp, $pzp] = $planetHelioRect($jd_tt + $dt);
            [$exp, $eyp, $ezp] = self::earthHeliocRectApproxFromSun($jd_tt + $dt);
            $gxp = $pxp - $exp;
            $gyp = $pyp - $eyp;
            $gzp = $pzp - $ezp;

            [$pxm, $pym, $pzm] = $planetHelioRect($jd_tt - $dt);
            [$exm, $eym, $ezm] = self::earthHeliocRectApproxFromSun($jd_tt - $dt);
            $gxm = $pxm - $exm;
            $gym = $pym - $eym;
            $gzm = $pzm - $ezm;
        }

        if ($iflag & Constants::SEFLG_XYZ) {
            // Rectangular (AU, AU/day)
            // Check if EQUATORIAL + XYZ: transform ecliptic cartesian to equatorial cartesian
            if ($iflag & Constants::SEFLG_EQUATORIAL) {
                // Get obliquity for transformation (use J2000 if J2000 flag set)
                if ($iflag & Constants::SEFLG_J2000) {
                    $eps = 0.40909280422232897; // J2000 obliquity in radians (23.4392911°)
                } else {
                    $eps = Obliquity::meanObliquityRadFromJdTT($jd_tt);
                }
                $sineps = sin($eps);
                $coseps = cos($eps);

                // Transform position: ecliptic → equatorial
                $eqPos = [];
                Coordinates::coortrf2([$gx, $gy, $gz], $eqPos, -$sineps, $coseps);
                // Transform velocity: ecliptic → equatorial
                $vx = ($gxp - $gxm) / (2 * $dt);
                $vy = ($gyp - $gym) / (2 * $dt);
                $vz = ($gzp - $gzm) / (2 * $dt);
                $eqVel = [];
                Coordinates::coortrf2([$vx, $vy, $vz], $eqVel, -$sineps, $coseps);

                $xx[0] = $eqPos[0];
                $xx[1] = $eqPos[1];
                $xx[2] = $eqPos[2];
                $xx[3] = $eqVel[0];
                $xx[4] = $eqVel[1];
                $xx[5] = $eqVel[2];
            } else {
                // Ecliptic cartesian
                $xx[0] = $gx;
                $xx[1] = $gy;
                $xx[2] = $gz;
                $xx[3] = ($gxp - $gxm) / (2 * $dt);
                $xx[4] = ($gyp - $gym) / (2 * $dt);
                $xx[5] = ($gzp - $gzm) / (2 * $dt);
            }
            return $xx;
        }        // Spherical long/lat at shifted times
        $lonp = \atan2($gyp, $gxp);
        if ($lonp < 0) {
            $lonp += Math::TWO_PI;
        }
        $lonm = \atan2($gym, $gxm);
        if ($lonm < 0) {
            $lonm += Math::TWO_PI;
        }
        $latp = \atan2($gzp, \sqrt($gxp * $gxp + $gyp * $gyp));
        $latm = \atan2($gzm, \sqrt($gxm * $gxm + $gym * $gym));

        if ($iflag & Constants::SEFLG_EQUATORIAL) {
            $eps = Obliquity::meanObliquityRadFromJdTT($jd_tt);
            [$ra, $dec] = Coordinates::eclipticToEquatorialRad($lon, $lat, $dist, $eps);
            [$rap, $decp] = Coordinates::eclipticToEquatorialRad($lonp, $latp, $dist, $eps);
            [$ram, $decm] = Coordinates::eclipticToEquatorialRad($lonm, $latm, $dist, $eps);
            $isRad = (bool)($iflag & Constants::SEFLG_RADIANS);
            $dRa = Math::angleDiffRad($rap, $ram) / (2 * $dt);
            $dDec = ($decp - $decm) / (2 * $dt);
            if (!$isRad) {
                $ra = Math::radToDeg($ra);
                $dec = Math::radToDeg($dec);
                $dRa = Math::radToDeg($dRa);
                $dDec = Math::radToDeg($dDec);
            }
            $r_p = \sqrt($gxp * $gxp + $gyp * $gyp + $gzp * $gzp);
            $r_m = \sqrt($gxm * $gxm + $gym * $gym + $gzm * $gzm);
            return [$ra, $dec, $dist, $dRa, $dDec, ($r_p - $r_m) / (2 * $dt)];
        }

        // Ecliptic spherical speeds
        $dLon = Math::angleDiffRad($lonp, $lonm) / (2 * $dt);
        $dLat = ($latp - $latm) / (2 * $dt);
        $dR = (
            \sqrt($gxp * $gxp + $gyp * $gyp + $gzp * $gzp)
            - \sqrt($gxm * $gxm + $gym * $gym + $gzm * $gzm)
        ) / (2 * $dt);
        $isRad = (bool)($iflag & Constants::SEFLG_RADIANS);
        if (!$isRad) {
            // convert angles and angular speeds to degrees
            $lon_deg = Math::radToDeg($lon);
            $lat_deg = Math::radToDeg($lat);
            $dLon = Math::radToDeg($dLon);
            $dLat = Math::radToDeg($dLat);
            return [$lon_deg, $lat_deg, $dist, $dLon, $dLat, $dR];
        }
        return [$lon, $lat, $dist, $dLon, $dLat, $dR];
    }
}
