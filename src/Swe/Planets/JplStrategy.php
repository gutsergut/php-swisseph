<?php

declare(strict_types=1);

namespace Swisseph\Swe\Planets;

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;
use Swisseph\Swe\Jpl\JplConstants;
use Swisseph\Swe\Jpl\JplEphemeris;

/**
 * JPL Ephemeris strategy for planet calculations
 * Uses DE405, DE431, DE440, DE441, etc.
 *
 * This strategy fetches raw barycentric J2000 coordinates from JPL ephemeris
 * and passes them through the same apparent position pipeline as SwephStrategy.
 */
class JplStrategy implements EphemerisStrategy
{
    private ?JplEphemeris $jpl = null;
    private bool $initialized = false;

    public function supports(int $ipl, int $iflag): bool
    {
        // Support Sun, Moon, Mercury-Pluto
        if ($ipl >= Constants::SE_SUN && $ipl <= Constants::SE_PLUTO) {
            return true;
        }
        if ($ipl === Constants::SE_EARTH) {
            return true;
        }
        // Mean nodes not supported by JPL
        if ($ipl === Constants::SE_MEAN_NODE || $ipl === Constants::SE_TRUE_NODE) {
            return false;
        }
        return false;
    }

    public function compute(float $jdTt, int $ipl, int $iflag): StrategyResult
    {
        // Initialize JPL ephemeris if needed
        if (!$this->initialized) {
            $this->jpl = JplEphemeris::getInstance();
            $swed = SwedState::getInstance();

            // Try to open JPL ephemeris file
            $jplFile = $swed->getJplFile();
            if (empty($jplFile)) {
                $jplFile = 'de441.eph';  // Default
            }

            $ss = [];
            $serr = null;
            $ret = $this->jpl->open($ss, $jplFile, $swed->getEphePath(), $serr);

            if ($ret !== JplConstants::OK) {
                return StrategyResult::err(
                    $serr ?? 'Could not open JPL ephemeris file',
                    Constants::SE_ERR
                );
            }

            $this->initialized = true;
        }

        // Map SE planet to JPL body index
        $jplTarget = $this->seToJpl($ipl);
        if ($jplTarget < 0) {
            return StrategyResult::err(
                sprintf('Planet %d not supported by JPL ephemeris', $ipl),
                Constants::SE_ERR
            );
        }

        $swed = SwedState::getInstance();
        $serr = null;

        // Always get planet barycentric (relative to SSB) for pipeline
        $planetBary = [];
        $ret = $this->jpl->pleph($jdTt, $jplTarget, JplConstants::J_SBARY, $planetBary, $serr);
        if ($ret !== JplConstants::OK) {
            return StrategyResult::err($serr ?? 'JPL ephemeris error for planet', Constants::SE_ERR);
        }

        // Get Earth barycentric (EMB relative to SSB) - needed for geocentric calcs
        $earthBary = [];
        $ret = $this->jpl->pleph($jdTt, JplConstants::J_EARTH, JplConstants::J_SBARY, $earthBary, $serr);
        if ($ret !== JplConstants::OK) {
            return StrategyResult::err($serr ?? 'JPL ephemeris error for Earth', Constants::SE_ERR);
        }

        // Get Sun barycentric (relative to SSB) - needed for heliocentric calcs
        $sunBary = [];
        $ret = $this->jpl->pleph($jdTt, JplConstants::J_SUN, JplConstants::J_SBARY, $sunBary, $serr);
        if ($ret !== JplConstants::OK) {
            return StrategyResult::err($serr ?? 'JPL ephemeris error for Sun', Constants::SE_ERR);
        }

        // Convert from equatorial J2000 to ecliptic J2000
        $planetEcl = $this->equatorialToEcliptic($planetBary);
        $earthEcl = $this->equatorialToEcliptic($earthBary);
        $sunEcl = $this->equatorialToEcliptic($sunBary);

        // NOTE: Do NOT store in SwedState pldat - this would corrupt cache for other ephemerides
        // JplStrategy handles its own geocentric transformations without relying on global state

        // Moon special case: pipeline expects geocentric directly
        if ($ipl === Constants::SE_MOON) {
            $moonGeo = [];
            $ret = $this->jpl->pleph($jdTt, JplConstants::J_MOON, JplConstants::J_EARTH, $moonGeo, $serr);
            if ($ret !== JplConstants::OK) {
                return StrategyResult::err($serr ?? 'JPL ephemeris error for Moon', Constants::SE_ERR);
            }
            $moonEcl = $this->equatorialToEcliptic($moonGeo);
            // Use MoonTransform for full apparent position
            $swed->pldat[SwephConstants::SEI_MOON]->x = $moonEcl;
            $retc = \Swisseph\Swe\Moon\MoonTransform::appPosEtc($iflag, $serr);
            if ($retc !== Constants::SE_OK) {
                return StrategyResult::err($serr ?? 'Moon transform error', $retc);
            }
            $pdp = &$swed->pldat[SwephConstants::SEI_MOON];
            $offset = 0;
            if ($iflag & Constants::SEFLG_EQUATORIAL) {
                $offset = ($iflag & Constants::SEFLG_XYZ) ? 18 : 12;
            } else {
                $offset = ($iflag & Constants::SEFLG_XYZ) ? 6 : 0;
            }
            $out = [0,0,0,0,0,0];
            for ($i=0; $i<6; $i++) { $out[$i] = $pdp->xreturn[$offset+$i]; }
            return StrategyResult::okFinal($out);
        }

        // For geocentric calculations, compute planet - Earth
        $isHeliocentric = (bool)($iflag & Constants::SEFLG_HELCTR);
        $isBarycentric = (bool)($iflag & Constants::SEFLG_BARYCTR);

        $xx = $planetEcl;
        if (!$isHeliocentric && !$isBarycentric) {
            // Geocentric: subtract Earth position
            for ($i = 0; $i < 6; $i++) {
                $xx[$i] = $planetEcl[$i] - $earthEcl[$i];
            }
        } elseif ($isHeliocentric) {
            // Heliocentric: subtract Sun position
            for ($i = 0; $i < 6; $i++) {
                $xx[$i] = $planetEcl[$i] - $sunEcl[$i];
            }
        }
        // For barycentric, xx = planetEcl (already relative to SSB)

        // Apply light-time correction if needed
        if (!($iflag & Constants::SEFLG_TRUEPOS) && $ipl !== Constants::SE_EARTH) {
            $c_au_per_day = 173.144632674240;
            $r = sqrt($xx[0]*$xx[0] + $xx[1]*$xx[1] + $xx[2]*$xx[2]);
            $dt_light = $r / $c_au_per_day;

            // Rough apparent position (one iteration)
            for ($i = 0; $i < 3; $i++) {
                $xx[$i] = $xx[$i] - $xx[$i + 3] * $dt_light;
            }
        }

        // Ensure obliquity is calculated
        if ($swed->oec->needsUpdate($jdTt)) {
            $swed->oec->calculate($jdTt, $iflag);
        }
        if ($swed->oec2000->needsUpdate(Constants::J2000)) {
            $swed->oec2000->calculate(Constants::J2000, $iflag);
        }

        // Apply precession from J2000 to date (unless SEFLG_J2000 set)
        if (!($iflag & Constants::SEFLG_J2000)) {
            \Swisseph\Precession::precess($xx, $jdTt, $iflag, Constants::J2000_TO_J);
            if ($iflag & Constants::SEFLG_SPEED) {
                \Swisseph\Precession::precessSpeed($xx, $jdTt, $iflag, Constants::J2000_TO_J);
            }
        }

        // Convert cartesian to polar (ecliptic longitude, latitude, distance)
        $r = sqrt($xx[0]*$xx[0] + $xx[1]*$xx[1] + $xx[2]*$xx[2]);
        $lon = rad2deg(atan2($xx[1], $xx[0]));
        if ($lon < 0) $lon += 360.0;
        $lat = rad2deg(asin($xx[2] / $r));

        // Speed in polar coordinates (simple approximation)
        $lonSpeed = 0.0;
        $latSpeed = 0.0;
        $distSpeed = 0.0;
        if ($iflag & Constants::SEFLG_SPEED) {
            // Numerical differentiation would be more accurate, but for now use chain rule
            $rxy = sqrt($xx[0]*$xx[0] + $xx[1]*$xx[1]);
            if ($rxy > 0 && $r > 0) {
                $lonSpeed = rad2deg(($xx[0]*$xx[4] - $xx[1]*$xx[3]) / ($rxy*$rxy));
                $latSpeed = rad2deg(($xx[5]*$rxy - $xx[2]*($xx[0]*$xx[3]+$xx[1]*$xx[4])/$rxy) / ($r*$r));
                $distSpeed = ($xx[0]*$xx[3] + $xx[1]*$xx[4] + $xx[2]*$xx[5]) / $r;
            }
        }

        return StrategyResult::okFinal([$lon, $lat, $r, $lonSpeed, $latSpeed, $distSpeed]);
    }

