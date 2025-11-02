<?php

namespace Swisseph\SwephFile;

use Swisseph\Constants;

/**
 * Swiss Ephemeris file reader
 *
 * Port of sweph.c file reading functions for .se1 ephemeris files.
 * Implements binary file reading, byte order handling, and Chebyshev interpolation.
 */
final class SwephReader
{
    /** Test value for endianness detection: 0x616263 = "abc" from sweph.h:183 */
    private const SEI_FILE_TEST_ENDIAN = 0x616263;

    /** Current file position marker */
    private const SEI_CURR_FPOS = -1;

    /**
     * Open and read header of a Swiss Ephemeris file
     *
     * Port of read_const() from sweph.c:4509
     *
     * @param int $ifno File index (SEI_FILE_PLANET, etc.)
     * @param string $fname File name
     * @param string $ephepath Ephemeris path
     * @param string|null &$serr Error message
     * @return bool True on success, false on error
     */
    public static function openAndReadHeader(int $ifno, string $fname, string $ephepath, ?string &$serr): bool
    {
        $swed = SwedState::getInstance();
        $fdp = &$swed->fidat[$ifno];

        // Try to open file
        $fullpath = self::findFile($fname, $ephepath);
        if ($fullpath === null) {
            $serr = "Ephemeris file '$fname' not found in path: $ephepath";
            return false;
        }

        $fp = fopen($fullpath, 'rb');
        if ($fp === false) {
            $serr = "Cannot open ephemeris file: $fullpath";
            return false;
        }

        $fdp->fptr = $fp;
        $fdp->fnam = basename($fullpath);

        // Read header and constants
        try {
            return self::readConst($ifno, $serr);
        } catch (\Exception $e) {
            $serr = "Error reading file header: " . $e->getMessage();
            if ($fdp->fptr !== null) {
                fclose($fdp->fptr);
                $fdp->fptr = null;
            }
            return false;
        }
    }

    /**
     * Find ephemeris file in search path
     */
    private static function findFile(string $fname, string $ephepath): ?string
    {
        // Split path by semicolon (or colon on Unix, but not on Windows drive letters)
        // On Windows, C:\path contains : which should not be split
        if (DIRECTORY_SEPARATOR === '\\') {
            // Windows: only split by semicolon
            $paths = explode(';', $ephepath);
        } else {
            // Unix: split by semicolon or colon
            $paths = preg_split('/[;:]/', $ephepath);
        }

        foreach ($paths as $dir) {
            $dir = trim($dir);
            if (empty($dir)) continue;

            $fullpath = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fname;
            if (file_exists($fullpath)) {
                return $fullpath;
            }
        }

        // Try current directory
        if (file_exists($fname)) {
            return $fname;
        }

        return null;
    }

