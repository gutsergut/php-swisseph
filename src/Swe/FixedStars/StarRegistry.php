<?php

declare(strict_types=1);

namespace Swisseph\Swe\FixedStars;

use Swisseph\Constants;
use Swisseph\State;

/**
 * Registry for in-memory storage of fixed stars catalog
 *
 * Port of load_all_fixed_stars() and related functions from sweph.c:6378-6450
 *
 * Loads all fixed stars from sefstars.txt into memory on first access.
 * Creates multiple index entries per star:
 * - By Bayer/Flamsteed designation (e.g., ",alCMa")
 * - By traditional name if present (e.g., "sirius")
 * - By sequential number (1-based index)
 *
 * Array is sorted by search key (skey) for binary search.
 */
final class StarRegistry
{
    /** @var FixedStarData[] Array of all star records */
    private static array $fixedStars = [];

    /** @var int Number of actual stars (not counting duplicate index entries) */
    private static int $nFixstarsReal = 0;

    /** @var int Number of stars with traditional names */
    private static int $nFixstarsNamed = 0;

    /** @var int Total number of records (including duplicates for different search keys) */
    private static int $nFixstarsRecords = 0;

    /** @var bool Whether stars have been loaded */
    private static bool $loaded = false;

    /** @var bool Whether using old star file format */
    private static bool $isOldStarFile = false;

    /**
     * Load all fixed stars from catalog file into memory
     *
     * Port of load_all_fixed_stars() from sweph.c:6378-6450
     *
     * Loads stars from sefstars.txt (or sefstars_old.txt for old format).
     * Creates index entries:
     * - Every star gets entry with Bayer/Flamsteed as key (prefixed with ",")
     * - Stars with traditional names get additional entry with name as key
     * - Array is sorted by skey for binary search
     *
     * @param string|null &$serr Output: error message
     * @return int OK on success, ERR on error, -2 if already loaded
     */
    public static function loadAll(?string &$serr = null): int
    {
        $serr = '';

        // Already loaded? Return OK, not error
        if (self::$loaded) {
            return Constants::SE_OK;
        }

        // Get ephemeris path
        $ephePath = State::getEphePath();

        // Try to open star file
        $starFile = self::findStarFile($ephePath, $serr);
        if ($starFile === null) {
            return Constants::SE_ERR;
        }

        // Read all lines from file
        $lines = file($starFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            $serr = "error reading fixed star file: {$starFile}";
            return Constants::SE_ERR;
        }

        $nstars = 0;
        $nrecs = 0;
        $nnamed = 0;
        $lastStarbayer = '';

        foreach ($lines as $line) {
            // Skip comment lines
            if (str_starts_with($line, '#')) {
                continue;
            }
            if (trim($line) === '') {
                continue;
            }

            // Parse the record
            $fstdata = FixedStarParser::parseRecord($line, $star, $parseErr);
            if ($fstdata === null) {
                if ($serr !== null) {
                    $serr = $parseErr;
                }
                return Constants::SE_ERR;
            }

            // If star has a traditional name, save it with that name as its search key
            if ($fstdata->starname !== '') {
                $nrecs++;
                $nnamed++;

                // Create copy for traditional name index
                $namedStar = clone $fstdata;
                $namedStar->skey = $fstdata->starname;

                // Remove white spaces from star name
                $namedStar->skey = str_replace(' ', '', $namedStar->skey);

                // Star name to lowercase
                $namedStar->skey = strtolower($namedStar->skey);

                // Save to array
                if (self::saveStarInArray($nrecs, $namedStar, $serr) === Constants::SE_ERR) {
                    return Constants::SE_ERR;
                }
            }

            // Also save it with Bayer designation as search key;
            // only if it has not been saved already
            if ($fstdata->starbayer === $lastStarbayer) {
                continue;
            }

            $nstars++;
            $nrecs++;

            // Create copy for Bayer index
            $bayerStar = clone $fstdata;
            // Prefix with comma (sorts before alphanumeric)
            $bayerStar->skey = ',' . $fstdata->starbayer;

            // Remove white spaces from Bayer name
            $bayerStar->skey = str_replace(' ', '', $bayerStar->skey);

            // Bayer name to lowercase
            $bayerStar->skey = strtolower($bayerStar->skey);

            $lastStarbayer = $fstdata->starbayer;

            if (self::saveStarInArray($nrecs, $bayerStar, $serr) === Constants::SE_ERR) {
                return Constants::SE_ERR;
            }
        }

        // Store counts
        self::$nFixstarsReal = $nstars;
        self::$nFixstarsNamed = $nnamed;
        self::$nFixstarsRecords = $nrecs;

        // Sort array by skey (like qsort in C)
        usort(self::$fixedStars, function (FixedStarData $a, FixedStarData $b): int {
            return strcmp($a->skey, $b->skey);
        });

        self::$loaded = true;

        return Constants::SE_OK;
    }

