<?php

declare(strict_types=1);

namespace Swisseph\Swe\FixedStars;

use Swisseph\Constants;

/**
 * Parser for fixed star catalog CSV records
 *
 * Port of fixstar_cut_string() from sweph.c:6265-6377
 *
 * Parses CSV format from sefstars.txt:
 * Traditional Name,Bayer,Epoch,RA_h,RA_m,RA_s,Dec_d,Dec_m,Dec_s,PM_RA,PM_Dec,RadVel,Parallax,Mag,RA_ICRS,Dec_ICRS
 *
 * Example: "Sirius,alCMa,ICRS,06,45,08.91728,-16,42,58.0171,-546.05,-1223.14,0,-379.21,-1.44,27,16529"
 */
final class FixedStarParser
{
    /** Conversion factor: km/s to AU/century */
    private const KM_S_TO_AU_CTY = 21.095;

    /** Degrees to radians */
    private const DEGTORAD = 0.017453292519943295769;

    /**
     * Parse CSV record into FixedStarData
     *
     * Port of fixstar_cut_string() from sweph.c:6265-6377
     *
     * @param string $srecord CSV record from star file
     * @param string|null &$star Output: full star name ("Traditional,Bayer")
     * @param string|null &$serr Output: error message
     * @return FixedStarData|null Parsed star data, or null on error
     */
    public static function parseRecord(string $srecord, ?string &$star = null, ?string &$serr = null): ?FixedStarData
    {
        $serr = '';

        // Split CSV fields (port of swi_cutstr())
        $cpos = explode(',', $srecord);

        // Trim trailing whitespace from first two fields
        if (isset($cpos[0])) {
            $cpos[0] = rtrim($cpos[0]);
        }
        if (isset($cpos[1])) {
            $cpos[1] = rtrim($cpos[1]);
        }

        // Validate: need at least 14 fields
        if (count($cpos) < 14) {
            if (count($cpos) >= 2) {
                $serr = sprintf("data of star '%s,%s' incomplete", $cpos[0], $cpos[1]);
            } else {
                $truncated = strlen($srecord) > 200 ? substr($srecord, 0, 200) : $srecord;
                $serr = sprintf("invalid line in fixed stars file: '%s'", $truncated);
            }
            return null;
        }

        // Truncate star names to max length (SWI_STAR_LENGTH)
        if (strlen($cpos[0]) > FixedStarData::STAR_LENGTH) {
            $cpos[0] = substr($cpos[0], 0, FixedStarData::STAR_LENGTH);
        }
        if (strlen($cpos[1]) > FixedStarData::STAR_LENGTH - 1) {
            $cpos[1] = substr($cpos[1], 0, FixedStarData::STAR_LENGTH - 1);
        }

        // Build full star name for output
        if ($star !== null) {
            $star = $cpos[0];
            if (strlen($cpos[0]) + strlen($cpos[1]) + 1 < FixedStarData::STAR_LENGTH - 1) {
                $star .= ',' . $cpos[1];
            }
        }

        // Parse numeric fields
        $epoch = (float)$cpos[2];
        $ra_h = (float)$cpos[3];
        $ra_m = (float)$cpos[4];
        $ra_s = (float)$cpos[5];
        $de_d = (float)$cpos[6];
        $sde_d = $cpos[6];  // Keep string for sign checking
        $de_m = (float)$cpos[7];
        $de_s = (float)$cpos[8];
        $ra_pm = (float)$cpos[9];
        $de_pm = (float)$cpos[10];
        $radv = (float)$cpos[11];
        $parall = (float)$cpos[12];
        if ($parall < 0) {
            $parall = -$parall;  // Fix bug like old Rasalgheti
        }
        $mag = (float)$cpos[13];

        /****************************************
         * Position and speed (equinox)
         ****************************************/

        // RA and Dec in degrees
        $ra = ($ra_s / 3600.0 + $ra_m / 60.0 + $ra_h) * 15.0;

        if (str_contains($sde_d, '-')) {
            // Negative declination
            $de = -$de_s / 3600.0 - $de_m / 60.0 + $de_d;
        } else {
            // Positive declination
            $de = $de_s / 3600.0 + $de_m / 60.0 + $de_d;
        }

        // Speed in RA and Dec, degrees per century
        // Check if using old or new star file format
        // Old format (sefstars.txt before SE 2.00): ra_pm in seconds/year, de_pm in arcsec/year
        // New format (sefstars.txt from SE 2.00+): ra_pm in mas/year, de_pm in mas/year, parallax in mas

        // Detection heuristic: if epoch is numeric (1950.0 or 2000.0), use old format
        // if epoch is "ICRS" or similar string, use new format
        // Since we already parsed epoch as float, we need to check the original string
        $isOldStarFile = is_numeric($cpos[2]) && ((float)$cpos[2] === 1950.0 || (float)$cpos[2] === 2000.0);

        if ($isOldStarFile) {
            // Old format
            $ra_pm = $ra_pm * 15 / 3600.0;
            $de_pm = $de_pm / 3600.0;
        } else {
            // New format: milliarcseconds/year to degrees/century
            $ra_pm = $ra_pm / 10.0 / 3600.0;
            $de_pm = $de_pm / 10.0 / 3600.0;
            $parall /= 1000.0;  // mas to arcsec
        }

        // Parallax, convert to degrees
        if ($parall > 1) {
            // Given in arcseconds, convert to degrees
            $parall = 1 / $parall / 3600.0;
        } else {
            // Already in arcseconds
            $parall /= 3600.0;
        }

        // Radial velocity in AU per century
        $radv *= self::KM_S_TO_AU_CTY;

        // Convert to radians
        $ra *= self::DEGTORAD;
        $de *= self::DEGTORAD;
        $ra_pm *= self::DEGTORAD;
        $de_pm *= self::DEGTORAD;
        // Catalogues give proper motion in RA as great circle
        $ra_pm /= cos($de);
        $parall *= self::DEGTORAD;

        // Create FixedStarData object
        $stardata = new FixedStarData();
        $stardata->starname = $cpos[0];
        $stardata->starbayer = $cpos[1];

        // Handle ICRS epoch (represented as 0 in C code)
        if (strtoupper(trim($cpos[2])) === 'ICRS' || strtoupper(trim($cpos[2])) === '2000') {
            $stardata->epoch = 0.0;  // ICRS is represented as epoch 0
        } elseif ($epoch === 2000.0) {
            $stardata->epoch = 2000.0;
        } elseif ($epoch === 1950.0) {
            $stardata->epoch = 1950.0;
        } else {
            $stardata->epoch = $epoch;
        }

        $stardata->ra = $ra;
        $stardata->de = $de;
        $stardata->ramot = $ra_pm;
        $stardata->demot = $de_pm;
        $stardata->parall = $parall;
        $stardata->radvel = $radv;
        $stardata->mag = $mag;

        return $stardata;
    }
}