    /**
     * Read file constants and header
     *
     * Port of read_const() from sweph.c:4509
     */
    private static function readConst(int $ifno, ?string &$serr): bool
    {
        $swed = SwedState::getInstance();
        $fdp = &$swed->fidat[$ifno];
        $fp = $fdp->fptr;

        // Read version number (first line)
        $line = fgets($fp);
        if ($line === false || strpos($line, "\r\n") === false) {
            $serr = "File damaged: cannot read version";
            return false;
        }

        preg_match('/\d+/', $line, $matches);
        if (empty($matches)) {
            $serr = "File damaged: no version number";
            return false;
        }
        $fdp->fversion = (int)$matches[0];

        // Read filename (second line) - skip validation for now
        fgets($fp);

        // Read copyright (third line)
        fgets($fp);

        // For asteroid files, read orbital elements (fourth line) - skip for now
        if ($ifno == SwephConstants::SEI_FILE_ANY_AST) {
            fgets($fp);
        }

        // Read test endian value (4 bytes)
        $testendian = self::readInt32($fp);

        // Determine byte order
        list($freord, $fendian) = self::determineByteOrder($testendian, $serr);
        if ($freord === null) {
            return false;
        }
        $fdp->iflg = $freord | $fendian;        // Read and verify file length
        $lng = self::doFread($fp, 4, self::SEI_CURR_FPOS, $freord, $fendian);
        $fpos = ftell($fp);
        fseek($fp, 0, SEEK_END);
        $flen = ftell($fp);
        if ($lng != $flen) {
            $serr = "File damaged: incorrect file length";
            return false;
        }

        // Read DE number
        $fdp->sweph_denum = self::doFread($fp, 4, $fpos, $freord, $fendian);

        // Read start and end epochs
        $fdp->tfstart = self::doFread($fp, 8, self::SEI_CURR_FPOS, $freord, $fendian);
        $fdp->tfend = self::doFread($fp, 8, self::SEI_CURR_FPOS, $freord, $fendian);

        // Read number of planets in file
        $nplan = self::doFread($fp, 2, self::SEI_CURR_FPOS, $freord, $fendian);

        $nbytes_ipl = 2;
        if ($nplan > 256) {
            $nbytes_ipl = 4;
            $nplan %= 256;
        }

        if ($nplan < 1 || $nplan > 20) {
            $serr = "File damaged: invalid planet count";
            return false;
        }
        $fdp->npl = $nplan;

        // Read planet numbers
        for ($i = 0; $i < $nplan; $i++) {
            $fdp->ipl[$i] = self::doFread($fp, $nbytes_ipl, self::SEI_CURR_FPOS, $freord, $fendian);
        }

        // Skip asteroid name field for now
        if ($ifno == SwephConstants::SEI_FILE_ANY_AST) {
            fread($fp, 30);
        }

        // Skip CRC check for now (would need swi_crc32 port)
        $fpos = ftell($fp);
        fseek($fp, $fpos + 4, SEEK_SET);

        // Read general constants (clight, aunit, helgravconst, ratme, sunradius)
        $doubles = [];
        for ($i = 0; $i < 5; $i++) {
            $doubles[$i] = self::doFread($fp, 8, self::SEI_CURR_FPOS, $freord, $fendian);
        }
        $swed->gcdat->clight = $doubles[0];
        $swed->gcdat->aunit = $doubles[1];
        $swed->gcdat->helgravconst = $doubles[2];
        $swed->gcdat->ratme = $doubles[3];
        $swed->gcdat->sunradius = $doubles[4];

        // Read constants for each planet
        for ($kpl = 0; $kpl < $fdp->npl; $kpl++) {
            $ipli = $fdp->ipl[$kpl];

            // Get planet data pointer
            if ($ipli >= \Swisseph\Constants::SE_AST_OFFSET) {
                $pdp = &$swed->pldat[SwephConstants::SEI_ANYBODY];
            } else {
                $pdp = &$swed->pldat[$ipli];
            }

            $pdp->ibdy = $ipli;

            // Read planet constants
            $pdp->lndx0 = self::doFread($fp, 4, self::SEI_CURR_FPOS, $freord, $fendian);
            $pdp->iflg = self::doFread($fp, 1, self::SEI_CURR_FPOS, $freord, $fendian);
            $pdp->ncoe = self::doFread($fp, 1, self::SEI_CURR_FPOS, $freord, $fendian);

            if (getenv('DEBUG_OSCU') && $ipli == SwephConstants::SEI_JUPITER) {
                error_log(sprintf("DEBUG openAndReadHeader: ipli=%d, iflg=0x%X, ncoe=%d", $ipli, $pdp->iflg, $pdp->ncoe));
            }

            $lng = self::doFread($fp, 4, self::SEI_CURR_FPOS, $freord, $fendian);
            $pdp->rmax = $lng / 1000.0;

            // Read 10 doubles: tfstart, tfend, dseg, telem, prot, dprot, qrot, dqrot, peri, dperi
            $doubles = [];
            for ($i = 0; $i < 10; $i++) {
                $doubles[$i] = self::doFread($fp, 8, self::SEI_CURR_FPOS, $freord, $fendian);
            }

            $pdp->tfstart = $doubles[0];
            $pdp->tfend = $doubles[1];
            $pdp->dseg = $doubles[2];
            $pdp->nndx = (int)(($doubles[1] - $doubles[0] + 0.1) / $doubles[2]);
            $pdp->telem = $doubles[3];
            $pdp->prot = $doubles[4];
            $pdp->dprot = $doubles[5];
            $pdp->qrot = $doubles[6];
            $pdp->dqrot = $doubles[7];
            $pdp->peri = $doubles[8];
            $pdp->dperi = $doubles[9];

            // If reference ellipse is used, read its coefficients
            if ($pdp->iflg & SwephConstants::SEI_FLG_ELLIPSE) {
                $pdp->refep = [];
                for ($i = 0; $i < 2 * $pdp->ncoe; $i++) {
                    $pdp->refep[$i] = self::doFread($fp, 8, self::SEI_CURR_FPOS, $freord, $fendian);
                }
            }
        }

        return true;
    }

