<?php

namespace Swisseph\SwephFile;

use Swisseph\Constants;

/**
 * Port of C swi_gen_filename() from swephlib.c:3610-3693
 *
 * Generates Swiss Ephemeris file name based on Julian day and planet index.
 *
 * File naming convention:
 * - Planets: sepl_XX.se1 (where XX = century: 00, 06, 12, 18, 24, m06, m12, etc.)
 * - Moon: semo_XX.se1
 * - Asteroids (main belt): seas_XX.se1
 * - Numbered asteroids: ast0/se00001.se1, ast1/se01234.se1, etc.
 * - Planetary moons: sat/sepm123.se1
 *
 * Century files cover 600-year periods (NCTIES=6):
 * - sepl_18.se1: 1800-2399 (actual: often starts earlier like 1500)
 * - sepl_00.se1: 0-599 AD
 * - sepl_m06.se1: 600-1 BC
 * - sepl_m12.se1: 1200-601 BC
 */
final class FilenameGenerator
{
    /**
     * Number of centuries per file (typically 6 = 600 years)
     * From C: #define NCTIES 6
     */
    private const NCTIES = 6;

    /**
     * Gregorian calendar start: 1582-10-15 = JD 2299160.5
     * But C code uses 1600 as threshold: JD 2305447.5
     */
    private const GREGORIAN_START_JD = 2305447.5;

    /**
     * Directory separator
     */
    private const DIR_GLUE = '/';

    /**
     * File extension
     */
    private const FILE_SUFFIX = 'se1';

    /**
     * Generate ephemeris filename for given Julian day and planet
     *
     * Port of C function swi_gen_filename() from swephlib.c:3610
     *
     * @param float $tjd Julian day
     * @param int $ipli Internal planet index (SEI_*)
     * @return string Filename (e.g., "sepl_18.se1", "semo_18.se1", "ast0/se00433.se1")
     */
    public static function generate(float $tjd, int $ipli): string
    {
        // Special cases: asteroids and planetary moons
        // C code lines 3643-3653
        if ($ipli > Constants::SE_PLMOON_OFFSET && $ipli < Constants::SE_AST_OFFSET) {
            // Planetary moons: sat/sepm123.se1
            $moonNum = $ipli;
            return sprintf('sat%ssepm%d.%s', self::DIR_GLUE, $moonNum, self::FILE_SUFFIX);
        }

        if ($ipli > Constants::SE_AST_OFFSET) {
            // Numbered asteroids: ast0/se00001.se1, ast1/se01234.se1, etc.
            $astNum = $ipli - Constants::SE_AST_OFFSET;
            $astDir = (int)($astNum / 1000);

            if ($astNum > 99999) {
                // 6-digit format for asteroids > 99999
                return sprintf('ast%d%ss%06d.%s', $astDir, self::DIR_GLUE, $astNum, self::FILE_SUFFIX);
            } else {
                // 5-digit format
                return sprintf('ast%d%sse%05d.%s', $astDir, self::DIR_GLUE, $astNum, self::FILE_SUFFIX);
            }
        }

        // Determine base filename prefix
        // C code lines 3619-3642
        $prefix = match($ipli) {
            SwephConstants::SEI_MOON => 'semo',
            SwephConstants::SEI_EMB,
            SwephConstants::SEI_MERCURY,
            SwephConstants::SEI_VENUS,
            SwephConstants::SEI_MARS,
            SwephConstants::SEI_JUPITER,
            SwephConstants::SEI_SATURN,
            SwephConstants::SEI_URANUS,
            SwephConstants::SEI_NEPTUNE,
            SwephConstants::SEI_PLUTO,
            SwephConstants::SEI_SUNBARY => 'sepl',
            SwephConstants::SEI_CERES,
            SwephConstants::SEI_PALLAS,
            SwephConstants::SEI_JUNO,
            SwephConstants::SEI_VESTA,
            SwephConstants::SEI_CHIRON,
            SwephConstants::SEI_PHOLUS => 'seas',
            default => 'sepl', // Default to planets file
        };

        // For asteroids/moons, only one file covers 3000 BC - 3000 AD
        // Already handled above, but keeping this comment for clarity

        // Convert Julian day to calendar date
        // C code lines 3655-3664
        $gregflag = $tjd >= self::GREGORIAN_START_JD;
        [$jyear, $jmon, $jday, $jut] = self::revjul($tjd, $gregflag);

        // Calculate century of file containing tjd
        // C code lines 3666-3674
        $sgn = $jyear < 0 ? -1 : 1;
        $icty = (int)($jyear / 100);

        if ($sgn < 0 && $jyear % 100 !== 0) {
            $icty -= 1;
        }

        // Round down to start of NCTIES-century period
        while ($icty % self::NCTIES !== 0) {
            $icty--;
        }

        // Build filename: prefix + ("m" for BC or "_" for AD) + century + extension
        // C code lines 3681-3686
        if ($icty < 0) {
            $fname = $prefix . 'm' . sprintf('%02d', abs($icty)) . '.' . self::FILE_SUFFIX;
        } else {
            $fname = $prefix . '_' . sprintf('%02d', $icty) . '.' . self::FILE_SUFFIX;
        }

        return $fname;
    }

