<?php

declare(strict_types=1);

namespace Swisseph\Domain\Moshier;

use Swisseph\Constants;
use Swisseph\Moshier\MoshierConstants;
use Swisseph\Precession;
use Swisseph\SwephFile\SwedState;

/**
 * Moshier Moon theory implementation
 *
 * ELP2000-85 analytical lunar ephemeris adapted by S. Moshier to fit DE404.
 *
 * Precision: ±7" longitude, ±5" latitude, ±5×10⁻⁸ AU radius (-3000..+3000 AD)
 *
 * Entry swi_moshmoon2() returns the geometric position of the Moon
 * relative to the Earth. Its coordinates are:
 *   pol[0] = ecliptic longitude (radians)
 *   pol[1] = ecliptic latitude (radians)
 *   pol[2] = distance (AU)
 *
 * Entry swi_moshmoon() returns equatorial J2000 cartesian coordinates
 * with speeds computed from 3 positions.
 *
 * @see swemmoon.c
 */
class MoshierMoon
{
    /** Arc seconds to radians: 1 arcsec = this many radians */
    private const STR = 4.8481368110953599359e-6;

    /** J2000 epoch in Julian days */
    private const J2000 = 2451545.0;

    /** Astronomical unit in km */
    private const AUNIT = 149597870.7;

    /** Moon mean distance in km */
    private const MOON_MEAN_DIST = 384400.0;

    /** Moon mean eccentricity */
    private const MOON_MEAN_ECC = 0.0549006;

    /** Moon mean inclination in degrees */
    private const MOON_MEAN_INCL = 5.1453964;

    /** Speed interval for Moon (days) */
    private const MOON_SPEED_INTV = 0.00005;

    /** Moshier Moon ephemeris start JD (-3000 years) */
    public const MOSHLUEPH_START = 625307.5;    // -3000 Jan 1

    /** Moshier Moon ephemeris end JD (+3000 years) */
    public const MOSHLUEPH_END = 2816848.5;     // +3000 Dec 31

    /** Moshier mean node ephemeris start JD */
    public const MOSHNDEPH_START = -3100015.5;  // -13200

    /** Moshier mean node ephemeris end JD */
    public const MOSHNDEPH_END = 8000016.5;     // +17200

    // ============ Thread-local state variables (in C: static TLS) ============

    /** Sin/cos lookup tables for multiple angles */
    private array $ss = [];
    private array $cc = [];

    /** Moon's ecliptic longitude accumulator */
    private float $moonL = 0.0;

    /** Ecliptic latitude */
    private float $B = 0.0;

    /** Final polar result [lon, lat, radius] */
    private array $moonpol = [0.0, 0.0, 0.0];

    // Mean elements
    private float $SWELP = 0.0;  // Moon's mean longitude
    private float $M = 0.0;      // Sun's mean anomaly (l')
    private float $MP = 0.0;     // Moon's mean anomaly (l)
    private float $D = 0.0;      // Mean elongation
    private float $NF = 0.0;     // Moon's mean argument of latitude (F)

    // Time powers
    private float $T = 0.0;
    private float $T2 = 0.0;
    private float $T3 = 0.0;
    private float $T4 = 0.0;

    // Angle temporaries
    private float $f = 0.0;
    private float $g = 0.0;
    private float $cg = 0.0;
    private float $sg = 0.0;

    // Planet mean longitudes
    private float $Ve = 0.0;
    private float $Ea = 0.0;
    private float $Ma = 0.0;
    private float $Ju = 0.0;
    private float $Sa = 0.0;

