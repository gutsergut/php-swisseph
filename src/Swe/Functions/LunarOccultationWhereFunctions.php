<?php

declare(strict_types=1);

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;
use Swisseph\Swe\Eclipses\EclipseCalculator;

use function swe_deltat_ex;

/**
 * Lunar occultation "where" functions
 * Port of swe_lun_occult_where() from swecl.c:606-630
 */
final class LunarOccultationWhereFunctions
{
    /**
     * Find geographic location of maximum occultation
     *
     * Port of swe_lun_occult_where() from swecl.c:606-630
     *
     * @param float $tjdUT Julian day number (UT)
     * @param int $ipl Planet number (SE_*)
     * @param string|null $starname Fixed star name (or null for planet)
     * @param int $ifl Ephemeris flag
     * @param array &$geopos Geographic position [longitude, latitude, altitude] (output)
     * @param array &$attr Attributes array (output)
     * @param string|null &$serr Error string (output)
     * @return int Return flags (SE_ECL_*)
     */
    public static function lunOccultWhere(
        float $tjdUT,
        int $ipl,
        ?string $starname,
        int $ifl,
        array &$geopos,
        array &$attr,
        ?string &$serr
    ): int {
        if ($ipl < 0) {
            $ipl = 0;
        }

        // Filter ephemeris flags
        $ifl &= Constants::SEFLG_EPHMASK;

        // Set tidal acceleration (swi_set_tid_acc in C - skipped for now)

        // Pluto as asteroid 134340 is treated as main body SE_PLUTO
        if ($ipl === Constants::SE_AST_OFFSET + 134340) {
            $ipl = Constants::SE_PLUTO;
        }

        // Get geographic position of eclipse maximum
        $dcore = [];
        $retflag = EclipseCalculator::eclipseWhere($tjdUT, $ipl, $starname, $ifl, $geopos, $dcore, $serr);

        if ($retflag < 0) {
            return $retflag;
        }

        // Calculate eclipse attributes at that location
        $retflag2 = EclipseCalculator::eclipseHow(
            $tjdUT,
            $ipl,
            $starname,
            $ifl,
            $geopos[0],
            $geopos[1],
            0.0, // altitude
            $attr,
            $serr
        );

        if ($retflag2 === Constants::SE_ERR) {
            return $retflag2;
        }

        // Set core shadow diameter in attr[3]
        $attr[3] = $dcore[0];

        return $retflag;
    }
}