    /**
     * Reverse Julian day to calendar date
     *
     * Port of swe_revjul() - converts JD to Gregorian or Julian calendar
     *
     * @param float $jd Julian day
     * @param bool $gregflag TRUE for Gregorian, FALSE for Julian calendar
     * @return array [year, month, day, hour] where hour is decimal (0.0-24.0)
     */
    private static function revjul(float $jd, bool $gregflag): array
    {
        // This is a simplified port - full implementation in swephlib.c
        // For now, use PHP DateTime which handles Gregorian calendar

        // Adjust for Julian day epoch (JD 0 = 4713 BC January 1, 12:00 UT)
        $unixTime = ($jd - 2440587.5) * 86400; // Convert JD to Unix timestamp

        $dt = new \DateTime('@' . (int)$unixTime, new \DateTimeZone('UTC'));

        $year = (int)$dt->format('Y');
        $month = (int)$dt->format('m');
        $day = (int)$dt->format('d');
        $hour = (float)$dt->format('H') + (float)$dt->format('i') / 60.0 + (float)$dt->format('s') / 3600.0;

        // For BC dates (year < 1), astronomical year numbering: 0 = 1 BC, -1 = 2 BC, etc.
        // PHP DateTime doesn't handle BC natively, so we need special handling for old dates
        if ($jd < 1721425.5) { // Before 0 AD
            // Use astronomical year numbering
            $year = -(int)abs($year) + 1;
        }

        return [$year, $month, $day, $hour];
    }

    /**
     * Generate list of alternative filenames for numbered asteroids.
     *
     * Swiss Ephemeris asteroid files come in two versions:
     * - Long version: se00433.se1 (covers 6000 years: 3000 BCE - 3000 CE)
     * - Short version: se00433s.se1 (covers 600 years: 1500 - 2100 CE)
     *
     * This method returns both variants so the reader can try fallback.
     *
     * @param int $ipli Internal planet index (must be > SE_AST_OFFSET)
     * @return array<string> List of possible filenames, in order of preference
     */
    public static function generateAsteroidFilenames(int $ipli): array
    {
        if ($ipli <= Constants::SE_AST_OFFSET) {
            return [];
        }

        $astNum = $ipli - Constants::SE_AST_OFFSET;
        $astDir = (int)($astNum / 1000);
        $filenames = [];

        if ($astNum > 99999) {
            // 6-digit format for asteroids > 99999
            // Long version: s123456.se1
            $filenames[] = sprintf('ast%d%ss%06d.%s', $astDir, self::DIR_GLUE, $astNum, self::FILE_SUFFIX);
            // Short version: s123456s.se1 (with 's' suffix before extension)
            $filenames[] = sprintf('ast%d%ss%06ds.%s', $astDir, self::DIR_GLUE, $astNum, self::FILE_SUFFIX);
        } else {
            // 5-digit format
            // Long version: se00433.se1
            $filenames[] = sprintf('ast%d%sse%05d.%s', $astDir, self::DIR_GLUE, $astNum, self::FILE_SUFFIX);
            // Short version: se00433s.se1 (with 's' suffix before extension)
            $filenames[] = sprintf('ast%d%sse%05ds.%s', $astDir, self::DIR_GLUE, $astNum, self::FILE_SUFFIX);
        }

        return $filenames;
    }

    /**
     * Generate list of alternative filenames for planetary moons.
     *
     * Planetary moon files are typically stored in sat/ subdirectory:
     * - Primary: sat/sepm9501.se1 (in sat/ subdirectory)
     * - Fallback: sepm9501.se1 (in main ephemeris directory)
     *
     * Per C sweph.c:2193-2196, if file not found in sat/, try without the directory.
     *
     * @param int $ipli Internal planet index (must be > SE_PLMOON_OFFSET && < SE_AST_OFFSET)
     * @return array<string> List of possible filenames, in order of preference
     */
    public static function generatePlanetaryMoonFilenames(int $ipli): array
    {
        if ($ipli <= Constants::SE_PLMOON_OFFSET || $ipli >= Constants::SE_AST_OFFSET) {
            return [];
        }

        $filenames = [];

        // Primary: in sat/ subdirectory (as per swi_gen_filename)
        $filenames[] = sprintf('sat%ssepm%d.%s', self::DIR_GLUE, $ipli, self::FILE_SUFFIX);

        // Fallback: in main ephemeris directory (without sat/)
        $filenames[] = sprintf('sepm%d.%s', $ipli, self::FILE_SUFFIX);

        return $filenames;
    }
}
