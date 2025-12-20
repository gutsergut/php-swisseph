<?php

declare(strict_types=1);

namespace Swisseph\Swe\Planets;

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\Swe\Jpl\JplConstants;
use Swisseph\Swe\Jpl\JplEphemeris;

/**
 * JPL Ephemeris strategy for planet calculations
 * Uses DE405, DE431, DE440, DE441, etc.
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
                return new StrategyResult(
                    Constants::SE_ERR,
                    'barycentric_j2000',
                    [],
                    $serr ?? 'Could not open JPL ephemeris file'
                );
            }

            $this->initialized = true;
        }

        // Map SE planet to JPL body index
        $jplTarget = $this->seToJpl($ipl);
        if ($jplTarget < 0) {
            return new StrategyResult(
                Constants::SE_ERR,
                'barycentric_j2000',
                [],
                sprintf('Planet %d not supported by JPL ephemeris', $ipl)
            );
        }

        // Determine center
        $isHeliocentric = (bool)($iflag & Constants::SEFLG_HELCTR);
        $isBarycentric = (bool)($iflag & Constants::SEFLG_BARYCTR);

        if ($isHeliocentric) {
            $jplCenter = JplConstants::J_SUN;
        } elseif ($isBarycentric) {
            $jplCenter = JplConstants::J_SBARY;
        } else {
            // Geocentric: need special handling
            $jplCenter = JplConstants::J_EARTH;
        }

        // Special case for Moon: already geocentric in JPL
        if ($ipl === Constants::SE_MOON && !$isHeliocentric && !$isBarycentric) {
            $jplCenter = JplConstants::J_EARTH;
        }

        // Get position from JPL ephemeris
        $rrd = [];
        $serr = null;
        $ret = $this->jpl->pleph($jdTt, $jplTarget, $jplCenter, $rrd, $serr);

        if ($ret !== JplConstants::OK) {
            return new StrategyResult(
                Constants::SE_ERR,
                'barycentric_j2000',
                [],
                $serr ?? 'JPL ephemeris calculation error'
            );
        }

        // JPL returns equatorial J2000 coordinates - we need ecliptic J2000
        // Convert equatorial to ecliptic
        $x = $this->equatorialToEcliptic($rrd);

        return new StrategyResult(
            Constants::SE_OK,
            'barycentric_j2000',
            $x,
            null
        );
    }

    /**
     * Map Swiss Ephemeris planet index to JPL body index
     */
    private function seToJpl(int $ipl): int
    {
        return match ($ipl) {
            Constants::SE_SUN => JplConstants::J_SUN,
            Constants::SE_MOON => JplConstants::J_MOON,
            Constants::SE_MERCURY => JplConstants::J_MERCURY,
            Constants::SE_VENUS => JplConstants::J_VENUS,
            Constants::SE_MARS => JplConstants::J_MARS,
            Constants::SE_JUPITER => JplConstants::J_JUPITER,
            Constants::SE_SATURN => JplConstants::J_SATURN,
            Constants::SE_URANUS => JplConstants::J_URANUS,
            Constants::SE_NEPTUNE => JplConstants::J_NEPTUNE,
            Constants::SE_PLUTO => JplConstants::J_PLUTO,
            Constants::SE_EARTH => JplConstants::J_EARTH,
            default => -1,
        };
    }

    /**
     * Convert equatorial J2000 coordinates to ecliptic J2000
     * Uses obliquity at J2000.0 epoch
     */
    private function equatorialToEcliptic(array $equatorial): array
    {
        // Mean obliquity at J2000.0 in radians
        $eps = deg2rad(23.4392911);
        $cosEps = cos($eps);
        $sinEps = sin($eps);

        // Position
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
}
