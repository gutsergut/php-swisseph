<?php

declare(strict_types=1);

namespace Swisseph\Domain\Heliacal;

use Swisseph\Swe;

/**
 * Magnitude and object determination functions for heliacal calculations
 * Port from swehel.c lines 306-1135
 */
class HeliacalMagnitude
{
    /**
     * Determine object type from name
     * Port from swehel.c:306-333
     *
     * Identifies if object is a planet, Moon, or asteroid by name
     *
     * @param string $ObjectName Object name (planet name, "moon", or asteroid number)
     * @return int Planet ID or -1 if not recognized (use as fixed star)
     */
    public static function DeterObject(string $ObjectName): int
    {
        $s = strtolower($ObjectName);

        if (str_starts_with($s, 'sun')) {
            return Swe::SE_SUN;
        }
        if (str_starts_with($s, 'venus')) {
            return Swe::SE_VENUS;
        }
        if (str_starts_with($s, 'mars')) {
            return Swe::SE_MARS;
        }
        if (str_starts_with($s, 'mercur')) {
            return Swe::SE_MERCURY;
        }
        if (str_starts_with($s, 'jupiter')) {
            return Swe::SE_JUPITER;
        }
        if (str_starts_with($s, 'saturn')) {
            return Swe::SE_SATURN;
        }
        if (str_starts_with($s, 'uranus')) {
            return Swe::SE_URANUS;
        }
        if (str_starts_with($s, 'neptun')) {
            return Swe::SE_NEPTUNE;
        }
        if (str_starts_with($s, 'moon')) {
            return Swe::SE_MOON;
        }

        // Check if it's an asteroid number
        $ipl = intval($s);
        if ($ipl > 0) {
            return $ipl + Swe::SE_AST_OFFSET;
        }

        // Not a planet - will be treated as fixed star
        return -1;
    }

    /**
     * Calculate apparent magnitude of object
     * Port from swehel.c:1107-1128
     *
     * Determines magnitude using:
     * - swe_pheno_ut() for planets
     * - swe_fixstar_mag() for fixed stars
     *
     * @param float $JDNDaysUT Julian Day Number UT
     * @param array $dgeo Geographic location [longitude, latitude, altitude]
     * @param string $ObjectName Object name
     * @param int $helflag Heliacal flags
     * @param float &$dmag Output: magnitude value
     * @param string &$serr Error message output
     * @return int OK or ERR
     */
    public static function Magnitude(
        float $JDNDaysUT,
        array $dgeo,
        string $ObjectName,
        int $helflag,
        float &$dmag,
        string &$serr
    ): int {
        $epheflag = $helflag & (Swe::SEFLG_JPLEPH | Swe::SEFLG_SWIEPH | Swe::SEFLG_MOSEPH);
        $dmag = -99.0;

        $Planet = self::DeterObject($ObjectName);
        $iflag = Swe::SEFLG_TOPOCTR | Swe::SEFLG_EQUATORIAL | $epheflag;

        if (!($helflag & HeliacalConstants::SE_HELFLAG_HIGH_PRECISION)) {
            $iflag |= Swe::SEFLG_NONUT | Swe::SEFLG_TRUEPOS;
        }

        if ($Planet != -1) {
            // Planet or Moon - use phenomena calculation
            Swe::swe_set_topo($dgeo[0], $dgeo[1], $dgeo[2]);

            $result = Swe::swe_pheno_ut($JDNDaysUT, $Planet, $iflag, $serr);
            if ($result['rc'] == Swe::ERR) {
                return Swe::ERR;
            }

            // Magnitude is 5th element (index 4) of pheno array
            $dmag = $result['attr'][4];
        } else {
            // Fixed star - use fixstar_mag
            $result = Swe::swe_fixstar_mag($ObjectName, $serr);
            if ($result['rc'] == Swe::ERR) {
                return Swe::ERR;
            }

            $dmag = $result['mag'];
        }

        return Swe::OK;
    }
}
