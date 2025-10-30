<?php

declare(strict_types=1);

namespace Swisseph\Domain\NodesApsides;

use Swisseph\Constants;
use Swisseph\Coordinates;
use Swisseph\Math;
use Swisseph\Swe\Functions\PlanetsFunctions;
use Swisseph\VectorMath;

/**
 * Calculator for osculating nodes and apsides from orbital positions
 * Port from Swiss Ephemeris swecl.c osculating calculation
 */
class OsculatingCalculator
{
    private const J2000 = 2451545.0;

    /**
     * Calculate osculating nodes and apsides from orbital elements
     *
     * @param float $tjdEt Julian day ET/TT
     * @param int $ipl Planet number
     * @param int $iflag Calculation flags
     * @param array &$xnasc Output: ascending node [lon, lat, dist, dlon, dlat, ddist]
     * @param array &$xndsc Output: descending node
     * @param array &$xperi Output: perihelion
     * @param array &$xaphe Output: aphelion or focal point
     * @param bool $doFocalPoint Return focal point instead of aphelion
     * @param bool $withSpeed Calculate speeds via finite differences
     * @param bool $useBary Use barycentric ellipse (for outer planets)
     * @param string|null &$serr Error message
     * @return bool Success
     */
    public static function calculate(
        float $tjdEt,
        int $ipl,
        int $iflag,
        array &$xnasc,
        array &$xndsc,
        array &$xperi,
        array &$xaphe,
        bool $doFocalPoint,
        bool $withSpeed,
        bool $useBary,
        ?string &$serr
    ): bool {
        if (getenv('DEBUG_OSCU')) {
            fprintf(STDERR, "OsculatingCalculator::calculate() called for ipl=%d\n", $ipl);
        }

        // Initialize arrays
        $xnasc = array_fill(0, 6, 0.0);
        $xndsc = array_fill(0, 6, 0.0);
        $xperi = array_fill(0, 6, 0.0);
        $xaphe = array_fill(0, 6, 0.0);

        // Determine time interval and gravitational constant
        if ($ipl === Constants::SE_MOON) {
            $dt = Constants::NODE_CALC_INTV;
            $dzmin = 1e-15;
            $Gmsm = Constants::GEOGCONST * (1 + 1 / Constants::EARTH_MOON_MRAT) /
                    Constants::AUNIT / Constants::AUNIT / Constants::AUNIT * 86400.0 * 86400.0;
        } else {
            // Get planet mass if available
            $iplx = PlanetaryElements::IPL_TO_ELEM[$ipl] ?? null;
            if ($iplx !== null && $iplx > 0) {
                $plm = 1.0 / PlanetaryElements::PLMASS[$iplx];
            } else {
                $plm = 0.0;
            }

            // First get planet position to determine distance
            $x = [];
            // Match C code exactly: swecl.c:5123
            // iflg0 = (iflag & (SEFLG_EPHMASK|SEFLG_NONUT)) | SEFLG_SPEED | SEFLG_TRUEPOS;
            $iflg0 = ($iflag & (Constants::SEFLG_EPHMASK | Constants::SEFLG_NONUT)) |
                     Constants::SEFLG_SPEED | Constants::SEFLG_TRUEPOS;
            if ($ipl !== Constants::SE_MOON) {
                $iflg0 |= $useBary ? Constants::SEFLG_BARYCTR : Constants::SEFLG_HELCTR;
            }

            $retflag = PlanetsFunctions::calc($tjdEt, $ipl, $iflg0, $x, $serr);
            if ($retflag < 0) {
                return false;
            }

            if (getenv('DEBUG_OSCU')) {
                error_log(sprintf("DEBUG first calc result: x=[%.6f, %.6f, %.6f, %.6f, %.6f, %.6f] (polar coords: lon, lat, dist)",
                    $x[0], $x[1], $x[2], $x[3], $x[4], $x[5]));
            }

            $dt = Constants::NODE_CALC_INTV * 10 * $x[2];
            $dzmin = 1e-15 * $dt / Constants::NODE_CALC_INTV;
            $Gmsm = Constants::HELGRAVCONST * (1 + $plm) /
                    Constants::AUNIT / Constants::AUNIT / Constants::AUNIT * 86400.0 * 86400.0;
        }

        // Get positions at three time points (for speed calculation)
        $istart = $withSpeed ? 0 : 1;
        $iend = $withSpeed ? 2 : 1;

        $xpos = [];
        // J2000 equatorial rectangular coordinates with speeds
        // Match C code swecl.c:5242 exactly:
        // iflJ2000 = (iflag & SEFLG_EPHMASK)|SEFLG_J2000|SEFLG_EQUATORIAL|SEFLG_XYZ|SEFLG_TRUEPOS|SEFLG_NONUT|SEFLG_SPEED;
        $iflJ2000 = ($iflag & Constants::SEFLG_EPHMASK) |
                    Constants::SEFLG_J2000 |
                    Constants::SEFLG_EQUATORIAL |  // Get equatorial coordinates directly
                    Constants::SEFLG_XYZ |
                    Constants::SEFLG_TRUEPOS |
                    Constants::SEFLG_NONUT |
                    Constants::SEFLG_SPEED;

        if ($ipl !== Constants::SE_MOON) {
            $iflJ2000 |= $useBary ? Constants::SEFLG_BARYCTR : Constants::SEFLG_HELCTR;
        }

        if (getenv('DEBUG_OSCU')) {
            error_log(sprintf("DEBUG osculating loop: istart=%d, iend=%d, dt=%.10f, tjdEt=%.10f", $istart, $iend, $dt, $tjdEt));
        }

        for ($i = $istart; $i <= $iend; $i++) {
            $t = $tjdEt + ($i - 1) * $dt;

            if (getenv('DEBUG_OSCU')) {
                error_log(sprintf("DEBUG calling PlanetsFunctions::calc [i=%d]: ipl=%d, tjd=%.10f (tjdEt + (%d-1)*%.10f), iflJ2000=0x%X", $i, $ipl, $t, $i, $dt, $iflJ2000));
            }

            $xpos[$i] = [];
            $retflag = PlanetsFunctions::calc($t, $ipl, $iflJ2000, $xpos[$i], $serr);
            if ($retflag < 0) {
                return false;
            }

            if (getenv('DEBUG_OSCU')) {
                error_log(sprintf("DEBUG equatorial J2000 from PlanetsFunctions [i=%d]: xx=[%.15f, %.15f, %.15f, %.15f, %.15f, %.15f]",
                    $i, $xpos[$i][0], $xpos[$i][1], $xpos[$i][2], $xpos[$i][3], $xpos[$i][4], $xpos[$i][5]));
            }

            // For Earth, add Moon contribution (EMB → Earth barycenter)
            if ($ipl === Constants::SE_EARTH) {
                $xposm = [];
                $retflag = PlanetsFunctions::calc(
                    $t,
                    Constants::SE_MOON,
                    $iflJ2000 & ~(Constants::SEFLG_BARYCTR | Constants::SEFLG_HELCTR),
                    $xposm,
                    $serr
                );
                if ($retflag < 0) {
                    return false;
                }
                for ($j = 0; $j <= 5; $j++) {
                    $xpos[$i][$j] += $xposm[$j] / (Constants::EARTH_MOON_MRAT + 1.0);
                }
            }

            // Apply full transformation chain for osculating elements
            // CRITICAL: Use $iflJ2000, not $iflg0, because coordinates are in J2000
            if (getenv('DEBUG_OSCU')) {
                error_log(sprintf("DEBUG BEFORE planForOscElem [i=%d]: xx=[%.15f, %.15f, %.15f]",
                    $i, $xpos[$i][0], $xpos[$i][1], $xpos[$i][2]));
            }

            self::planForOscElem($iflJ2000, $t, $xpos[$i]);

            if (getenv('DEBUG_OSCU')) {
                error_log(sprintf("DEBUG AFTER planForOscElem [i=%d]: xx=[%.15f, %.15f, %.15f]",
                    $i, $xpos[$i][0], $xpos[$i][1], $xpos[$i][2]));
                $lon = rad2deg(atan2($xpos[$i][1], $xpos[$i][0]));
                if ($lon < 0) $lon += 360.0;
                error_log(sprintf("  lon=%.10f°, r=%.10f AU", $lon, sqrt($xpos[$i][0]**2 + $xpos[$i][1]**2 + $xpos[$i][2]**2)));
            }
        }

        // Calculate nodes and apsides from positions
        $xn = [];
        $xs = [];
        $xq = [];
        $xa = [];

        for ($i = $istart; $i <= $iend; $i++) {
            // Ensure minimum z-velocity
            if (abs($xpos[$i][5]) < $dzmin) {
                $xpos[$i][5] = $dzmin;
            }

            $fac = $xpos[$i][2] / $xpos[$i][5];
            $sgn = $xpos[$i][5] / abs($xpos[$i][5]);

            if (getenv('DEBUG_OSCU')) {
                error_log(sprintf("DEBUG node vector inputs [%d]:", $i));
                error_log(sprintf("  xpos=[%.15f, %.15f, %.15f]", $xpos[$i][0], $xpos[$i][1], $xpos[$i][2]));
                error_log(sprintf("  vel =[%.15f, %.15f, %.15f]", $xpos[$i][3], $xpos[$i][4], $xpos[$i][5]));
                error_log(sprintf("  fac=%.15f (z/vz = %.15f / %.15f)", $fac, $xpos[$i][2], $xpos[$i][5]));
                error_log(sprintf("  sgn=%.15f (vz/|vz| = %.15f / %.15f)", $sgn, $xpos[$i][5], abs($xpos[$i][5])));
            }

            // Calculate node vector: xn = (x - (z/v_z) * v) * sgn(v_z)
            for ($j = 0; $j <= 2; $j++) {
                $xn[$i][$j] = ($xpos[$i][$j] - $fac * $xpos[$i][$j + 3]) * $sgn;
                $xs[$i][$j] = -$xn[$i][$j]; // Descending node is opposite
            }

            if (getenv('DEBUG_OSCU')) {
                error_log(sprintf("  xn=[%.15f, %.15f, %.15f]", $xn[$i][0], $xn[$i][1], $xn[$i][2]));
                $test_lon = rad2deg(atan2($xn[$i][1], $xn[$i][0]));
                $test_r = sqrt($xn[$i][0]**2 + $xn[$i][1]**2 + $xn[$i][2]**2);
                error_log(sprintf("  → node lon=%.10f°, r=%.10f AU", $test_lon, $test_r));
            }
        }

        // Calculate orbital elements for each time point
        for ($i = $istart; $i <= $iend; $i++) {
            // Node longitude
            $rxy = sqrt($xn[$i][0] * $xn[$i][0] + $xn[$i][1] * $xn[$i][1]);
            $cosnode = $xn[$i][0] / $rxy;
            $sinnode = $xn[$i][1] / $rxy;

            if (getenv('DEBUG_OSCU')) {
                $node_lon_rad = atan2($sinnode, $cosnode);
                $node_lon_deg = rad2deg($node_lon_rad);
                error_log(sprintf("DEBUG orbital elements [%d]: node_lon=%.6f° (%.10f rad), cosnode=%.10f, sinnode=%.10f",
                    $i, $node_lon_deg, $node_lon_rad, $cosnode, $sinnode));
            }

            // Inclination from angular momentum vector
            $xnorm = [];
            VectorMath::crossProduct($xpos[$i], array_slice($xpos[$i], 3, 3), $xnorm);

            $rxy2 = $xnorm[0] * $xnorm[0] + $xnorm[1] * $xnorm[1];
            $c2 = $rxy2 + $xnorm[2] * $xnorm[2];
            $rxyz = sqrt($c2);
            $rxy = sqrt($rxy2);
            $sinincl = $rxy / $rxyz;
            $cosincl = sqrt(1 - $sinincl * $sinincl);
            if ($xnorm[2] < 0) {
                $cosincl = -$cosincl; // Retrograde orbit
            }

            // Argument of latitude
            $cosu = $xpos[$i][0] * $cosnode + $xpos[$i][1] * $sinnode;
            $sinu = $xpos[$i][2] / $sinincl;
            $uu = atan2($sinu, $cosu);

            // Semi-major axis
            $rxyz = sqrt(VectorMath::squareSum($xpos[$i]));
            $vel = array_slice($xpos[$i], 3, 3);
            $v2 = VectorMath::squareSum($vel);

            if (getenv('DEBUG_OSCU') && $i === 1) {
                error_log(sprintf("DEBUG SEMA calc: i=%d, rxyz=%.10f AU, v2=%.10f, Gmsm=%.10f",
                    $i, $rxyz, $v2, $Gmsm));
                error_log(sprintf("DEBUG xpos=[%.10f, %.10f, %.10f], vel=[%.10f, %.10f, %.10f]",
                    $xpos[$i][0], $xpos[$i][1], $xpos[$i][2], $xpos[$i][3], $xpos[$i][4], $xpos[$i][5]));
            }

            $sema = 1 / (2 / $rxyz - $v2 / $Gmsm);

            // Eccentricity
            $pp = $c2 / $Gmsm;
            $ecce = sqrt(1 - $pp / $sema);

            if (getenv('DEBUG_OSCU')) {
                error_log(sprintf("DEBUG orbital params [%d]: sema=%.10f, ecce=%.10f, incl=%.10f°",
                    $i, $sema, $ecce, rad2deg(asin($sinincl))));
            }

            // Eccentric anomaly
            $cosE = 1 / $ecce * (1 - $rxyz / $sema);
            $sinE = 1 / $ecce / sqrt($sema * $Gmsm) *
                    VectorMath::dotProduct($xpos[$i], array_slice($xpos[$i], 3, 3));

            // True anomaly
            $ny = 2 * atan(sqrt((1 + $ecce) / (1 - $ecce)) * $sinE / (1 + $cosE));

            // Perihelion in orbital plane, then transform to ecliptic
            $xq[$i][0] = Math::mod2PI($uu - $ny);
            $xq[$i][1] = 0;
            $xq[$i][2] = $sema * (1 - $ecce);

            Coordinates::polCart($xq[$i], $xq[$i]);
            Coordinates::coortrf2($xq[$i], $xq[$i], -$sinincl, $cosincl);
            Coordinates::cartPol($xq[$i], $xq[$i]);
            $xq[$i][0] += atan2($sinnode, $cosnode);

            // Aphelion
            $xa[$i][0] = Math::mod2PI($xq[$i][0] + M_PI);
            $xa[$i][1] = -$xq[$i][1];

            if ($doFocalPoint) {
                $xa[$i][2] = $sema * $ecce * 2;
            } else {
                $xa[$i][2] = $sema * (1 + $ecce);
            }

            Coordinates::polCart($xq[$i], $xq[$i]);
            Coordinates::polCart($xa[$i], $xa[$i]);

            // Correct node distances using true anomaly
            $ny_node = Math::mod2PI($ny - $uu);
            $ny2 = Math::mod2PI($ny_node + M_PI);

            $cosE = cos(2 * atan(tan($ny_node / 2) / sqrt((1 + $ecce) / (1 - $ecce))));
            $cosE2 = cos(2 * atan(tan($ny2 / 2) / sqrt((1 + $ecce) / (1 - $ecce))));

            $rn = $sema * (1 - $ecce * $cosE);
            $rn2 = $sema * (1 - $ecce * $cosE2);

            $ro = sqrt(VectorMath::squareSum($xn[$i]));
            $ro2 = sqrt(VectorMath::squareSum($xs[$i]));

            if (getenv('DEBUG_OSCU')) {
                error_log(sprintf("DEBUG distance correction [%d]: rn=%.10f, ro=%.10f, scale=%.10f",
                    $i, $rn, $ro, $rn / $ro));
                error_log(sprintf("  Before scaling: xn=[%.10f, %.10f, %.10f]",
                    $xn[$i][0], $xn[$i][1], $xn[$i][2]));
            }

            for ($j = 0; $j <= 2; $j++) {
                $xn[$i][$j] *= $rn / $ro;
                $xs[$i][$j] *= $rn2 / $ro2;
            }

            if (getenv('DEBUG_OSCU')) {
                error_log(sprintf("  After scaling: xn=[%.10f, %.10f, %.10f]",
                    $xn[$i][0], $xn[$i][1], $xn[$i][2]));
                $final_lon = rad2deg(atan2($xn[$i][1], $xn[$i][0]));
                $final_r = sqrt($xn[$i][0]**2 + $xn[$i][1]**2 + $xn[$i][2]**2);
                error_log(sprintf("  Final: lon=%.10f°, r=%.10f AU", $final_lon, $final_r));
            }
        }

        // Extract results (use middle time point, or calculate speeds if requested)
        for ($i = 0; $i <= 2; $i++) {
            if ($withSpeed) {
                $xperi[$i] = $xq[1][$i];
                $xperi[$i + 3] = ($xq[2][$i] - $xq[0][$i]) / $dt / 2;
                $xaphe[$i] = $xa[1][$i];
                $xaphe[$i + 3] = ($xa[2][$i] - $xa[0][$i]) / $dt / 2;
                $xnasc[$i] = $xn[1][$i];
                $xnasc[$i + 3] = ($xn[2][$i] - $xn[0][$i]) / $dt / 2;
                $xndsc[$i] = $xs[1][$i];
                $xndsc[$i + 3] = ($xs[2][$i] - $xs[0][$i]) / $dt / 2;
            } else {
                $xperi[$i] = $xq[1][$i];
                $xperi[$i + 3] = 0;
                $xaphe[$i] = $xa[1][$i];
                $xaphe[$i + 3] = 0;
                $xnasc[$i] = $xn[1][$i];
                $xnasc[$i + 3] = 0;
                $xndsc[$i] = $xs[1][$i];
                $xndsc[$i + 3] = 0;
            }
        }

        if (getenv('DEBUG_OSCU')) {
            fprintf(STDERR, "OsculatingCalculator: xnasc=[%.10f, %.10f, %.10f]\n", $xnasc[0], $xnasc[1], $xnasc[2]);
            fprintf(STDERR, "OsculatingCalculator: xn[1]=[%.10f, %.10f, %.10f]\n", $xn[1][0], $xn[1][1], $xn[1][2]);
        }

        return true;
    }