    /**
     * Read integer from file (raw binary, little-endian)
     */
    private static function readInt32($fp): int
    {
        $data = fread($fp, 4);
        if ($data === false || strlen($data) < 4) {
            throw new \RuntimeException("Cannot read 4 bytes");
        }
        // unpack as little-endian signed int32
        $unpacked = unpack('V', $data); // V = unsigned 32-bit, little-endian
        $value = $unpacked[1];

        // Convert unsigned to signed if needed (PHP handles this automatically for most values)
        // But for proper signed int32, check if high bit is set
        if ($value > 0x7FFFFFFF) {
            $value = $value - 0x100000000;
        }

        return $value;
    }

    /**
     * Determine byte order from test endian value
     */
    private static function determineByteOrder(int $testendian, ?string &$serr): ?array
    {
        if ($testendian == self::SEI_FILE_TEST_ENDIAN) {
            $freord = SwephConstants::SEI_FILE_NOREORD;
        } else {
            // Try byte swap
            $swapped = (($testendian & 0xFF) << 24) |
                       ((($testendian >> 8) & 0xFF) << 16) |
                       ((($testendian >> 16) & 0xFF) << 8) |
                       (($testendian >> 24) & 0xFF);

            if ($swapped != self::SEI_FILE_TEST_ENDIAN) {
                $serr = "File damaged: incorrect endian test value";
                return null;
            }
            $freord = SwephConstants::SEI_FILE_REORD;
        }

        // Determine if file is big-endian or little-endian
        $first_byte = $testendian & 0xFF;
        $expected_first = (self::SEI_FILE_TEST_ENDIAN >> 24) & 0xFF;

        if ($first_byte == $expected_first) {
            $fendian = SwephConstants::SEI_FILE_BIGENDIAN;
        } else {
            $fendian = SwephConstants::SEI_FILE_LITENDIAN;
        }

        return [$freord, $fendian];
    }

    /**
     * Read from file with byte reordering if necessary
     *
     * Port of do_fread() from sweph.c:4903
     *
     * @param resource $fp File pointer
     * @param int $size Number of bytes to read
     * @param int $fpos File position (-1 for current position)
     * @param int $freord Reorder flag
     * @param int $fendian Endian flag
     * @param int $corrsize Corrected size (for padding smaller reads into larger types)
     * @return int|float Read value
     */
    private static function doFread($fp, int $size, int $fpos, int $freord, int $fendian, int $corrsize = 0)
    {
        // If corrsize not specified, default to size
        if ($corrsize === 0) {
            $corrsize = $size;
        }

        if ($fpos >= 0) {
            fseek($fp, $fpos, SEEK_SET);
        }

        $data = fread($fp, $size);
        if ($data === false || strlen($data) < $size) {
            throw new \RuntimeException("Cannot read $size bytes from file");
        }

        // If size != corrsize, we need to pad
        if ($size !== $corrsize) {
            // Pad with zeros to corrsize
            $padded = str_repeat("\0", $corrsize);

            // Determine where to place the read bytes
            if (($fendian === SwephConstants::SEI_FILE_BIGENDIAN && !$freord) ||
                ($fendian === SwephConstants::SEI_FILE_LITENDIAN && $freord)) {
                // Place at high-order bytes (offset by corrsize - size)
                $offset = $corrsize - $size;
                for ($i = 0; $i < $size; $i++) {
                    $padded[$offset + $i] = $data[$i];
                }
            } else {
                // Place at low-order bytes
                for ($i = 0; $i < $size; $i++) {
                    $padded[$i] = $data[$i];
                }
            }
            $data = $padded;
        }

        // Convert binary data based on corrsize and byte order
        if ($freord & SwephConstants::SEI_FILE_REORD) {
            $data = strrev($data); // Reverse bytes
        }

        // Unpack based on corrsize
        switch ($corrsize) {
            case 1:
                $unpacked = unpack('C', $data);
                return $unpacked[1];
            case 2:
                $unpacked = unpack('v', $data); // unsigned short, little-endian
                return $unpacked[1];
            case 4:
                $unpacked = unpack('V', $data); // unsigned long, little-endian
                return $unpacked[1];
            case 8:
                $unpacked = unpack('d', $data); // double
                return $unpacked[1];
            default:
                throw new \RuntimeException("Unsupported corrsize: $corrsize");
        }
    }

