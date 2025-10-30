<?php

namespace Swisseph\SwephFile;

/**
 * Port of struct file_data from sweph.h
 *
 * Contains information about an open Swiss Ephemeris file
 */
final class FileData
{
    /** Ephemeris file name */
    public string $fnam = '';

    /** Version number of file */
    public int $fversion = 0;

    /** Asteroid name (if asteroid file) */
    public string $astnam = '';

    /** DE number of JPL ephemeris which this file is derived from */
    public int $sweph_denum = 0;

    /** Ephemeris file pointer (resource or null) */
    public $fptr = null;

    /** File may be used from this date */
    public float $tfstart = 0.0;

    /** Through this date */
    public float $tfend = 0.0;

    /** Byte reorder flag and little/bigendian flag */
    public int $iflg = 0;

    /** How many planets in file */
    public int $npl = 0;

    /** Planet numbers (max 50 planets per file - SEI_FILE_NMAXPLAN) */
    public array $ipl = [];

    public function __construct()
    {
        // Initialize ipl array with 50 zeros
        $this->ipl = array_fill(0, 50, 0);
    }
}