    // Longitude polynomial terms
    private float $l1 = 0.0;
    private float $l2 = 0.0;
    private float $l3 = 0.0;
    private float $l4 = 0.0;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Initialize sin/cos tables
        for ($i = 0; $i < 5; $i++) {
            $this->ss[$i] = array_fill(0, 8, 0.0);
            $this->cc[$i] = array_fill(0, 8, 0.0);
        }
    }

    /**
     * Calculate geometric coordinates of Moon without light time or nutation correction.
     *
     * @param float $J Julian Ephemeris Date
     * @param array $pol Output: ecliptic polar coordinates in radians and AU
     *                   pol[0] = longitude, pol[1] = latitude, pol[2] = radius
     * @return int OK (0) always
     */
    public function moshmoon2(float $J, array &$pol): int
    {
        $this->T = ($J - self::J2000) / 36525.0;
        $this->T2 = $this->T * $this->T;

        $this->meanElements();
        $this->meanElementsPl();
        $this->moon1();
        $this->moon2();
        $this->moon3();
        $this->moon4();

        for ($i = 0; $i < 3; $i++) {
            $pol[$i] = $this->moonpol[$i];
        }

        return 0;
    }

    /**
     * Complete Moshier Moon calculation with speed.
     * Returns equatorial J2000 cartesian coordinates.
     *
     * @param float $tjd Julian day
     * @param bool $doSave Save to SwedState planet data
     * @param array|null $xpmret Output array for position and speed (6 doubles)
     * @param string|null $serr Error message
     * @param SwedState|null $swed State object
     * @return int OK (0) or ERR (-1)
     */
    public function moshmoon(
        float $tjd,
        bool $doSave,
        ?array &$xpmret,
        ?string &$serr,
        ?SwedState $swed = null
    ): int {
        $xx = array_fill(0, 6, 0.0);

        // Allow 0.2 day tolerance so that true node interval fits in
        if ($tjd < self::MOSHLUEPH_START - 0.2 || $tjd > self::MOSHLUEPH_END + 0.2) {
            $msg = sprintf(
                "jd %f outside Moshier's Moon range %.2f .. %.2f ",
                $tjd,
                self::MOSHLUEPH_START,
                self::MOSHLUEPH_END
            );
            if ($serr !== null) {
                $serr .= $msg;
            }
            return Constants::ERR;
        }

        // Check if already computed
        if ($swed !== null && $doSave) {
            $pdp = $swed->pldat[MoshierConstants::SEI_MOON] ?? null;
            if ($pdp !== null && $pdp->teval === $tjd && $pdp->iephe === Constants::SEFLG_MOSEPH) {
                if ($xpmret !== null) {
                    for ($i = 0; $i <= 5; $i++) {
                        $xpmret[$i] = $pdp->x[$i];
                    }
                }
                return Constants::OK;
            }
        }

        // Compute Moon geometric position
        $xpm = array_fill(0, 6, 0.0);
        $this->moshmoon2($tjd, $xpm);

        // Convert ecliptic of date to equatorial J2000
        // Moshier moon is referred to ecliptic of date.
        // But we need equatorial positions for several reasons:
        // e.g. computation of earth from emb and moon, of heliocentric moon.
        // Besides, this helps to keep the program structure simpler.
        $this->ecldatEqu2000($tjd, $xpm, $swed);

        // Speed: from 2 other positions
        // One would be good enough for computation of osculating node,
        // but not for osculating apogee
        $x1 = array_fill(0, 6, 0.0);
        $x2 = array_fill(0, 6, 0.0);

        $t = $tjd + self::MOON_SPEED_INTV;
        $this->moshmoon2($t, $x1);
        $this->ecldatEqu2000($t, $x1, $swed);

        $t = $tjd - self::MOON_SPEED_INTV;
        $this->moshmoon2($t, $x2);
        $this->ecldatEqu2000($t, $x2, $swed);

        // Calculate speeds using parabolic interpolation
        for ($i = 0; $i <= 2; $i++) {
            $b = ($x1[$i] - $x2[$i]) / 2;
            $a = ($x1[$i] + $x2[$i]) / 2 - $xpm[$i];
            $xpm[$i + 3] = (2 * $a + $b) / self::MOON_SPEED_INTV;
        }

        // Copy to output
        for ($i = 0; $i <= 5; $i++) {
            $xx[$i] = $xpm[$i];
        }

        // Save to state if requested
        if ($swed !== null && $doSave) {
            if (!isset($swed->pldat[MoshierConstants::SEI_MOON])) {
                $swed->pldat[MoshierConstants::SEI_MOON] = new \stdClass();
            }
            $pdp = $swed->pldat[MoshierConstants::SEI_MOON];
            $pdp->x = $xx;
            $pdp->teval = $tjd;
            $pdp->xflgs = -1;
            $pdp->iephe = Constants::SEFLG_MOSEPH;
        }

        if ($xpmret !== null) {
            for ($i = 0; $i <= 5; $i++) {
                $xpmret[$i] = $xx[$i];
            }
        }

        return Constants::OK;
    }

    /**
     * Mean elements of the Moon
     *
     * @see swemmoon.c mean_elements()
     */
    private function meanElements(): void
    {
        $T = $this->T;
        $T2 = $this->T2;

        $fracT = fmod($T, 1.0);

        // Mean anomaly of sun = l' (J. Laskar)
        $this->M = $this->mods3600(129600000.0 * $fracT - 3418.961646 * $T + 1287104.76154);
        $this->M += ((((((((
            1.62e-20 * $T
            - 1.0390e-17) * $T
            - 3.83508e-15) * $T
            + 4.237343e-13) * $T
            + 8.8555011e-11) * $T
            - 4.77258489e-8) * $T
            - 1.1297037031e-5) * $T
            + 1.4732069041e-4) * $T
            - 0.552891801772) * $T2;

        // Mean distance of moon from its ascending node = F
        $this->NF = $this->mods3600(1739232000.0 * $fracT + 295263.0983 * $T - 2.079419901760e-01 * $T + 335779.55755);

        // Mean anomaly of moon = l
        $this->MP = $this->mods3600(1717200000.0 * $fracT + 715923.4728 * $T - 2.035946368532e-01 * $T + 485868.28096);

        // Mean elongation of moon = D
        $this->D = $this->mods3600(1601856000.0 * $fracT + 1105601.4603 * $T + 3.962893294503e-01 * $T + 1072260.73512);

        // Mean longitude of moon, referred to the mean ecliptic and equinox of date
        $this->SWELP = $this->mods3600(1731456000.0 * $fracT + 1108372.83264 * $T - 6.784914260953e-01 * $T + 785939.95571);

        // Higher degree secular terms found by least squares fit
        $z = MoshierMoonData::Z;
        $this->NF += (($z[2] * $T + $z[1]) * $T + $z[0]) * $T2;
        $this->MP += (($z[5] * $T + $z[4]) * $T + $z[3]) * $T2;
        $this->D += (($z[8] * $T + $z[7]) * $T + $z[6]) * $T2;
        $this->SWELP += (($z[11] * $T + $z[10]) * $T + $z[9]) * $T2;
    }

    /**
     * Mean longitudes of planets (Laskar, Bretagnon)
     *
     * @see swemmoon.c mean_elements_pl()
     */
    private function meanElementsPl(): void
    {
        $T = $this->T;
        $T2 = $this->T2;

        // Venus
        $this->Ve = $this->mods3600(210664136.4335482 * $T + 655127.283046);
        $this->Ve += ((((((((
            -9.36e-023 * $T
            - 1.95e-20) * $T
            + 6.097e-18) * $T
            + 4.43201e-15) * $T
            + 2.509418e-13) * $T
            - 3.0622898e-10) * $T
            - 2.26602516e-9) * $T
            - 1.4244812531e-5) * $T
            + 0.005871373088) * $T2;

        // Earth
        $this->Ea = $this->mods3600(129597742.26669231 * $T + 361679.214649);
        $this->Ea += ((((((((
            -1.16e-22 * $T
            + 2.976e-19) * $T
            + 2.8460e-17) * $T
            - 1.08402e-14) * $T
            - 1.226182e-12) * $T
            + 1.7228268e-10) * $T
            + 1.515912254e-7) * $T
            + 8.863982531e-6) * $T
            - 2.0199859001e-2) * $T2;

        // Mars
        $this->Ma = $this->mods3600(68905077.59284 * $T + 1279559.78866);
        $this->Ma += (-1.043e-5 * $T + 9.38012e-3) * $T2;

        // Jupiter
        $this->Ju = $this->mods3600(10925660.428608 * $T + 123665.342120);
        $this->Ju += (1.543273e-5 * $T - 3.06037836351e-1) * $T2;

        // Saturn
        $this->Sa = $this->mods3600(4399609.65932 * $T + 180278.89694);
        $this->Sa += ((4.475946e-8 * $T - 6.874806E-5) * $T + 7.56161437443E-1) * $T2;
    }

    /**
     * First group of perturbations (T², T³ terms)
     *
     * @see swemmoon.c moon1()
     */
    private function moon1(): void
    {
        $T = $this->T;
        $z = MoshierMoonData::Z;

        // Initialize sin/cos tables
        for ($i = 0; $i < 5; $i++) {
            for ($j = 0; $j < 8; $j++) {
                $this->ss[$i][$j] = 0.0;
                $this->cc[$i][$j] = 0.0;
            }
        }

        $this->sscc(0, self::STR * $this->D, 6);
        $this->sscc(1, self::STR * $this->M, 4);
        $this->sscc(2, self::STR * $this->MP, 4);
        $this->sscc(3, self::STR * $this->NF, 4);

        $this->moonpol[0] = 0.0;
        $this->moonpol[1] = 0.0;
        $this->moonpol[2] = 0.0;

        // Terms in T^2, scale 1.0 = 10^-5"
        $this->chewm(MoshierMoonData::LRT2, MoshierMoonData::NLRT2, 4, 2);
        $this->chewm(MoshierMoonData::BT2, MoshierMoonData::NBT2, 4, 4);

        $this->f = 18 * $this->Ve - 16 * $this->Ea;

        $this->g = self::STR * ($this->f - $this->MP);  // 18V - 16E - l
        $this->cg = cos($this->g);
        $this->sg = sin($this->g);
        $l = 6.367278 * $this->cg + 12.747036 * $this->sg;  // t^0
        $this->l1 = 23123.70 * $this->cg - 10570.02 * $this->sg;  // t^1
        $this->l2 = $z[12] * $this->cg + $z[13] * $this->sg;      // t^2
        $this->moonpol[2] += 5.01 * $this->cg + 2.72 * $this->sg;

        $this->g = self::STR * (10.0 * $this->Ve - 3.0 * $this->Ea - $this->MP);
        $this->cg = cos($this->g);
        $this->sg = sin($this->g);
        $l += -0.253102 * $this->cg + 0.503359 * $this->sg;
        $this->l1 += 1258.46 * $this->cg + 707.29 * $this->sg;
        $this->l2 += $z[14] * $this->cg + $z[15] * $this->sg;

        $this->g = self::STR * (8.0 * $this->Ve - 13.0 * $this->Ea);
        $this->cg = cos($this->g);
        $this->sg = sin($this->g);
        $l += -0.187231 * $this->cg - 0.127481 * $this->sg;
        $this->l1 += -319.87 * $this->cg - 18.34 * $this->sg;
        $this->l2 += $z[16] * $this->cg + $z[17] * $this->sg;

        $a = 4.0 * $this->Ea - 8.0 * $this->Ma + 3.0 * $this->Ju;
        $this->g = self::STR * $a;
        $this->cg = cos($this->g);
        $this->sg = sin($this->g);
        $l += -0.866287 * $this->cg + 0.248192 * $this->sg;
        $this->l1 += 41.87 * $this->cg + 1053.97 * $this->sg;
        $this->l2 += $z[18] * $this->cg + $z[19] * $this->sg;

        $this->g = self::STR * ($a - $this->MP);
        $this->cg = cos($this->g);
        $this->sg = sin($this->g);
        $l += -0.165009 * $this->cg + 0.044176 * $this->sg;
        $this->l1 += 4.67 * $this->cg + 201.55 * $this->sg;

        $this->g = self::STR * $this->f;  // 18V - 16E
        $this->cg = cos($this->g);
        $this->sg = sin($this->g);
        $l += 0.330401 * $this->cg + 0.661362 * $this->sg;
        $this->l1 += 1202.67 * $this->cg - 555.59 * $this->sg;
        $this->l2 += $z[20] * $this->cg + $z[21] * $this->sg;

        $this->g = self::STR * ($this->f - 2.0 * $this->MP);  // 18V - 16E - 2l
        $this->cg = cos($this->g);
        $this->sg = sin($this->g);
        $l += 0.352185 * $this->cg + 0.705041 * $this->sg;
        $this->l1 += 1283.59 * $this->cg - 586.43 * $this->sg;

        $this->g = self::STR * (2.0 * $this->Ju - 5.0 * $this->Sa);
        $this->cg = cos($this->g);
        $this->sg = sin($this->g);
        $l += -0.034700 * $this->cg + 0.160041 * $this->sg;
        $this->l2 += $z[22] * $this->cg + $z[23] * $this->sg;

        $this->g = self::STR * ($this->SWELP - $this->NF);
        $this->cg = cos($this->g);
        $this->sg = sin($this->g);
        $l += 0.000116 * $this->cg + 7.063040 * $this->sg;
        $this->l1 += 298.8 * $this->sg;

        // T^3 terms
        $sg = sin(self::STR * $this->M);
        $this->l3 = $z[24] * $sg;
        $this->l4 = 0.0;

        $this->g = self::STR * (2.0 * $this->D - $this->M);
        $sg = sin($this->g);
        $cg = cos($this->g);
        $this->moonpol[2] += -0.2655 * $cg * $T;

        $this->g = self::STR * ($this->M - $this->MP);
        $this->moonpol[2] += -0.1568 * cos($this->g) * $T;

        $this->g = self::STR * ($this->M + $this->MP);
        $this->moonpol[2] += 0.1309 * cos($this->g) * $T;

        $this->g = self::STR * (2.0 * ($this->D + $this->M) - $this->MP);
        $sg = sin($this->g);
        $cg = cos($this->g);
        $this->moonpol[2] += 0.5568 * $cg * $T;

        $this->l2 += $this->moonpol[0];

        $this->g = self::STR * (2.0 * $this->D - $this->M - $this->MP);
        $this->moonpol[2] += -0.1910 * cos($this->g) * $T;

        $this->moonpol[1] *= $T;
        $this->moonpol[2] *= $T;

        // Terms in T
        $this->moonpol[0] = 0.0;
        $this->chewm(MoshierMoonData::BT, MoshierMoonData::NBT, 4, 4);
        $this->chewm(MoshierMoonData::LRT, MoshierMoonData::NLRT, 4, 1);

        $this->g = self::STR * ($this->f - $this->MP - $this->NF - 2355767.6);  // 18V - 16E - l - F
        $this->moonpol[1] += -1127.0 * sin($this->g);

        $this->g = self::STR * ($this->f - $this->MP + $this->NF - 235353.6);  // 18V - 16E - l + F
        $this->moonpol[1] += -1123.0 * sin($this->g);

        $this->g = self::STR * ($this->Ea + $this->D + 51987.6);
        $this->moonpol[1] += 1303.0 * sin($this->g);

        $this->g = self::STR * $this->SWELP;
        $this->moonpol[1] += 342.0 * sin($this->g);

        $this->g = self::STR * (2.0 * $this->Ve - 3.0 * $this->Ea);
        $this->cg = cos($this->g);
        $this->sg = sin($this->g);
        $l += -0.343550 * $this->cg - 0.000276 * $this->sg;
        $this->l1 += 105.90 * $this->cg + 336.53 * $this->sg;

        $this->g = self::STR * ($this->f - 2.0 * $this->D);  // 18V - 16E - 2D
        $this->cg = cos($this->g);
        $this->sg = sin($this->g);
        $l += 0.074668 * $this->cg + 0.149501 * $this->sg;
        $this->l1 += 271.77 * $this->cg - 124.20 * $this->sg;

        $this->g = self::STR * ($this->f - 2.0 * $this->D - $this->MP);
        $this->cg = cos($this->g);
        $this->sg = sin($this->g);
        $l += 0.073444 * $this->cg + 0.147094 * $this->sg;
        $this->l1 += 265.24 * $this->cg - 121.16 * $this->sg;

        $this->g = self::STR * ($this->f + 2.0 * $this->D - $this->MP);
        $this->cg = cos($this->g);
        $this->sg = sin($this->g);
        $l += 0.072844 * $this->cg + 0.145829 * $this->sg;
        $this->l1 += 265.18 * $this->cg - 121.29 * $this->sg;

        $this->g = self::STR * ($this->f + 2.0 * ($this->D - $this->MP));
        $this->cg = cos($this->g);
        $this->sg = sin($this->g);
        $l += 0.070201 * $this->cg + 0.140542 * $this->sg;
        $this->l1 += 255.36 * $this->cg - 116.79 * $this->sg;

        $this->g = self::STR * ($this->Ea + $this->D - $this->NF);
        $this->cg = cos($this->g);
        $this->sg = sin($this->g);
        $l += 0.288209 * $this->cg - 0.025901 * $this->sg;
        $this->l1 += -63.51 * $this->cg - 240.14 * $this->sg;

        $this->g = self::STR * (2.0 * $this->Ea - 3.0 * $this->Ju + 2.0 * $this->D - $this->MP);
        $this->cg = cos($this->g);
        $this->sg = sin($this->g);
        $l += 0.077865 * $this->cg + 0.438460 * $this->sg;
        $this->l1 += 210.57 * $this->cg + 124.84 * $this->sg;

        $this->g = self::STR * ($this->Ea - 2.0 * $this->Ma);
        $this->cg = cos($this->g);
        $this->sg = sin($this->g);
        $l += -0.216579 * $this->cg + 0.241702 * $this->sg;
        $this->l1 += 197.67 * $this->cg + 125.23 * $this->sg;

        $this->g = self::STR * ($a + $this->MP);
        $this->cg = cos($this->g);
        $this->sg = sin($this->g);
        $l += -0.165009 * $this->cg + 0.044176 * $this->sg;
        $this->l1 += 4.67 * $this->cg + 201.55 * $this->sg;

        $this->g = self::STR * ($a + 2.0 * $this->D - $this->MP);
        $this->cg = cos($this->g);
        $this->sg = sin($this->g);
        $l += -0.133533 * $this->cg + 0.041116 * $this->sg;
        $this->l1 += 6.95 * $this->cg + 187.07 * $this->sg;

        $this->g = self::STR * ($a - 2.0 * $this->D + $this->MP);
        $this->cg = cos($this->g);
        $this->sg = sin($this->g);
        $l += -0.133430 * $this->cg + 0.041079 * $this->sg;
        $this->l1 += 6.28 * $this->cg + 169.08 * $this->sg;

        $this->g = self::STR * (3.0 * $this->Ve - 4.0 * $this->Ea);
        $this->cg = cos($this->g);
        $this->sg = sin($this->g);
        $l += -0.175074 * $this->cg + 0.003035 * $this->sg;
        $this->l1 += 49.17 * $this->cg + 150.57 * $this->sg;

        $this->g = self::STR * (2.0 * ($this->Ea + $this->D - $this->MP) - 3.0 * $this->Ju + 213534.0);
        $this->l1 += 158.4 * sin($this->g);

        $this->l1 += $this->moonpol[0];

        $a = 0.1 * $T;  // set amplitude scale of 1.0 = 10^-4 arcsec
        $this->moonpol[1] *= $a;
        $this->moonpol[2] *= $a;

        $this->moonL = $l;
    }

    /**
     * Second group of perturbations (T⁰ terms)
     *
     * @see swemmoon.c moon2()
     */
    private function moon2(): void
    {
        $l = $this->moonL;

        // Terms in T^0
        $this->g = self::STR * (2 * ($this->Ea - $this->Ju + $this->D) - $this->MP + 648431.172);
        $l += 1.14307 * sin($this->g);

        $this->g = self::STR * ($this->Ve - $this->Ea + 648035.568);
        $l += 0.82155 * sin($this->g);

        $this->g = self::STR * (3 * ($this->Ve - $this->Ea) + 2 * $this->D - $this->MP + 647933.184);
        $l += 0.64371 * sin($this->g);

        $this->g = self::STR * ($this->Ea - $this->Ju + 4424.04);
        $l += 0.63880 * sin($this->g);

        $this->g = self::STR * ($this->SWELP + $this->MP - $this->NF + 4.68);
        $l += 0.49331 * sin($this->g);

        $this->g = self::STR * ($this->SWELP - $this->MP - $this->NF + 4.68);
        $l += 0.4914 * sin($this->g);

        $this->g = self::STR * ($this->SWELP + $this->NF + 2.52);
        $l += 0.36061 * sin($this->g);

        $this->g = self::STR * (2.0 * $this->Ve - 2.0 * $this->Ea + 736.2);
        $l += 0.30154 * sin($this->g);

        $this->g = self::STR * (2.0 * $this->Ea - 3.0 * $this->Ju + 2.0 * $this->D - 2.0 * $this->MP + 36138.2);
        $l += 0.28282 * sin($this->g);

        $this->g = self::STR * (2.0 * $this->Ea - 2.0 * $this->Ju + 2.0 * $this->D - 2.0 * $this->MP + 311.0);
        $l += 0.24516 * sin($this->g);

        $this->g = self::STR * ($this->Ea - $this->Ju - 2.0 * $this->D + $this->MP + 6275.88);
        $l += 0.21117 * sin($this->g);

        $this->g = self::STR * (2.0 * ($this->Ea - $this->Ma) - 846.36);
        $l += 0.19444 * sin($this->g);

        $this->g = self::STR * (2.0 * ($this->Ea - $this->Ju) + 1569.96);
        $l -= 0.18457 * sin($this->g);

        $this->g = self::STR * (2.0 * ($this->Ea - $this->Ju) - $this->MP - 55.8);
        $l += 0.18256 * sin($this->g);

        $this->g = self::STR * ($this->Ea - $this->Ju - 2.0 * $this->D + 6490.08);
        $l += 0.16499 * sin($this->g);

        $this->g = self::STR * ($this->Ea - 2.0 * $this->Ju - 212378.4);
        $l += 0.16427 * sin($this->g);

        $this->g = self::STR * (2.0 * ($this->Ve - $this->Ea - $this->D) + $this->MP + 1122.48);
        $l += 0.16088 * sin($this->g);

        $this->g = self::STR * ($this->Ve - $this->Ea - $this->MP + 32.04);
        $l -= 0.15350 * sin($this->g);

        $this->g = self::STR * ($this->Ea - $this->Ju - $this->MP + 4488.88);
        $l += 0.14346 * sin($this->g);

        $this->g = self::STR * (2.0 * ($this->Ve - $this->Ea + $this->D) - $this->MP - 8.64);
        $l += 0.13594 * sin($this->g);

        $this->g = self::STR * (2.0 * ($this->Ve - $this->Ea - $this->D) + 1319.76);
        $l += 0.13432 * sin($this->g);

        $this->g = self::STR * ($this->Ve - $this->Ea - 2.0 * $this->D + $this->MP - 56.16);
        $l -= 0.13122 * sin($this->g);

        $this->g = self::STR * ($this->Ve - $this->Ea + $this->MP + 54.36);
        $l -= 0.12722 * sin($this->g);

        $this->g = self::STR * (3.0 * ($this->Ve - $this->Ea) - $this->MP + 433.8);
        $l += 0.12539 * sin($this->g);

        $this->g = self::STR * ($this->Ea - $this->Ju + $this->MP + 4002.12);
        $l += 0.10994 * sin($this->g);

        $this->g = self::STR * (20.0 * $this->Ve - 21.0 * $this->Ea - 2.0 * $this->D + $this->MP - 317511.72);
        $l += 0.10652 * sin($this->g);

        $this->g = self::STR * (26.0 * $this->Ve - 29.0 * $this->Ea - $this->MP + 270002.52);
        $l += 0.10490 * sin($this->g);

        $this->g = self::STR * (3.0 * $this->Ve - 4.0 * $this->Ea + $this->D - $this->MP - 322765.56);
        $l += 0.10386 * sin($this->g);

        // Latitude terms
        $this->g = self::STR * ($this->SWELP + 648002.556);
        $this->B = 8.04508 * sin($this->g);

        $this->g = self::STR * ($this->Ea + $this->D + 996048.252);
        $this->B += 1.51021 * sin($this->g);

        $this->g = self::STR * ($this->f - $this->MP + $this->NF + 95554.332);
        $this->B += 0.63037 * sin($this->g);

        $this->g = self::STR * ($this->f - $this->MP - $this->NF + 95553.792);
        $this->B += 0.63014 * sin($this->g);

        $this->g = self::STR * ($this->SWELP - $this->MP + 2.9);
        $this->B += 0.45587 * sin($this->g);

        $this->g = self::STR * ($this->SWELP + $this->MP + 2.5);
        $this->B += -0.41573 * sin($this->g);

        $this->g = self::STR * ($this->SWELP - 2.0 * $this->NF + 3.2);
        $this->B += 0.32623 * sin($this->g);

        $this->g = self::STR * ($this->SWELP - 2.0 * $this->D + 2.5);
        $this->B += 0.29855 * sin($this->g);

        $this->moonL = $l;
    }

    /**
     * Third phase: apply main tables LR and MB
     *
     * @see swemmoon.c moon3()
     */
    private function moon3(): void
    {
        $T = $this->T;

        // Terms in T^0
        $this->moonpol[0] = 0.0;
        $this->chewm(MoshierMoonData::LR, MoshierMoonData::NLR, 4, 1);
        $this->chewm(MoshierMoonData::MB, MoshierMoonData::NMB, 4, 3);

        $l = $this->moonL;
        $l += ((($this->l4 * $T + $this->l3) * $T + $this->l2) * $T + $this->l1) * $T * 1.0e-5;

        $this->moonpol[0] = $this->SWELP + $l + 1.0e-4 * $this->moonpol[0];
        $this->moonpol[1] = 1.0e-4 * $this->moonpol[1] + $this->B;
        $this->moonpol[2] = 1.0e-4 * $this->moonpol[2] + 385000.52899;  // kilometers
    }

    /**
     * Compute final ecliptic polar coordinates
     *
     * @see swemmoon.c moon4()
     */
    private function moon4(): void
    {
        // Convert distance from km to AU
        // Note: AUNIT is in km (149597870.7 km = 1 AU)
        // C uses AUNIT in meters (1.4959787e11 m), so C has /= AUNIT/1000
        // We have AUNIT in km, so we just divide by AUNIT
        $this->moonpol[2] /= self::AUNIT;
        $this->moonpol[0] = self::STR * $this->mods3600($this->moonpol[0]);
        $this->moonpol[1] = self::STR * $this->moonpol[1];
        $this->B = $this->moonpol[1];
    }

    /**
     * Program to step through the perturbation table
     *
     * @param array $pt Perturbation table
     * @param int $nlines Number of lines in table
     * @param int $nangles Number of angle factors per line
     * @param int $typflg Type flag (1=large lon/rad, 2=lon/rad, 3=large lat, 4=lat)
     */
    private function chewm(array $pt, int $nlines, int $nangles, int $typflg): void
    {
        $ptr = 0;

        for ($i = 0; $i < $nlines; $i++) {
            $k1 = 0;
            $sv = 0.0;
            $cv = 0.0;

            for ($m = 0; $m < $nangles; $m++) {
                $j = $pt[$ptr++];  // multiple angle factor
                if ($j != 0) {
                    $k = abs($j);  // make angle factor > 0
                    // sin, cos (k*angle) from lookup table
                    $su = $this->ss[$m][$k - 1];
                    $cu = $this->cc[$m][$k - 1];
                    if ($j < 0) {
                        $su = -$su;  // negative angle factor
                    }
                    if ($k1 == 0) {
                        // Set sin, cos of first angle
                        $sv = $su;
                        $cv = $cu;
                        $k1 = 1;
                    } else {
                        // Combine angles by trigonometry
                        $ff = $su * $cv + $cu * $sv;
                        $cv = $cu * $cv - $su * $sv;
                        $sv = $ff;
                    }
                }
            }

            // Accumulate
            switch ($typflg) {
                case 1:
                    // Large longitude and radius
                    $j = $pt[$ptr++];
                    $k = $pt[$ptr++];
                    $this->moonpol[0] += (10000.0 * $j + $k) * $sv;
                    $j = $pt[$ptr++];
                    $k = $pt[$ptr++];
                    if ($k != 0) {
                        $this->moonpol[2] += (10000.0 * $j + $k) * $cv;
                    }
                    break;

                case 2:
                    // Longitude and radius
                    $j = $pt[$ptr++];
                    $k = $pt[$ptr++];
                    $this->moonpol[0] += $j * $sv;
                    $this->moonpol[2] += $k * $cv;
                    break;

                case 3:
                    // Large latitude
                    $j = $pt[$ptr++];
                    $k = $pt[$ptr++];
                    $this->moonpol[1] += (10000.0 * $j + $k) * $sv;
                    break;

                case 4:
                    // Latitude
                    $j = $pt[$ptr++];
                    $this->moonpol[1] += $j * $sv;
                    break;
            }
        }
    }

    /**
     * Prepare lookup table of sin and cos (i*Lj) for required multiple angles
     *
     * @param int $k Index (0=D, 1=M, 2=MP, 3=NF)
     * @param float $arg Argument in radians
     * @param int $n Number of multiples to compute
     */
    private function sscc(int $k, float $arg, int $n): void
    {
        $su = sin($arg);
        $cu = cos($arg);

        $this->ss[$k][0] = $su;  // sin(L)
        $this->cc[$k][0] = $cu;  // cos(L)

        $sv = 2.0 * $su * $cu;
        $cv = $cu * $cu - $su * $su;

        $this->ss[$k][1] = $sv;  // sin(2L)
        $this->cc[$k][1] = $cv;

        for ($i = 2; $i < $n; $i++) {
            $s = $su * $cv + $cu * $sv;
            $cv = $cu * $cv - $su * $sv;
            $sv = $s;
            $this->ss[$k][$i] = $sv;  // sin((i+1)L)
            $this->cc[$k][$i] = $cv;
        }
    }

    /**
     * Reduce arc seconds modulo 360 degrees
     *
     * @param float $x Value in arc seconds
     * @return float Reduced value in arc seconds
     */
    private function mods3600(float $x): float
    {
        return $x - 1296000.0 * floor($x / 1296000.0);
    }

    /**
     * Convert from polar coordinates of ecliptic of date
     * to cartesian coordinates of equator 2000
     *
     * @param float $tjd Julian day
     * @param array $xpm Position array (modified in place)
     * @param SwedState|null $swed State object
     */
    private function ecldatEqu2000(float $tjd, array &$xpm, ?SwedState $swed = null): void
    {
        // Polar to cartesian
        $this->polcart($xpm);

        // Get obliquity of date
        // Use swed.oec if available (should be initialized by caller)
        // Otherwise, compute mean obliquity as fallback
        if ($swed !== null && $swed->oec !== null && abs($swed->oec->teps - $tjd) < 1.0) {
            // Use cached obliquity from state
            $seps = $swed->oec->seps;
            $ceps = $swed->oec->ceps;
        } else {
            // Fallback: compute mean obliquity
            $eps = $this->meanObliquity($tjd);
            $seps = sin($eps);
            $ceps = cos($eps);
        }

        // Ecliptic to equatorial
        $this->coortrf2($xpm, -$seps, $ceps);

        // Precess to J2000 using main Precession class
        // Direction: +1 = from date to J2000 (J_TO_J2000)
        Precession::precess($xpm, $tjd, 0, 1);
    }

    /**
     * Polar to cartesian conversion
     */
    private function polcart(array &$pol): void
    {
        $lon = $pol[0];
        $lat = $pol[1];
        $rad = $pol[2];

        $cosLat = cos($lat);
        $pol[0] = $rad * $cosLat * cos($lon);
        $pol[1] = $rad * $cosLat * sin($lon);
        $pol[2] = $rad * sin($lat);
    }

    /**
     * Coordinate transformation (rotation about X axis)
     */
    private function coortrf2(array &$x, float $sineps, float $coseps): void
    {
        $y = $x[1];
        $z = $x[2];
        $x[1] = $y * $coseps + $z * $sineps;
        $x[2] = -$y * $sineps + $z * $coseps;
    }

    /**
     * Simple precession from date to J2000 (or vice versa)
    /**
     * Mean obliquity of the ecliptic (simplified for fallback)
     */
    private function meanObliquity(float $tjd): float
    {
        $T = ($tjd - self::J2000) / 36525.0;

        // IAU 1976 value
        $eps = 84381.448 - 46.8150 * $T - 0.00059 * $T * $T + 0.001813 * $T * $T * $T;

        return $eps * self::STR;
    }

    // ========================================================================
    // MEAN NODE AND APOGEE FUNCTIONS
    // ========================================================================

    /**
     * Correction for mean node from DE431 fitted data
     * Returns correction in degrees
     *
     * Port of corr_mean_node() from swemmoon.c
     */
    public function corrMeanNode(float $J): float
    {
        $J0 = MoshierMoonCorrections::JD_T0_GREG; // 1 jan -13100 greg
        $dayscty = MoshierMoonCorrections::DAYS_PER_CENTURY; // 36524.25

        if ($J < MoshierMoonCorrections::JPL_DE431_START) {
            return 0.0;
        }
        if ($J > MoshierMoonCorrections::JPL_DE431_END) {
            return 0.0;
        }

        $dJ = $J - $J0;
        $i = (int) floor($dJ / $dayscty); // centuries = index of lower correction value
        $dfrac = ($dJ - $i * $dayscty) / $dayscty;

        $dcor0 = MoshierMoonCorrections::MEAN_NODE_CORR[$i];
        $dcor1 = MoshierMoonCorrections::MEAN_NODE_CORR[$i + 1];

        $dcor = $dcor0 + $dfrac * ($dcor1 - $dcor0);

        return $dcor;
    }

    /**
     * Mean lunar ascending node
     *
     * Port of swi_mean_node() from swemmoon.c
     *
     * @param float $J Julian day
     * @param array &$pol Return array for position (polar coordinates of ecliptic of date)
     * @param string|null &$serr Error return string
     * @return int OK or ERR
     */
    public function meanNode(float $J, array &$pol, ?string &$serr = null): int
    {
        $this->T = ($J - self::J2000) / 36525.0;
        $this->T2 = $this->T * $this->T;
        $this->T3 = $this->T * $this->T2;
        $this->T4 = $this->T2 * $this->T2;

        // Check range
        if ($J < self::MOSHNDEPH_START || $J > self::MOSHNDEPH_END) {
            if ($serr !== null) {
                $serr .= sprintf(
                    "jd %f outside mean node range %.2f .. %.2f ",
                    $J, self::MOSHNDEPH_START, self::MOSHNDEPH_END
                );
            }
            return -1; // ERR
        }

        $this->meanElements();

        // Correction from DE431 fitted data
        $dcor = $this->corrMeanNode($J) * 3600.0; // degrees to arcsec

        // Mean node = SWELP - NF
        $pol[0] = $this->swiMod2PI(($this->SWELP - $this->NF - $dcor) * self::STR);
        $pol[1] = 0.0;
        $pol[2] = self::MOON_MEAN_DIST / self::AUNIT;

        return 0; // OK
    }

    /**
     * Correction for mean apogee from DE431 fitted data
     * Returns correction in degrees
     *
     * Port of corr_mean_apog() from swemmoon.c
     */
    public function corrMeanApog(float $J): float
    {
        $J0 = MoshierMoonCorrections::JD_T0_GREG; // 1 jan -13100 greg
        $dayscty = MoshierMoonCorrections::DAYS_PER_CENTURY; // 36524.25

        if ($J < MoshierMoonCorrections::JPL_DE431_START) {
            return 0.0;
        }
        if ($J > MoshierMoonCorrections::JPL_DE431_END) {
            return 0.0;
        }

        $dJ = $J - $J0;
        $i = (int) floor($dJ / $dayscty); // centuries = index of lower correction value
        $dfrac = ($dJ - $i * $dayscty) / $dayscty;

        $dcor0 = MoshierMoonCorrections::MEAN_APSIS_CORR[$i];
        $dcor1 = MoshierMoonCorrections::MEAN_APSIS_CORR[$i + 1];

        $dcor = $dcor0 + $dfrac * ($dcor1 - $dcor0);

        return $dcor;
    }

    /**
     * Mean lunar apogee ('dark moon', 'lilith')
     *
     * Port of swi_mean_apog() from swemmoon.c
     *
     * @param float $J Julian day
     * @param array &$pol Return array for position (polar coordinates of ecliptic of date)
     * @param string|null &$serr Error return string
     * @return int OK or ERR
     */
    public function meanApog(float $J, array &$pol, ?string &$serr = null): int
    {
        $this->T = ($J - self::J2000) / 36525.0;
        $this->T2 = $this->T * $this->T;
        $this->T3 = $this->T * $this->T2;
        $this->T4 = $this->T2 * $this->T2;

        // Check range
        if ($J < self::MOSHNDEPH_START || $J > self::MOSHNDEPH_END) {
            if ($serr !== null) {
                $serr .= sprintf(
                    "jd %f outside mean apogee range %.2f .. %.2f ",
                    $J, self::MOSHNDEPH_START, self::MOSHNDEPH_END
                );
            }
            return -1; // ERR
        }

        $this->meanElements();

        // Mean apogee = SWELP - MP + 180°
        $pol[0] = $this->swiMod2PI(($this->SWELP - $this->MP) * self::STR + M_PI);
        $pol[1] = 0.0;
        $pol[2] = self::MOON_MEAN_DIST * (1.0 + self::MOON_MEAN_ECC) / self::AUNIT; // apogee distance

        /*
         * Lilith or Dark Moon is either the empty focal point of the mean
         * lunar ellipse or, for some people, its apogee ("aphelion").
         * This is 180 degrees from the perigee.
         *
         * Since the lunar orbit is not in the ecliptic, the apogee must be
         * projected onto the ecliptic.
         */

        // Apply apogee correction from DE431
        $dcor = $this->corrMeanApog($J) * (M_PI / 180.0); // degrees to radians
        $pol[0] = $this->swiMod2PI($pol[0] - $dcor);

        // Get mean node for projection
        $node = ($this->SWELP - $this->NF) * self::STR;
        $dcorNode = $this->corrMeanNode($J) * (M_PI / 180.0);
        $node = $this->swiMod2PI($node - $dcorNode);

        // Project apogee onto ecliptic
        $pol[0] = $this->swiMod2PI($pol[0] - $node);

        // Convert to cartesian
        $this->polcart($pol, $pol);

        // Rotate by mean inclination
        $this->coortrf2($pol, $pol, -self::MOON_MEAN_INCL * M_PI / 180.0);

        // Convert back to polar
        $this->cartpol($pol, $pol);

        // Add node back
        $pol[0] = $this->swiMod2PI($pol[0] + $node);

        return 0; // OK
    }

    /**
     * Modulo 2*PI, result always positive
     */
    private function swiMod2PI(float $x): float
    {
        $y = fmod($x, 2.0 * M_PI);
        if ($y < 0.0) {
            $y += 2.0 * M_PI;
        }
        return $y;
    }

    /**
     * Cartesian to polar coordinates
     */
    private function cartpol(array $x, array &$pol): void
    {
        $r = sqrt($x[0] * $x[0] + $x[1] * $x[1] + $x[2] * $x[2]);
        $rho = sqrt($x[0] * $x[0] + $x[1] * $x[1]);

        if ($rho > 0.0) {
            $pol[0] = atan2($x[1], $x[0]);
            if ($pol[0] < 0.0) {
                $pol[0] += 2.0 * M_PI;
            }
        } else {
            $pol[0] = 0.0;
        }

        if ($r > 0.0) {
            $pol[1] = asin($x[2] / $r);
        } else {
            $pol[1] = 0.0;
        }

        $pol[2] = $r;
    }

    /**
     * Get mean lunar elements
     *
     * Port of swi_mean_lunar_elements() from swemmoon.c
     *
     * @param float $tjd Julian day
     * @param float &$node Mean ascending node (degrees)
     * @param float &$dnode Daily motion of node (degrees/day)
     * @param float &$peri Mean perigee (degrees)
     * @param float &$dperi Daily motion of perigee (degrees/day)
     */
    public function meanLunarElements(float $tjd, float &$node, float &$dnode, float &$peri, float &$dperi): void
    {
        $this->T = ($tjd - self::J2000) / 36525.0;
        $this->T2 = $this->T * $this->T;
        $this->T3 = $this->T * $this->T2;
        $this->T4 = $this->T2 * $this->T2;

        $this->meanElements();

        // Normalize to degrees
        $node = $this->sweDegnorm(($this->SWELP - $this->NF) * self::STR * (180.0 / M_PI));
        $peri = $this->sweDegnorm(($this->SWELP - $this->MP) * self::STR * (180.0 / M_PI));

        // Calculate daily motion (using T - 1 day in centuries)
        $this->T -= 1.0 / 36525.0;
        $this->T2 = $this->T * $this->T;
        $this->T3 = $this->T * $this->T2;
        $this->T4 = $this->T2 * $this->T2;

        $this->meanElements();

        $dnode = $this->sweDegnorm($node - ($this->SWELP - $this->NF) * self::STR * (180.0 / M_PI));
        $dnode -= 360.0; // Node moves retrograde

        $dperi = $this->sweDegnorm($peri - ($this->SWELP - $this->MP) * self::STR * (180.0 / M_PI));

        // Apply DE431 corrections
        $dcor = $this->corrMeanNode($tjd);
        $node = $this->sweDegnorm($node - $dcor);

        $dcor = $this->corrMeanApog($tjd);
        $peri = $this->sweDegnorm($peri - $dcor);
    }

    /**
     * Normalize degrees to 0..360
     */
    private function sweDegnorm(float $x): float
    {
        $y = fmod($x, 360.0);
        if ($y < 0.0) {
            $y += 360.0;
        }
        return $y;
    }

    /**
     * Calculate geometric coordinates of true interpolated Moon apsides
     *
     * Port of swi_intp_apsides() from swemmoon.c
     *
     * @param float $J Julian day
     * @param array &$pol Return array for position (longitude, latitude, distance in radians/AU)
     * @param int $ipli Planet index (SEI_INTP_PERG=5 for perigee, SEI_INTP_APOG=4 for apogee)
     * @return int OK
     */
    public function intpApsides(float $J, array &$pol, int $ipli): int
    {
        // Internal indices for apsides (from sweph.h)
        $SEI_INTP_APOG = 4;
        $SEI_INTP_PERG = 5;

        $zMP = 27.55454988; // Mean synodic month in days
        $fNF = 27.212220817 / $zMP;
        $fD = 29.530588835 / $zMP;
        $fLP = 27.321582 / $zMP;
        $fM = 365.2596359 / $zMP;
        $fVe = 224.7008001 / $zMP;
        $fEa = 365.2563629 / $zMP;
        $fMa = 686.9798519 / $zMP;
        $fJu = 4332.589348 / $zMP;
        $fSa = 10759.22722 / $zMP;

        $this->T = ($J - self::J2000) / 36525.0;
        $this->T2 = $this->T * $this->T;
        $this->T3 = $this->T * $this->T2;
        $this->T4 = $this->T2 * $this->T2;

        $this->meanElements();
        $this->meanElementsPl();

        // Save original mean elements (without normalization for planets)
        $sM = $this->M;
        $sVe = $this->Ve;
        $sEa = $this->Ea;
        $sMa = $this->Ma;
        $sJu = $this->Ju;
        $sSa = $this->Sa;

        // Save and normalize lunar mean elements
        $sNF = $this->mods3600($this->NF);
        $sD = $this->mods3600($this->D);
        $sLP = $this->mods3600($this->SWELP);
        $sMP = $this->mods3600($this->MP);

        // Set target for perigee or apogee
        $niter = 4;
        if ($ipli === $SEI_INTP_PERG) {
            $this->MP = 0.0;
            $niter = 5;
        }
        if ($ipli === $SEI_INTP_APOG) {
            $this->MP = 648000.0; // 180 degrees in arcsec
            $niter = 4;
        }

        $cMP = 0.0;
        $dd = 18000.0;
        $rsv = [0.0, 0.0, 0.0];

        for ($iii = 0; $iii <= $niter; $iii++) {
            $dMP = $sMP - $this->MP;
            $mLP = $sLP - $dMP;
            $mNF = $sNF - $dMP;
            $mD = $sD - $dMP;
            $mMP = $sMP - $dMP;

            for ($ii = 0; $ii <= 2; $ii++) {
                $this->MP = $mMP + ($ii - 1) * $dd;
                $this->NF = $mNF + ($ii - 1) * $dd / $fNF;
                $this->D = $mD + ($ii - 1) * $dd / $fD;
                $this->SWELP = $mLP + ($ii - 1) * $dd / $fLP;
                $this->M = $sM + ($ii - 1) * $dd / $fM;
                $this->Ve = $sVe + ($ii - 1) * $dd / $fVe;
                $this->Ea = $sEa + ($ii - 1) * $dd / $fEa;
                $this->Ma = $sMa + ($ii - 1) * $dd / $fMa;
                $this->Ju = $sJu + ($ii - 1) * $dd / $fJu;
                $this->Sa = $sSa + ($ii - 1) * $dd / $fSa;

                $this->moon1();
                $this->moon2();
                $this->moon3();
                $this->moon4();

                if ($ii === 1) {
                    for ($i = 0; $i < 3; $i++) {
                        $pol[$i] = $this->moonpol[$i];
                    }
                }
                $rsv[$ii] = $this->moonpol[2];
            }

            // Parabolic interpolation to find extremum
            $denom = $rsv[0] + $rsv[2] - 2.0 * $rsv[1];
            if (abs($denom) > 1e-15) {
                $cMP = (1.5 * $rsv[0] - 2.0 * $rsv[1] + 0.5 * $rsv[2]) / $denom;
            } else {
                $cMP = 0.0;
            }
            $cMP *= $dd;
            $cMP = $cMP - $dd;
            $mMP += $cMP;
            $this->MP = $mMP;
            $dd /= 10.0;
        }

        return 0; // OK
    }
}