    /**
     * Fetch Chebyshev coefficients from Swiss Ephemeris file for given time
     *
     * Port of get_new_segment() from sweph.c:4366
     *
     * @param float $tjd Julian Day (TT)
     * @param int $ipli Planet index (SEI_SUNBARY, etc.)
     * @param int $ifno File index
     * @param string|null &$serr Error message
     * @return bool True on success, false on error
     */
    public static function getNewSegment(float $tjd, int $ipli, int $ifno, ?string &$serr): bool
    {
        $swed = SwedState::getInstance();
        $pdp = &$swed->pldat[$ipli];
        $fdp = &$swed->fidat[$ifno];
        $fp = $fdp->fptr;
        $freord = $fdp->iflg & SwephConstants::SEI_FILE_REORD;
        $fendian = $fdp->iflg & SwephConstants::SEI_FILE_LITENDIAN;

        // Check if planet data is loaded
        if ($pdp->dseg == 0.0) {
            $serr = "Planet data not loaded: dseg is zero";
            return false;
        }

        // Compute segment number
        $iseg = (int)(($tjd - $pdp->tfstart) / $pdp->dseg);
        $pdp->tseg0 = $pdp->tfstart + $iseg * $pdp->dseg;
        $pdp->tseg1 = $pdp->tseg0 + $pdp->dseg;

        // Get file position of coefficients from index
        $fpos = $pdp->lndx0 + $iseg * 3;

        try {
            // Read 3-byte file position as 4-byte integer (C code line 4389)
            $fpos = self::doFread($fp, 3, $fpos, $freord, $fendian, 4);
            fseek($fp, $fpos, SEEK_SET);

            // Allocate space for Chebyshev coefficients (3 coordinates)
            if ($pdp->segp === null) {
                $pdp->segp = array_fill(0, $pdp->ncoe * 3, 0.0);
            } else {
                // Clear existing coefficients
                for ($i = 0; $i < $pdp->ncoe * 3; $i++) {
                    $pdp->segp[$i] = 0.0;
                }
            }

            // Read coefficients for 3 coordinates (x, y, z)
            for ($icoord = 0; $icoord < 3; $icoord++) {
                $idbl = $icoord * $pdp->ncoe;

                // Read header (first 2 bytes)
                $c = [];
                $c[0] = self::doFread($fp, 1, self::SEI_CURR_FPOS, $freord, $fendian);
                $c[1] = self::doFread($fp, 1, self::SEI_CURR_FPOS, $freord, $fendian);

                // Determine number of size groups
                if ($c[0] & 128) {
                    // 6 size groups
                    $nsizes = 6;
                    $c[2] = self::doFread($fp, 1, self::SEI_CURR_FPOS, $freord, $fendian);
                    $c[3] = self::doFread($fp, 1, self::SEI_CURR_FPOS, $freord, $fendian);

                    $nsize = [];
                    $nsize[0] = (int)($c[1] / 16);
                    $nsize[1] = (int)($c[1] % 16);
                    $nsize[2] = (int)($c[2] / 16);
                    $nsize[3] = (int)($c[2] % 16);
                    $nsize[4] = (int)($c[3] / 16);
                    $nsize[5] = (int)($c[3] % 16);
                    $nco = $nsize[0] + $nsize[1] + $nsize[2] + $nsize[3] + $nsize[4] + $nsize[5];
                } else {
                    // 4 size groups
                    $nsizes = 4;
                    $nsize = [];
                    $nsize[0] = (int)($c[0] / 16);
                    $nsize[1] = (int)($c[0] % 16);
                    $nsize[2] = (int)($c[1] / 16);
                    $nsize[3] = (int)($c[1] % 16);
                    $nco = $nsize[0] + $nsize[1] + $nsize[2] + $nsize[3];
                }

                // Validate coefficient count
                if ($nco > $pdp->ncoe) {
                    $serr = "Error in ephemeris file {$fdp->fnam}: $nco coefficients instead of {$pdp->ncoe}";
                    if ($pdp->segp !== null) {
                        $pdp->segp = null;
                    }
                    return false;
                }

                // Unpack coefficients
                for ($i = 0; $i < $nsizes; $i++) {
                    if ($nsize[$i] == 0) {
                        continue;
                    }

                    if ($i < 4) {
                        // Standard packing (4 bytes, 3 bytes, 2 bytes, 1 byte)
                        $j = 4 - $i; // bytes per coefficient
                        $k = $nsize[$i]; // number of coefficients

                        $longs = [];
                        for ($mi = 0; $mi < $k; $mi++) {
                            // C code line 4445: do_fread(&longs[0], j, k, 4, ...)
                            $longs[$mi] = self::doFread($fp, $j, self::SEI_CURR_FPOS, $freord, $fendian, 4);
                        }

                        for ($m = 0; $m < $k; $m++, $idbl++) {
                            if ($longs[$m] & 1) {
                                // Negative value
                                $pdp->segp[$idbl] = -((($longs[$m] + 1) / 2) / 1e9 * $pdp->rmax / 2);
                            } else {
                                // Positive value
                                $pdp->segp[$idbl] = ($longs[$m] / 2) / 1e9 * $pdp->rmax / 2;
                            }
                        }
                    } elseif ($i == 4) {
                        // Half-byte packing
                        $j = 1;
                        $k = (int)(($nsize[$i] + 1) / 2);

                        $longs = [];
                        for ($mi = 0; $mi < $k; $mi++) {
                            $longs[$mi] = self::doFread($fp, $j, self::SEI_CURR_FPOS, $freord, $fendian);
                        }

                        $jj = 0;
                        for ($m = 0; $m < $k && $jj < $nsize[$i]; $m++) {
                            $o = 16;
                            for ($n = 0; $n < 2 && $jj < $nsize[$i]; $n++, $jj++, $idbl++) {
                                if ($longs[$m] & $o) {
                                    $pdp->segp[$idbl] = -((($longs[$m] + $o) / $o / 2) * $pdp->rmax / 2 / 1e9);
                                } else {
                                    $pdp->segp[$idbl] = ($longs[$m] / $o / 2) * $pdp->rmax / 2 / 1e9;
                                }
                                $longs[$m] %= $o;
                                $o = (int)($o / 16);
                            }
                        }
                    } elseif ($i == 5) {
                        // Quarter-byte packing
                        $j = 1;
                        $k = (int)(($nsize[$i] + 3) / 4);

                        $longs = [];
                        for ($mi = 0; $mi < $k; $mi++) {
                            $longs[$mi] = self::doFread($fp, $j, self::SEI_CURR_FPOS, $freord, $fendian);
                        }

                        $jj = 0;
                        for ($m = 0; $m < $k && $jj < $nsize[$i]; $m++) {
                            $o = 64;
                            for ($n = 0; $n < 4 && $jj < $nsize[$i]; $n++, $jj++, $idbl++) {
                                if ($longs[$m] & $o) {
                                    $pdp->segp[$idbl] = -((($longs[$m] + $o) / $o / 2) * $pdp->rmax / 2 / 1e9);
                                } else {
                                    $pdp->segp[$idbl] = ($longs[$m] / $o / 2) * $pdp->rmax / 2 / 1e9;
                                }
                                $longs[$m] %= $o;
                                $o = (int)($o / 4);
                            }
                        }
                    }
                }
            }

            return true;

        } catch (\Exception $e) {
            $serr = "Error reading segment: " . $e->getMessage();
            if ($fdp->fptr !== null) {
                fclose($fdp->fptr);
                $fdp->fptr = null;
            }
            return false;
        }
    }
}
