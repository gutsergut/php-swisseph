<?php

declare(strict_types=1);

namespace Swisseph\Swe\FixedStars;

use Swisseph\Constants;

/**
 * Legacy fixed star calculation from text record.
 * Port of swi_fixstar_calc_from_record() from sweph.c:7670-7948
 *
 * This function:
 * 1. Parses text record into FixedStarData
 * 2. Calls StarCalculator::calculate()
 *
 * For new code, use StarCalculator::calculate() directly.
 *
 * NO SIMPLIFICATIONS - Full C code fidelity maintained.
 */
final class LegacyStarCalculator
{
    /**
     * Calculate fixed star position from text record.
     * Port of swi_fixstar_calc_from_record() from sweph.c:7670-7948
     *
     * C signature:
     * static int32 swi_fixstar_calc_from_record(char *srecord, double tjd,
     *                                           int32 iflag, char *star,
     *                                           double *xx, char *serr)
     *
     * @param string $srecord Raw CSV record from sefstars.txt
     * @param float $tjd Julian Day (ET)
     * @param int $iflag Calculation flags
     * @param string &$star Output: formatted "tradname,nomenclature"
     * @param array &$xx Output: 6 doubles [lon/ra, lat/dec, dist, dlon, dlat, ddist]
     * @param string|null &$serr Error message
     * @return int iflag on success, ERR on error
     */
    public static function calculateFromRecord(
        string $srecord,
        float $tjd,
        int $iflag,
        string &$star,
        array &$xx,
        ?string &$serr = null
    ): int {
        $serr = '';

        // C: retc = fixstar_cut_string(srecord, star, &stardata, serr);
        $stardata = FixedStarParser::parseRecord($srecord, $star, $serr);
        if ($stardata === null) {
            return Constants::SE_ERR;
        }

        // C: rest of the function is identical to fixstar_calc_from_struct
        // Just delegate to StarCalculator
        return StarCalculator::calculate($stardata, $tjd, $iflag, $star, $xx, $serr);
    }
}