    /**
     * Convert equatorial J2000 coordinates to ecliptic J2000
     * Uses obliquity at J2000.0 epoch
     *
     * Ecliptic→Equatorial: y_eq = y_ecl*cos(eps) - z_ecl*sin(eps), z_eq = y_ecl*sin(eps) + z_ecl*cos(eps)
     * Equatorial→Ecliptic (inverse): y_ecl = y_eq*cos(eps) + z_eq*sin(eps), z_ecl = -y_eq*sin(eps) + z_eq*cos(eps)
     */
    private function equatorialToEcliptic(array $equatorial): array
    {
        // Mean obliquity at J2000.0 in radians
        $eps = deg2rad(23.4392911);
        $cosEps = cos($eps);
        $sinEps = sin($eps);

        // Position (inverse rotation by eps around X-axis)
        $x = $equatorial[0];
        $y = $equatorial[1];
        $z = $equatorial[2];

        $xEcl = $x;
        $yEcl = $y * $cosEps + $z * $sinEps;
        $zEcl = -$y * $sinEps + $z * $cosEps;

        // Velocity
        $vx = $equatorial[3];
        $vy = $equatorial[4];
        $vz = $equatorial[5];

        $vxEcl = $vx;
        $vyEcl = $vy * $cosEps + $vz * $sinEps;
        $vzEcl = -$vy * $sinEps + $vz * $cosEps;

        return [$xEcl, $yEcl, $zEcl, $vxEcl, $vyEcl, $vzEcl];
    }

    /**
     * Map Swiss Ephemeris planet ID to JPL body ID
     */
    private function seToJpl(int $ipl): int
    {
        return JplConstants::SE_TO_JPL[$ipl] ?? -1;
    }
}