    /**
     * Find star catalog file
     *
     * Tries sefstars.txt first, then sefstars_old.txt
     *
     * @param string $ephePath Ephemeris path
     * @param string|null &$serr Output: error message
     * @return string|null Full path to star file, or null if not found
     */
    private static function findStarFile(string $ephePath, ?string &$serr): ?string
    {
        // Try new format first
        $newFile = $ephePath . DIRECTORY_SEPARATOR . 'sefstars.txt';
        if (file_exists($newFile) && is_readable($newFile)) {
            self::$isOldStarFile = false;
            return $newFile;
        }

        // Try old format
        $oldFile = $ephePath . DIRECTORY_SEPARATOR . 'sefstars_old.txt';
        if (file_exists($oldFile) && is_readable($oldFile)) {
            self::$isOldStarFile = true;
            return $oldFile;
        }

        // Not found
        if ($serr !== null) {
            $serr = "fixed star file not found in {$ephePath}";
        }

        return null;
    }

    /**
     * Add star to internal array
     *
     * Port of save_star_in_struct() from sweph.c:6232-6245
     *
     * @param int $nrecs Record number (1-based)
     * @param FixedStarData $fst Star data to save
     * @param string|null &$serr Output: error message
     * @return int OK or ERR
     */
    private static function saveStarInArray(int $nrecs, FixedStarData $fst, ?string &$serr): int
    {
        // In PHP we don't need realloc, just append to array
        self::$fixedStars[] = $fst;

        return Constants::SE_OK;
    }

    /**
     * Get all loaded stars
     *
     * @return FixedStarData[]
     */
    public static function getAll(): array
    {
        return self::$fixedStars;
    }

    /**
     * Get total number of stars (not counting duplicate indices)
     *
     * @return int
     */
    public static function getRealCount(): int
    {
        return self::$nFixstarsReal;
    }

    /**
     * Get number of stars with traditional names
     *
     * @return int
     */
    public static function getNamedCount(): int
    {
        return self::$nFixstarsNamed;
    }

    /**
     * Get total number of records (including duplicate indices)
     *
     * @return int
     */
    public static function getRecordsCount(): int
    {
        return self::$nFixstarsRecords;
    }

    /**
     * Check if using old star file format
     *
     * @return bool
     */
    public static function isOldFormat(): bool
    {
        return self::$isOldStarFile;
    }

    /**
     * Check if stars are loaded
     *
     * @return bool
     */
    public static function isLoaded(): bool
    {
        return self::$loaded;
    }

