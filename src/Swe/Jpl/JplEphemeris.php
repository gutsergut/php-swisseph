<?php

declare(strict_types=1);

namespace Swisseph\Swe\Jpl;

use Swisseph\SwephFile\SwedState;

/**
 * JPL Ephemeris Reader - reads JPL DE ephemeris files
 * Port of swejpl.c from Swiss Ephemeris
 *
 * Supports: DE200, DE102, DE403, DE404, DE405, DE406, DE421, DE430, DE431, DE440, DE441
 *
 * The JPL ephemeris files contain Chebyshev polynomial coefficients for:
 * - Positions of Mercury through Pluto relative to the Solar System Barycenter
 * - Position of the Moon relative to the Earth-Moon Barycenter
 * - Position of the Sun relative to the Solar System Barycenter
 * - Nutations (longitude and obliquity)
 * - Lunar librations (if available)
 */
class JplEphemeris
{
    /** @var ?resource File pointer */
    private $jplfptr = null;

    /** @var string File name */
    private string $jplfname = '';

    /** @var string File path */
    private string $jplfpath = '';

    /** @var bool Byte order needs reordering (big-endian vs little-endian) */
    private bool $doReorder = false;

    /** @var array<float> Constant values from ephemeris */
    private array $ehCval = [];

    /** @var array<float> [start epoch, end epoch, segment size in days] */
    private array $ehSs = [0.0, 0.0, 0.0];

    /** @var float Astronomical Unit in km */
    private float $ehAu = 0.0;

    /** @var float Earth-Moon mass ratio */
    private float $ehEmrat = 0.0;

    /** @var int DE number (e.g., 405, 431, 441) */
    private int $ehDenum = 0;

    /** @var int Number of constants */
    private int $ehNcon = 0;

    /** @var array<int> Interpolation pointers [39 elements] */
    private array $ehIpt = [];

    /** @var string Constant names (6 chars each, up to 400) */
    private string $chCnam = '';

    /** @var array<float> Position/velocity buffer [78 elements] */
    private array $pv = [];

    /** @var array<float> Sun position/velocity [6 elements] */
    private array $pvsun = [];

    /** @var array<float> Coefficient buffer [~1500 elements] */
    private array $buf = [];

    /** @var array<float> Chebyshev polynomial P coefficients [18] */
    private array $pc = [];

    /** @var array<float> Chebyshev polynomial V coefficients [18] */
    private array $vc = [];

    /** @var array<float> Chebyshev polynomial A coefficients [18] */
    private array $ac = [];

    /** @var array<float> Chebyshev polynomial J coefficients [18] */
    private array $jc = [];

    /** @var bool Return km and km/sec instead of AU and AU/day */
    private bool $doKm = false;

    /** @var int Record size in bytes */
    private int $irecsz = 0;

    /** @var int Number of coefficients per record */
    private int $ncoeffs = 0;

    /** @var int Current record number in buffer */
    private int $nrl = 0;

    /** @var int Number of P polynomials computed */
    private int $np = 2;

    /** @var int Number of V polynomials computed */
    private int $nv = 3;

    /** @var int Number of A polynomials computed */
    private int $nac = 4;

    /** @var int Number of J polynomials computed */
    private int $njk = 5;

    /** @var float Twice the Chebyshev time */
    private float $twot = 0.0;

    /** @var bool File is initialized */
    private bool $initialized = false;

    /** Singleton instance */
    private static ?self $instance = null;