    /**
     * Full coordinate transformation chain for osculating element calculation
     * Port of swi_plan_for_osc_elem() from sweph.c
     *
     * Chain: ICRS → J2000 → date equatorial → date ecliptic
     *
     * @param int $iflag Calculation flags
     * @param float $tjd Julian day
     * @param array &$xx Position and speed [x, y, z, dx, dy, dz] (modified in place)
     */
    private static function planForOscElem(int $iflag, float $tjd, array &$xx): void
    {
        if (getenv('DEBUG_OSCU')) {
            $lon_before = rad2deg(atan2($xx[1], $xx[0]));
            $r_before = sqrt($xx[0]**2 + $xx[1]**2 + $xx[2]**2);
            error_log(sprintf("planForOscElem INPUT: equatorial J2000 xx=[%.10f, %.10f, %.10f]", $xx[0], $xx[1], $xx[2]));
            error_log(sprintf("  lon=%.6f°, r=%.6f AU", $lon_before, $r_before));
        }

        // NOTE: Input xx[] is in EQUATORIAL J2000 XYZ (from swe_calc with SEFLG_EQUATORIAL)
        // This function transforms it to ECLIPTIC coordinates for osculating elements calculation
        // Following C code in swi_plan_for_osc_elem() from sweph.c:5787

        // Step 1: ICRS to J2000 (frame bias for DE403+)
        if (!($iflag & Constants::SEFLG_ICRS)) {
            $xx = \Swisseph\Bias::apply(
                $xx,
                $tjd,
                $iflag,
                \Swisseph\Bias::MODEL_IAU_2006,
                false,
                \Swisseph\JplHorizonsApprox::SEMOD_JPLHORA_DEFAULT
            );
        }

        // Step 2: Precession from J2000 to date
        $useJ2000 = ($iflag & Constants::SEFLG_J2000) !== 0;

        if (!$useJ2000) {
            \Swisseph\Precession::precess($xx, $tjd, $iflag, -1, null);

            if (count($xx) >= 6) {
                $vel = [$xx[3], $xx[4], $xx[5]];
                \Swisseph\Precession::precess($vel, $tjd, $iflag, -1, null);
                $xx[3] = $vel[0];
                $xx[4] = $vel[1];
                $xx[5] = $vel[2];
            }
        }

        // Calculate obliquity
        $useEpoch = $useJ2000 ? self::J2000 : $tjd;
        $eps = \Swisseph\Obliquity::calc($useEpoch, $iflag, 0, null);
        $seps = sin($eps);
        $ceps = cos($eps);

        // Step 3: Nutation (mean to true equator of date)
        if (!($iflag & Constants::SEFLG_NONUT) && !$useJ2000) {
            $nutModel = \Swisseph\Nutation::selectModelFromFlags($iflag);
            [$dpsi, $deps] = \Swisseph\Nutation::calc($tjd, $nutModel, false);

            $nutMatrix = \Swisseph\NutationMatrix::build($dpsi, $deps, $eps, $seps, $ceps);

            $xTemp = \Swisseph\NutationMatrix::apply($nutMatrix, $xx);
            $xx[0] = $xTemp[0];
            $xx[1] = $xTemp[1];
            $xx[2] = $xTemp[2];

            if (count($xx) >= 6) {
                $velTemp = \Swisseph\NutationMatrix::apply($nutMatrix, [$xx[3], $xx[4], $xx[5]]);
                $xx[3] = $velTemp[0];
                $xx[4] = $velTemp[1];
                $xx[5] = $velTemp[2];
            }
        }

        // Step 4: Transform to ecliptic coordinates
        // C code sweph.c:5881: swi_coortrf2(xx, xx, oe->seps, oe->ceps)
        if (getenv('DEBUG_OSCU')) {
            error_log(sprintf("planForOscElem BEFORE ecliptic transform: equatorial xx=[%.15f, %.15f, %.15f]", $xx[0], $xx[1], $xx[2]));
            if (count($xx) >= 6) {
                error_log(sprintf("  velocity: vx=[%.15f, %.15f, %.15f]", $xx[3], $xx[4], $xx[5]));
            }
            error_log(sprintf("  obliquity: seps=%.15f, ceps=%.15f", $seps, $ceps));
        }

        $xOut = [];
        \Swisseph\Coordinates::coortrf2([$xx[0], $xx[1], $xx[2]], $xOut, $seps, $ceps);

        $xx[0] = $xOut[0];
        $xx[1] = $xOut[1];
        $xx[2] = $xOut[2];

        if (count($xx) >= 6) {
            $velOut = [];
            \Swisseph\Coordinates::coortrf2([$xx[3], $xx[4], $xx[5]], $velOut, $seps, $ceps);
            $xx[3] = $velOut[0];
            $xx[4] = $velOut[1];
            $xx[5] = $velOut[2];
        }

        if (getenv('DEBUG_OSCU')) {
            error_log(sprintf("planForOscElem AFTER ecliptic transform: ecliptic xx=[%.15f, %.15f, %.15f]", $xx[0], $xx[1], $xx[2]));
            if (count($xx) >= 6) {
                error_log(sprintf("  velocity: vx=[%.15f, %.15f, %.15f]", $xx[3], $xx[4], $xx[5]));
            }
        }

        // Step 5: Apply nutation to ecliptic (C code sweph.c:5894-5896)
        // This transforms from ECLIPTIC OF DATE (mean) to ECLIPTIC OF DATE (true, with nutation)
        if (!($iflag & \Swisseph\Constants::SEFLG_NONUT)) {
            // Get nutation for date
            $nutModel = \Swisseph\Nutation::selectModelFromFlags($iflag);
            [$dpsi, $deps] = \Swisseph\Nutation::calc($tjd, $nutModel, false);
            $snut = sin($deps);
            $cnut = cos($deps);

            if (getenv('DEBUG_OSCU')) {
                error_log(sprintf("planForOscElem BEFORE nutation to ecliptic: xx=[%.10f, %.10f, %.10f]", $xx[0], $xx[1], $xx[2]));
                error_log(sprintf("  nutation: snut=%.10f, cnut=%.10f, deps=%.10f rad", $snut, $cnut, $deps));
            }

            // Apply nutation rotation
            $xOut = [];
            \Swisseph\Coordinates::coortrf2([$xx[0], $xx[1], $xx[2]], $xOut, $snut, $cnut);
            $xx[0] = $xOut[0];
            $xx[1] = $xOut[1];
            $xx[2] = $xOut[2];

            if (count($xx) >= 6) {
                $velOut = [];
                \Swisseph\Coordinates::coortrf2([$xx[3], $xx[4], $xx[5]], $velOut, $snut, $cnut);
                $xx[3] = $velOut[0];
                $xx[4] = $velOut[1];
                $xx[5] = $velOut[2];
            }

            if (getenv('DEBUG_OSCU')) {
                error_log(sprintf("planForOscElem AFTER nutation to ecliptic: xx=[%.10f, %.10f, %.10f]", $xx[0], $xx[1], $xx[2]));
            }
        }

        // Reindex to ensure [x, y, z, vx, vy, vz]
        $xxReindexed = [
            $xx[0],
            $xx[1],
            $xx[2],
        ];
        if (count($xx) >= 6) {
            $xxReindexed[3] = $xx[3];
            $xxReindexed[4] = $xx[4];
            $xxReindexed[5] = $xx[5];
        }
        $xx = $xxReindexed;

        if (getenv('DEBUG_OSCU')) {
            error_log(sprintf("planForOscElem OUTPUT: ecliptic xx=[%.10f, %.10f, %.10f, %.10f, %.10f, %.10f]",
                $xx[0], $xx[1], $xx[2], $xx[3], $xx[4], $xx[5]));
            $test_lon = rad2deg(atan2($xx[1], $xx[0]));
            if ($test_lon < 0) $test_lon += 360.0;
            error_log(sprintf("  lon=%.10f°", $test_lon));
        }
    }
}