    /**
     * Search for a star in the catalog
     *
     * Port of search_star_in_list() from sweph.c:6728-6798
     *
     * Supports three search modes:
     * 1. Sequential number: "1", "2", etc (1-based)
     * 2. Wildcard traditional name: "sirius%" (matches prefix)
     * 3. Exact match: "sirius" or ",alCMa" (traditional name or Bayer with comma prefix)
     *
     * @param string $sstar Search string
     * @param string|null &$serr Output: error message
     * @return FixedStarData|null Star data if found, null if not found
     */
    public static function search(string $sstar, ?string &$serr = null): ?FixedStarData
    {
        $serr = '';
        $starNr = 0;
        $isBayer = false;

        // Check if search string starts with comma (Bayer designation)
        if (str_starts_with($sstar, ',')) {
            $isBayer = true;
        }
        // Check if search string is a number (sequential star number)
        elseif (ctype_digit($sstar)) {
            $starNr = (int) $sstar;
        }
        // Check if there's a comma inside (convert to Bayer format)
        else {
            $commaPos = strpos($sstar, ',');
            if ($commaPos !== false) {
                $sstar = substr($sstar, $commaPos);
                $isBayer = true;
            }
        }

        // Search by sequential number
        if ($starNr > 0) {
            if ($starNr > self::$nFixstarsReal) {
                if ($serr !== null) {
                    $serr = "error, swe_fixstar(): sequential fixed star number {$starNr} is not available";
                }
                return null;
            }

            // Sequential numbers are 1-based, but first n_fixstars_real entries
            // in array are Bayer designations (with comma prefix)
            return self::$fixedStars[$starNr - 1];
        }

        // Wildcard search (traditional name with % at end)
        if (!$isBayer) {
            $percentPos = strpos($sstar, '%');
            if ($percentPos !== false) {
                // Check that % is at the end
                if ($percentPos !== strlen($sstar) - 1) {
                    if ($serr !== null) {
                        $serr = "error, swe_fixstar(): invalid search string {$sstar}";
                    }
                    return null;
                }

                // Remove % and search for prefix match
                $searchKey = substr($sstar, 0, -1);
                $searchLen = strlen($searchKey);

                // Traditional names start at index n_fixstars_real
                $startIdx = self::$nFixstarsReal;
                $endIdx = $startIdx + self::$nFixstarsNamed;

                for ($i = $startIdx; $i < $endIdx; $i++) {
                    if (strncmp(self::$fixedStars[$i]->skey, $searchKey, $searchLen) === 0) {
                        return self::$fixedStars[$i];
                    }
                }

                if ($serr !== null) {
                    $serr = "error, swe_fixstar(): star search string {$sstar} did not match";
                }
                return null;
            }
        }

        // Exact match with binary search
        $searchKey = $sstar;

        if ($isBayer) {
            // Bayer designations are in first n_fixstars_real entries
            $startIdx = 0;
            $count = self::$nFixstarsReal;
        } else {
            // Traditional names start at n_fixstars_real
            $startIdx = self::$nFixstarsReal;
            $count = self::$nFixstarsNamed;
        }

        $result = self::binarySearch($searchKey, $startIdx, $count);

        if ($result === null && $serr !== null) {
            $serr = "error, swe_fixstar(): could not find star name {$sstar}";
        }

        return $result;
    }

    /**
     * Binary search for star by search key
     *
     * Port of bsearch() call in sweph.c:6789-6792 with fstar_node_compare
     *
     * @param string $searchKey Key to search for
     * @param int $startIdx Start index in array
     * @param int $count Number of elements to search
     * @return FixedStarData|null Star data if found, null otherwise
     */
    private static function binarySearch(string $searchKey, int $startIdx, int $count): ?FixedStarData
    {
        // Normalize search key (lowercase, no spaces)
        $searchKey = strtolower(str_replace(' ', '', $searchKey));

        $left = $startIdx;
        $right = $startIdx + $count - 1;

        while ($left <= $right) {
            $mid = (int) (($left + $right) / 2);
            $cmp = strcmp($searchKey, self::$fixedStars[$mid]->skey);

            if ($cmp === 0) {
                return self::$fixedStars[$mid];
            } elseif ($cmp < 0) {
                $right = $mid - 1;
            } else {
                $left = $mid + 1;
            }
        }

        return null;
    }

    /**
     * Reset registry (for testing)
     */
    public static function reset(): void
    {
        self::$fixedStars = [];
        self::$nFixstarsReal = 0;
        self::$nFixstarsNamed = 0;
        self::$nFixstarsRecords = 0;
        self::$loaded = false;
        self::$isOldStarFile = false;
    }
}