    private function __construct()
    {
        // Initialize arrays
        $this->ehIpt = array_fill(0, 39, 0);
        $this->pv = array_fill(0, 78, 0.0);
        $this->pvsun = array_fill(0, 6, 0.0);
        $this->buf = array_fill(0, 1500, 0.0);
        $this->pc = array_fill(0, 18, 0.0);
        $this->vc = array_fill(0, 18, 0.0);
        $this->ac = array_fill(0, 18, 0.0);
        $this->jc = array_fill(0, 18, 0.0);
        $this->ehCval = array_fill(0, 400, 0.0);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Open JPL ephemeris file
     * Port of swi_open_jpl_file()
     *
     * @param array<float> &$ss Returns [start epoch, end epoch, segment size]
     * @param string $fname Ephemeris file name (e.g., "de441.eph")
     * @param string $fpath Ephemeris file path
     * @param string|null &$serr Error message
     * @return int OK (0) on success, negative on error
     */
    public function open(array &$ss, string $fname, string $fpath, ?string &$serr = null): int
    {
        // If already open, return
        if ($this->jplfptr !== null && $this->initialized) {
            $ss = $this->ehSs;
            return JplConstants::OK;
        }

        $this->jplfname = $fname;
        $this->jplfpath = $fpath;

        $retc = $this->readConst($ss, $serr);
        if ($retc !== JplConstants::OK) {
            $this->close();
            return $retc;
        }

        // Initializations for interp()
        $this->pc[0] = 1.0;
        $this->pc[1] = 2.0;
        $this->vc[1] = 1.0;
        $this->ac[2] = 4.0;
        $this->jc[3] = 24.0;

        $this->initialized = true;
        return JplConstants::OK;
    }

    /**
     * Close JPL ephemeris file
     * Port of swi_close_jpl_file()
     */
    public function close(): void
    {
        if ($this->jplfptr !== null && is_resource($this->jplfptr)) {
            fclose($this->jplfptr);
        }
        $this->jplfptr = null;
        $this->initialized = false;
        $this->nrl = 0;
    }

    /**
     * Get DE number of currently loaded ephemeris
     * Port of swi_get_jpl_denum()
     */
    public function getDenum(): int
    {
        return $this->ehDenum;
    }

    /**
     * Get Earth-Moon mass ratio from ephemeris
     */
    public function getEmrat(): float
    {
        return $this->ehEmrat;
    }

    /**
     * Get AU value from ephemeris
     */
    public function getAu(): float
    {
        return $this->ehAu;
    }

    /**
     * Check if file is open and initialized
     */
    public function isOpen(): bool
    {
        return $this->initialized && $this->jplfptr !== null;
    }

    /**
     * Compute position and velocity of a body
     * Port of swi_pleph()
     *
     * @param float $et Julian Ephemeris Date
     * @param int $ntarg Target body (J_MERCURY..J_LIB)
     * @param int $ncent Center body (J_MERCURY..J_EMB)
     * @param array<float> &$rrd Output: 6-element array [x, y, z, vx, vy, vz]
     * @param string|null &$serr Error message
     * @return int OK (0) on success, negative on error
     */
    public function pleph(float $et, int $ntarg, int $ncent, array &$rrd, ?string &$serr = null): int
    {
        $rrd = array_fill(0, 6, 0.0);

        if ($ntarg === $ncent) {
            return JplConstants::OK;
        }

        $list = array_fill(0, 12, 0);

        // Check for nutation call
        if ($ntarg === JplConstants::J_NUT) {
            if ($this->ehIpt[34] > 0) {
                $list[10] = 2;
                return $this->state($et, $list, false, $this->pv, $this->pvsun, $rrd, $serr);
            } else {
                $serr = 'No nutations on the JPL ephemeris file';
                return JplConstants::NOT_AVAILABLE;
            }
        }

        // Check for libration call
        if ($ntarg === JplConstants::J_LIB) {
            if ($this->ehIpt[37] > 0) {
                $list[11] = 2;
                $retc = $this->state($et, $list, false, $this->pv, $this->pvsun, $rrd, $serr);
                if ($retc !== JplConstants::OK) {
                    return $retc;
                }
                for ($i = 0; $i < 6; $i++) {
                    $rrd[$i] = $this->pv[$i + 60];
                }
                return JplConstants::OK;
            } else {
                $serr = 'No librations on the ephemeris file';
                return JplConstants::NOT_AVAILABLE;
            }
        }

        // Set up proper entries in 'list' array for state call
        if ($ntarg < JplConstants::J_SUN) {
            $list[$ntarg] = 2;
        }
        if ($ntarg === JplConstants::J_MOON) {
            $list[JplConstants::J_EARTH] = 2; // Moon needs Earth
        }
        if ($ntarg === JplConstants::J_EARTH) {
            $list[JplConstants::J_MOON] = 2;  // Earth needs Moon
        }
        if ($ntarg === JplConstants::J_EMB) {
            $list[JplConstants::J_EARTH] = 2; // EMB needs Earth
        }
        if ($ncent < JplConstants::J_SUN) {
            $list[$ncent] = 2;
        }
        if ($ncent === JplConstants::J_MOON) {
            $list[JplConstants::J_EARTH] = 2;
        }
        if ($ncent === JplConstants::J_EARTH) {
            $list[JplConstants::J_MOON] = 2;
        }
        if ($ncent === JplConstants::J_EMB) {
            $list[JplConstants::J_EARTH] = 2;
        }

        $retc = $this->state($et, $list, true, $this->pv, $this->pvsun, $rrd, $serr);
        if ($retc !== JplConstants::OK) {
            return $retc;
        }

        // Handle Sun position
        if ($ntarg === JplConstants::J_SUN || $ncent === JplConstants::J_SUN) {
            for ($i = 0; $i < 6; $i++) {
                $this->pv[$i + 6 * JplConstants::J_SUN] = $this->pvsun[$i];
            }
        }

        // Handle barycenter (zero position)
        if ($ntarg === JplConstants::J_SBARY || $ncent === JplConstants::J_SBARY) {
            for ($i = 0; $i < 6; $i++) {
                $this->pv[$i + 6 * JplConstants::J_SBARY] = 0.0;
            }
        }

        // Handle EMB
        if ($ntarg === JplConstants::J_EMB || $ncent === JplConstants::J_EMB) {
            for ($i = 0; $i < 6; $i++) {
                $this->pv[$i + 6 * JplConstants::J_EMB] = $this->pv[$i + 6 * JplConstants::J_EARTH];
            }
        }

        // Handle Earth-Moon relationship
        if (($ntarg === JplConstants::J_EARTH && $ncent === JplConstants::J_MOON) ||
            ($ntarg === JplConstants::J_MOON && $ncent === JplConstants::J_EARTH)) {
            for ($i = 0; $i < 6; $i++) {
                $this->pv[$i + 6 * JplConstants::J_EARTH] = 0.0;
            }
        } else {
            if ($list[JplConstants::J_EARTH] === 2) {
                for ($i = 0; $i < 6; $i++) {
                    $this->pv[$i + 6 * JplConstants::J_EARTH] -=
                        $this->pv[$i + 6 * JplConstants::J_MOON] / ($this->ehEmrat + 1.0);
                }
            }
            if ($list[JplConstants::J_MOON] === 2) {
                for ($i = 0; $i < 6; $i++) {
                    $this->pv[$i + 6 * JplConstants::J_MOON] +=
                        $this->pv[$i + 6 * JplConstants::J_EARTH];
                }
            }
        }

        // Compute final result: target - center
        for ($i = 0; $i < 6; $i++) {
            $rrd[$i] = $this->pv[$i + $ntarg * 6] - $this->pv[$i + $ncent * 6];
        }

        return JplConstants::OK;
    }

    /**
     * Read constants from ephemeris file
     * Port of read_const_jpl()
     */
    private function readConst(array &$ss, ?string &$serr): int
    {
        $retc = $this->state(0.0, null, false, $this->pv, $this->pvsun, $ss, $serr);
        if ($retc !== JplConstants::OK) {
            return $retc;
        }

        $ss = [$this->ehSs[0], $this->ehSs[1], $this->ehSs[2]];
        return JplConstants::OK;
    }

    /**
     * Compute record size from file header
     * Port of fsizer()
     */
    private function fsizer(?string &$serr): int
    {
        // Construct full path
        $fullPath = $this->jplfpath;
        if (!empty($fullPath) && !str_ends_with($fullPath, DIRECTORY_SEPARATOR)) {
            $fullPath .= DIRECTORY_SEPARATOR;
        }
        $fullPath .= $this->jplfname;

        // Try to open the file
        $this->jplfptr = @fopen($fullPath, 'rb');
        if ($this->jplfptr === false) {
            // Try using SwedState path
            $swed = SwedState::getInstance();
            $ephePath = $swed->ephepath;
            if (!empty($ephePath)) {
                $fullPath = $ephePath;
                if (!str_ends_with($fullPath, DIRECTORY_SEPARATOR)) {
                    $fullPath .= DIRECTORY_SEPARATOR;
                }
                $fullPath .= $this->jplfname;
                $this->jplfptr = @fopen($fullPath, 'rb');
            }
        }

        if ($this->jplfptr === false) {
            $serr = sprintf('JPL ephemeris file %s not found', $this->jplfname);
            $this->jplfptr = null;
            return JplConstants::NOT_AVAILABLE;
        }

        // Read title (252 bytes)
        $ttl = fread($this->jplfptr, 252);
        if ($ttl === false || strlen($ttl) !== 252) {
            $serr = 'Error reading JPL ephemeris title';
            return JplConstants::NOT_AVAILABLE;
        }

        // Read constant names (2400 bytes = 6 * 400)
        $this->chCnam = fread($this->jplfptr, 2400);
        if ($this->chCnam === false || strlen($this->chCnam) !== 2400) {
            $serr = 'Error reading JPL ephemeris constant names';
            return JplConstants::NOT_AVAILABLE;
        }

        // Read ss[3] - start/end/segment
        $data = fread($this->jplfptr, 24);  // 3 doubles
        if ($data === false || strlen($data) !== 24) {
            $serr = 'Error reading JPL ephemeris dates';
            return JplConstants::NOT_AVAILABLE;
        }

        $ss = unpack('d3', $data);
        $this->ehSs = [$ss[1], $ss[2], $ss[3]];

        // Check if reordering is needed
        if ($this->ehSs[2] < 1 || $this->ehSs[2] > 200) {
            $this->doReorder = true;
            // Reorder the ss values
            $this->ehSs[0] = $this->reorderDouble($this->ehSs[0]);
            $this->ehSs[1] = $this->reorderDouble($this->ehSs[1]);
            $this->ehSs[2] = $this->reorderDouble($this->ehSs[2]);
        }

        // Plausibility check
        if ($this->ehSs[0] < -5583942 || $this->ehSs[1] > 9025909 ||
            $this->ehSs[2] < 1 || $this->ehSs[2] > 200) {
            $serr = sprintf('JPL ephemeris file (%s) has invalid format', $this->jplfname);
            return JplConstants::NOT_AVAILABLE;
        }

        // Read ncon
        $data = fread($this->jplfptr, 4);
        if ($data === false || strlen($data) !== 4) {
            return JplConstants::NOT_AVAILABLE;
        }
        $ncon = unpack('l', $data)[1];
        if ($this->doReorder) {
            $ncon = $this->reorderInt32($ncon);
        }
        $this->ehNcon = $ncon;

        // Read AU
        $data = fread($this->jplfptr, 8);
        if ($data === false || strlen($data) !== 8) {
            return JplConstants::NOT_AVAILABLE;
        }
        $this->ehAu = unpack('d', $data)[1];
        if ($this->doReorder) {
            $this->ehAu = $this->reorderDouble($this->ehAu);
        }

        // Read EMRAT
        $data = fread($this->jplfptr, 8);
        if ($data === false || strlen($data) !== 8) {
            return JplConstants::NOT_AVAILABLE;
        }
        $this->ehEmrat = unpack('d', $data)[1];
        if ($this->doReorder) {
            $this->ehEmrat = $this->reorderDouble($this->ehEmrat);
        }

        // Read ipt[36]
        $data = fread($this->jplfptr, 144);  // 36 * 4 bytes
        if ($data === false || strlen($data) !== 144) {
            return JplConstants::NOT_AVAILABLE;
        }
        $ipt = unpack('l36', $data);
        for ($i = 0; $i < 36; $i++) {
            $this->ehIpt[$i] = $ipt[$i + 1];
            if ($this->doReorder) {
                $this->ehIpt[$i] = $this->reorderInt32($this->ehIpt[$i]);
            }
        }

        // Read numde
        $data = fread($this->jplfptr, 4);
        if ($data === false || strlen($data) !== 4) {
            return JplConstants::NOT_AVAILABLE;
        }
        $this->ehDenum = unpack('l', $data)[1];
        if ($this->doReorder) {
            $this->ehDenum = $this->reorderInt32($this->ehDenum);
        }

        // Read libration pointers [3]
        $data = fread($this->jplfptr, 12);
        if ($data === false || strlen($data) !== 12) {
            return JplConstants::NOT_AVAILABLE;
        }
        $lpt = unpack('l3', $data);
        for ($i = 0; $i < 3; $i++) {
            $val = $lpt[$i + 1];
            if ($this->doReorder) {
                $val = $this->reorderInt32($val);
            }
            $this->ehIpt[36 + $i] = $val;
        }

        rewind($this->jplfptr);

        // Calculate record size from pointers
        $kmx = 0;
        $khi = 0;
        for ($i = 0; $i < 13; $i++) {
            if ($this->ehIpt[$i * 3] > $kmx) {
                $kmx = $this->ehIpt[$i * 3];
                $khi = $i + 1;
            }
        }

        $nd = ($khi === 12) ? 2 : 3;
        $ksize = ($this->ehIpt[$khi * 3 - 3] + $nd * $this->ehIpt[$khi * 3 - 2] * $this->ehIpt[$khi * 3 - 1] - 1) * 2;

        // DE102 workaround
        if ($ksize === 1546) {
            $ksize = 1652;
        }

        if ($ksize < 1000 || $ksize > 5000) {
            $serr = sprintf('JPL ephemeris file does not provide valid ksize (%d)', $ksize);
            return JplConstants::NOT_AVAILABLE;
        }

        return $ksize;
    }

    /**
     * State function - reads and interpolates ephemeris data
     * Port of state()
     */
    private function state(
        float $et,
        ?array $list,
        bool $doBary,
        array &$pv,
        array &$pvsun,
        array &$nut,
        ?string &$serr
    ): int {
        // Initialize file if needed
        if ($this->jplfptr === null) {
            $ksize = $this->fsizer($serr);
            if ($ksize === JplConstants::NOT_AVAILABLE) {
                return JplConstants::NOT_AVAILABLE;
            }

            $nrecl = 4;
            $this->irecsz = $nrecl * $ksize;   // Record size in bytes
            $this->ncoeffs = (int)($ksize / 2); // Number of coefficients (doubles)

            // Read title
            $ttl = fread($this->jplfptr, 252);
            if ($ttl === false || strlen($ttl) !== 252) {
                return JplConstants::NOT_AVAILABLE;
            }

            // Read constant names
            $this->chCnam = fread($this->jplfptr, 2400);
            if ($this->chCnam === false || strlen($this->chCnam) !== 2400) {
                return JplConstants::NOT_AVAILABLE;
            }

            // Read ss
            $data = fread($this->jplfptr, 24);
            if ($data === false || strlen($data) !== 24) {
                return JplConstants::NOT_AVAILABLE;
            }
            $ss = unpack('d3', $data);
            $this->ehSs = [$ss[1], $ss[2], $ss[3]];
            if ($this->doReorder) {
                $this->ehSs[0] = $this->reorderDouble($this->ehSs[0]);
                $this->ehSs[1] = $this->reorderDouble($this->ehSs[1]);
                $this->ehSs[2] = $this->reorderDouble($this->ehSs[2]);
            }

            // Read ncon
            $data = fread($this->jplfptr, 4);
            if ($data === false || strlen($data) !== 4) {
                return JplConstants::NOT_AVAILABLE;
            }
            $this->ehNcon = unpack('l', $data)[1];
            if ($this->doReorder) {
                $this->ehNcon = $this->reorderInt32($this->ehNcon);
            }

            // Read AU
            $data = fread($this->jplfptr, 8);
            if ($data === false || strlen($data) !== 8) {
                return JplConstants::NOT_AVAILABLE;
            }
            $this->ehAu = unpack('d', $data)[1];
            if ($this->doReorder) {
                $this->ehAu = $this->reorderDouble($this->ehAu);
            }

            // Read EMRAT
            $data = fread($this->jplfptr, 8);
            if ($data === false || strlen($data) !== 8) {
                return JplConstants::NOT_AVAILABLE;
            }
            $this->ehEmrat = unpack('d', $data)[1];
            if ($this->doReorder) {
                $this->ehEmrat = $this->reorderDouble($this->ehEmrat);
            }

            // Read ipt[36]
            $data = fread($this->jplfptr, 144);
            if ($data === false || strlen($data) !== 144) {
                return JplConstants::NOT_AVAILABLE;
            }
            $ipt = unpack('l36', $data);
            for ($i = 0; $i < 36; $i++) {
                $this->ehIpt[$i] = $ipt[$i + 1];
                if ($this->doReorder) {
                    $this->ehIpt[$i] = $this->reorderInt32($this->ehIpt[$i]);
                }
            }

            // Read denum
            $data = fread($this->jplfptr, 4);
            if ($data === false || strlen($data) !== 4) {
                return JplConstants::NOT_AVAILABLE;
            }
            $this->ehDenum = unpack('l', $data)[1];
            if ($this->doReorder) {
                $this->ehDenum = $this->reorderInt32($this->ehDenum);
            }

            // Read libration pointers
            $data = fread($this->jplfptr, 12);
            if ($data === false || strlen($data) !== 12) {
                return JplConstants::NOT_AVAILABLE;
            }
            $lpt = unpack('l3', $data);
            for ($i = 0; $i < 3; $i++) {
                $val = $lpt[$i + 1];
                if ($this->doReorder) {
                    $val = $this->reorderInt32($val);
                }
                $this->ehIpt[36 + $i] = $val;
            }

            // Read constants from second record
            fseek($this->jplfptr, $this->irecsz, SEEK_SET);
            $data = fread($this->jplfptr, 3200);  // 400 doubles
            if ($data === false || strlen($data) !== 3200) {
                return JplConstants::NOT_AVAILABLE;
            }
            $cval = unpack('d400', $data);
            for ($i = 0; $i < 400; $i++) {
                $this->ehCval[$i] = $cval[$i + 1];
                if ($this->doReorder) {
                    $this->ehCval[$i] = $this->reorderDouble($this->ehCval[$i]);
                }
            }

            $this->nrl = 0;

            // Verify file length
            fseek($this->jplfptr, 0, SEEK_END);
            $flen = ftell($this->jplfptr);
            $nseg = (int)(($this->ehSs[1] - $this->ehSs[0]) / $this->ehSs[2]);

            $nb = 0;
            for ($i = 0; $i < 13; $i++) {
                $k = ($i === 11) ? 2 : 3;
                $nb += $this->ehIpt[$i * 3 + 1] * $this->ehIpt[$i * 3 + 2] * $k * $nseg;
            }
            $nb += 2 * $nseg;  // Start/end epochs
            $nb *= 8;          // Doubles to bytes
            $nb += 2 * ($this->irecsz); // Header + constants

            // Allow for file to be one record longer
            if ($flen !== $nb && $flen - $nb !== $this->irecsz) {
                $serr = sprintf(
                    'JPL ephemeris file %s is mutilated; length = %d instead of %d',
                    $this->jplfname,
                    $flen,
                    $nb
                );
                return JplConstants::NOT_AVAILABLE;
            }

            // Verify start/end dates
            fseek($this->jplfptr, 2 * $this->irecsz, SEEK_SET);
            $data = fread($this->jplfptr, 16);
            if ($data === false || strlen($data) !== 16) {
                return JplConstants::NOT_AVAILABLE;
            }
            $ts = unpack('d2', $data);
            $ts0 = $ts[1];
            $ts1 = $ts[2];
            if ($this->doReorder) {
                $ts0 = $this->reorderDouble($ts0);
                $ts1 = $this->reorderDouble($ts1);
            }

            fseek($this->jplfptr, ($nseg + 2 - 1) * $this->irecsz, SEEK_SET);
            $data = fread($this->jplfptr, 16);
            if ($data === false || strlen($data) !== 16) {
                return JplConstants::NOT_AVAILABLE;
            }
            $ts = unpack('d2', $data);
            $ts2 = $ts[1];
            $ts3 = $ts[2];
            if ($this->doReorder) {
                $ts2 = $this->reorderDouble($ts2);
                $ts3 = $this->reorderDouble($ts3);
            }

            if ($ts0 !== $this->ehSs[0] || $ts3 !== $this->ehSs[1]) {
                $serr = sprintf(
                    'JPL ephemeris file is corrupt; start/end date check failed. %.1f != %.1f || %.1f != %.1f',
                    $ts0, $this->ehSs[0], $ts3, $this->ehSs[1]
                );
                return JplConstants::NOT_AVAILABLE;
            }
        }

        // If list is null, we're just initializing
        if ($list === null) {
            return JplConstants::OK;
        }

        // Calculate which record to read
        $s = $et - 0.5;
        $etMn = floor($s);
        $etFr = $s - $etMn;
        $etMn += 0.5;

        // Check date range
        if ($et < $this->ehSs[0] || $et > $this->ehSs[1]) {
            $serr = sprintf(
                'jd %.1f outside JPL eph. range %.2f .. %.2f',
                $et, $this->ehSs[0], $this->ehSs[1]
            );
            return JplConstants::BEYOND_EPH_LIMITS;
        }

        // Calculate record number
        $nr = (int)(($etMn - $this->ehSs[0]) / $this->ehSs[2]) + 2;
        if ($etMn === $this->ehSs[1]) {
            $nr--;  // End point uses last record
        }

        // Normalized time within segment
        $t = ($etMn - (($nr - 2) * $this->ehSs[2] + $this->ehSs[0]) + $etFr) / $this->ehSs[2];

        // Read record if not in cache
        if ($nr !== $this->nrl) {
            $this->nrl = $nr;
            if (fseek($this->jplfptr, $nr * $this->irecsz, SEEK_SET) !== 0) {
                $serr = sprintf('Read error in JPL eph. at %.1f', $et);
                return JplConstants::NOT_AVAILABLE;
            }

            for ($k = 0; $k < $this->ncoeffs; $k++) {
                $data = fread($this->jplfptr, 8);
                if ($data === false || strlen($data) !== 8) {
                    $serr = sprintf('Read error in JPL eph. at %.1f', $et);
                    return JplConstants::NOT_AVAILABLE;
                }
                $this->buf[$k] = unpack('d', $data)[1];
                if ($this->doReorder) {
                    $this->buf[$k] = $this->reorderDouble($this->buf[$k]);
                }
            }
        }

        // Set up interval and AU factor
        if ($this->doKm) {
            $intv = $this->ehSs[2] * 86400.0;
            $aufac = 1.0;
        } else {
            $intv = $this->ehSs[2];
            $aufac = 1.0 / $this->ehAu;
        }

        // Interpolate Sun (barycentric)
        $ipt = $this->ehIpt;
        $this->interp($ipt[30] - 1, $t, $intv, $ipt[31], 3, $ipt[32], 2, $pvsun);
        for ($i = 0; $i < 6; $i++) {
            $pvsun[$i] *= $aufac;
        }

        // Interpolate requested bodies
        for ($i = 0; $i < 10; $i++) {
            if ($list[$i] > 0) {
                $pvTemp = [];
                $this->interp($ipt[$i * 3] - 1, $t, $intv, $ipt[$i * 3 + 1], 3, $ipt[$i * 3 + 2], $list[$i], $pvTemp);
                for ($j = 0; $j < 6; $j++) {
                    if ($i < 9 && !$doBary) {
                        $pv[$j + $i * 6] = $pvTemp[$j] * $aufac - $pvsun[$j];
                    } else {
                        $pv[$j + $i * 6] = $pvTemp[$j] * $aufac;
                    }
                }
            }
        }

        // Do nutations if requested
        if ($list[10] > 0 && $ipt[34] > 0) {
            $this->interp($ipt[33] - 1, $t, $intv, $ipt[34], 2, $ipt[35], $list[10], $nut);
        }

        // Do librations if requested
        if ($list[11] > 0 && $ipt[37] > 0) {
            $pvLib = [];
            $this->interp($ipt[36] - 1, $t, $intv, $ipt[37], 3, $ipt[38], $list[11], $pvLib);
            for ($i = 0; $i < 6; $i++) {
                $pv[60 + $i] = $pvLib[$i];
            }
        }

        return JplConstants::OK;
    }

    /**
     * Chebyshev interpolation
     * Port of interp()
     *
     * @param int $bufStart Starting index in buf array
     * @param float $t Normalized time (0 <= t <= 1)
     * @param float $intv Interval length
     * @param int $ncf Number of coefficients per component
     * @param int $ncm Number of components
     * @param int $na Number of intervals
     * @param int $ifl Flag: 1=pos only, 2=pos+vel
     * @param array<float> &$pv Output array
     */
    private function interp(
        int $bufStart,
        float $t,
        float $intv,
        int $ncf,
        int $ncm,
        int $na,
        int $ifl,
        array &$pv
    ): void {
        $pv = array_fill(0, $ncm * 2, 0.0);

        // Get sub-interval and normalized time
        if ($t >= 0) {
            $dt1 = floor($t);
        } else {
            $dt1 = -floor(-$t);
        }
        $temp = $na * $t;
        $ni = (int)($temp - $dt1);

        // tc is normalized Chebyshev time (-1 <= tc <= 1)
        $tc = (fmod($temp, 1.0) + $dt1) * 2.0 - 1.0;

        // Compute Chebyshev polynomials if tc changed
        if ($tc !== $this->pc[1]) {
            $this->np = 2;
            $this->nv = 3;
            $this->nac = 4;
            $this->njk = 5;
            $this->pc[1] = $tc;
            $this->twot = $tc + $tc;
        }

        // Ensure enough P polynomials are computed
        if ($this->np < $ncf) {
            for ($i = $this->np; $i < $ncf; $i++) {
                $this->pc[$i] = $this->twot * $this->pc[$i - 1] - $this->pc[$i - 2];
            }
            $this->np = $ncf;
        }

        // Interpolate position
        for ($i = 0; $i < $ncm; $i++) {
            $pv[$i] = 0.0;
            for ($j = $ncf - 1; $j >= 0; $j--) {
                $pv[$i] += $this->pc[$j] * $this->buf[$bufStart + $j + ($i + $ni * $ncm) * $ncf];
            }
        }

        if ($ifl <= 1) {
            return;
        }

        // Compute velocity polynomials
        $bma = ($na + $na) / $intv;
        $this->vc[2] = $this->twot + $this->twot;

        if ($this->nv < $ncf) {
            for ($i = $this->nv; $i < $ncf; $i++) {
                $this->vc[$i] = $this->twot * $this->vc[$i - 1] +
                    $this->pc[$i - 1] + $this->pc[$i - 1] - $this->vc[$i - 2];
            }
            $this->nv = $ncf;
        }

        // Interpolate velocity
        for ($i = 0; $i < $ncm; $i++) {
            $pv[$i + $ncm] = 0.0;
            for ($j = $ncf - 1; $j >= 1; $j--) {
                $pv[$i + $ncm] += $this->vc[$j] * $this->buf[$bufStart + $j + ($i + $ni * $ncm) * $ncf];
            }
            $pv[$i + $ncm] *= $bma;
        }
    }

    /**
     * Reorder double bytes (for big-endian files)
     */
    private function reorderDouble(float $val): float
    {
        $packed = pack('d', $val);
        $reversed = strrev($packed);
        return unpack('d', $reversed)[1];
    }

    /**
     * Reorder int32 bytes
     */
    private function reorderInt32(int $val): int
    {
        $packed = pack('l', $val);
        $reversed = strrev($packed);
        return unpack('l', $reversed)[1];
    }

    /**
     * Reset singleton for testing
     */
    public static function resetInstance(): void
    {
        if (self::$instance !== null) {
            self::$instance->close();
            self::$instance = null;
        }
    }
}
