<?php

declare(strict_types=1);

namespace Swisseph\Moshier;

use Swisseph\Constants;
use Swisseph\Coordinates;
use Swisseph\Precession;
use Swisseph\SwephFile\SwedState;
use Swisseph\Moshier\Tables\MercuryTable;
use Swisseph\Moshier\Tables\VenusTable;
use Swisseph\Moshier\Tables\EarthTable;
use Swisseph\Moshier\Tables\MarsTable;
use Swisseph\Moshier\Tables\JupiterTable;
use Swisseph\Moshier\Tables\SaturnTable;
use Swisseph\Moshier\Tables\UranusTable;
use Swisseph\Moshier\Tables\NeptuneTable;
use Swisseph\Moshier\Tables\PlutoTable;

/**
 * Moshier semi-analytical planetary theory calculator
 *
 * Full port of swemplan.c functions:
 * - swi_moshplan2(): Core heliocentric coordinate computation
 * - swi_moshplan(): Main entry point with Earth handling and speeds
 * - sscc(): Sine/cosine table builder
 * - embofs_mosh(): EMB to Earth offset
 *
 * @see с-swisseph/swisseph/swemplan.c
 */
final class MoshierPlanetCalculator
{
    /**
     * Arcseconds to radians
     * STR = 4.8481368110953599359e-6 = pi / (180 * 3600)
     */
    private const STR = 4.8481368110953599359e-6;

    /**
     * J2000.0 epoch
     */
    private const J2000 = 2451545.0;

    /**
     * J1900.0 epoch (for embofs_mosh)
     */
    private const J1900 = 2415020.0;

    /**
     * Degrees to radians
     */
    private const DEGTORAD = 0.017453292519943295;

    /**
     * Speed calculation interval in days
     * @see sweph.h PLAN_SPEED_INTV
     */
    private const PLAN_SPEED_INTV = 0.0001;

    /**
     * Internal planet indices
     */
    private const SEI_EARTH = 0;
    private const SEI_EMB = 2;  // Earth-Moon Barycenter Moshier index

    /**
     * Cached sine values ss[planet][harmonic]
     * @var array<int, array<int, float>>
     */
    private static array $ss = [];

    /**
     * Cached cosine values cc[planet][harmonic]
     * @var array<int, array<int, float>>
     */
    private static array $cc = [];

    /**
     * Planet tables cache
     * @var array<int, PlanetTable>
     */
    private static array $planets = [];

    /**
     * Obliquity J2000.0 (eps0 = 84381.406")
     * sin(eps0) and cos(eps0) for J2000 ecliptic-equator conversion
     */
    private const SEPS2000 = 0.3977771559319137;  // sin(23.4392911° * π/180)
    private const CEPS2000 = 0.9174821430670688;  // cos(23.4392911° * π/180)

    /**
     * Get planet table for Moshier planet index
     *
     * @param int $iplm Moshier planet index (0-8)
     * @return PlanetTable
     */
    private static function getPlanetTable(int $iplm): PlanetTable
    {
        if (!isset(self::$planets[$iplm])) {
            self::$planets[$iplm] = match ($iplm) {
                0 => MercuryTable::get(),
                1 => VenusTable::get(),
                2 => EarthTable::get(),
                3 => MarsTable::get(),
                4 => JupiterTable::get(),
                5 => SaturnTable::get(),
                6 => UranusTable::get(),
                7 => NeptuneTable::get(),
                8 => PlutoTable::get(),
                default => throw new \InvalidArgumentException("Invalid Moshier planet index: $iplm"),
            };
        }
        return self::$planets[$iplm];
    }

    /**
     * Build sine and cosine lookup tables for multiple angles
     *
     * Computes sin(j*arg) and cos(j*arg) for j = 1..n using
     * recurrence relations to avoid repeated trig calls.
     *
     * Port of sscc() from swemplan.c:387-408
     *
     * @param int $k Planet index (0-8)
     * @param float $arg Angle in radians
     * @param int $n Maximum harmonic number
     */
    private static function sscc(int $k, float $arg, int $n): void
    {
        $su = sin($arg);
        $cu = cos($arg);

        // sin(L), cos(L)
        self::$ss[$k][0] = $su;
        self::$cc[$k][0] = $cu;

        // sin(2L), cos(2L) using double angle formulas
        $sv = 2.0 * $su * $cu;
        $cv = $cu * $cu - $su * $su;
        self::$ss[$k][1] = $sv;
        self::$cc[$k][1] = $cv;

        // sin((i+1)*L), cos((i+1)*L) using recurrence
        for ($i = 2; $i < $n; $i++) {
            $s = $su * $cv + $cu * $sv;
            $cv = $cu * $cv - $su * $sv;
            $sv = $s;
            self::$ss[$k][$i] = $sv;
            self::$cc[$k][$i] = $cv;
        }
    }

    /**
     * Compute heliocentric ecliptic coordinates using Moshier theory
     *
     * Returns heliocentric ecliptic coordinates (J2000.0) in polar form:
     * - pobj[0] = longitude in radians
     * - pobj[1] = latitude in radians
     * - pobj[2] = distance in AU
     *
     * Port of swi_moshplan2() from swemplan.c:134-264
     *
     * @param float $J Julian day (TT)
     * @param int $iplm Moshier planet index (0=Mercury...8=Pluto)
     * @param array<float> &$pobj Output: [longitude, latitude, distance]
     * @return int 0 = OK
     */
    public static function moshplan2(float $J, int $iplm, array &$pobj): int
    {
        $plan = self::getPlanetTable($iplm);

        $T = ($J - self::J2000) / MoshierConstants::TIMESCALE;

        // Calculate sin(i*MM), cos(i*MM) for needed multiple angles
        for ($i = 0; $i < 9; $i++) {
            $j = $plan->maxHarmonic[$i];
            if ($j > 0) {
                $sr = (MoshierConstants::mods3600(MoshierConstants::FREQS[$i] * $T)
                       + MoshierConstants::PHASES[$i]) * self::STR;
                self::sscc($i, $sr, $j);
            }
        }

        // Get table pointers
        $argTbl = $plan->argTbl;
        $lonTbl = $plan->lonTbl;
        $latTbl = $plan->latTbl;
        $radTbl = $plan->radTbl;

        // Array indices
        $pIdx = 0;  // argTbl index
        $lIdx = 0;  // lonTbl index
        $bIdx = 0;  // latTbl index
        $rIdx = 0;  // radTbl index

        $sl = 0.0;  // sum longitude
        $sb = 0.0;  // sum latitude
        $sr = 0.0;  // sum radius

        // Process argument table
        while (true) {
            // Number of periodic arguments
            $np = $argTbl[$pIdx++];

            if ($np < 0) {
                // End of table marker (-1)
                break;
            }

            if ($np === 0) {
                // Polynomial term
                $nt = $argTbl[$pIdx++];

                // Longitude polynomial
                $cu = $lonTbl[$lIdx++];
                for ($ip = 0; $ip < $nt; $ip++) {
                    $cu = $cu * $T + $lonTbl[$lIdx++];
                }
                $sl += MoshierConstants::mods3600($cu);

                // Latitude polynomial
                $cu = $latTbl[$bIdx++];
                for ($ip = 0; $ip < $nt; $ip++) {
                    $cu = $cu * $T + $latTbl[$bIdx++];
                }
                $sb += $cu;

                // Radius polynomial
                $cu = $radTbl[$rIdx++];
                for ($ip = 0; $ip < $nt; $ip++) {
                    $cu = $cu * $T + $radTbl[$rIdx++];
                }
                $sr += $cu;

                continue;
            }

            // Periodic (harmonic) term
            $k1 = 0;
            $cv = 0.0;
            $sv = 0.0;

            for ($ip = 0; $ip < $np; $ip++) {
                // What harmonic
                $j = $argTbl[$pIdx++];
                // Which planet (1-based in table, 0-based in arrays)
                $m = $argTbl[$pIdx++] - 1;

                if ($j !== 0) {
                    $k = ($j < 0) ? -$j : $j;
                    $k -= 1;

                    $su = self::$ss[$m][$k];  // sin(k*angle)
                    if ($j < 0) {
                        $su = -$su;
                    }
                    $cu = self::$cc[$m][$k];

                    if ($k1 === 0) {
                        // Set first angle
                        $sv = $su;
                        $cv = $cu;
                        $k1 = 1;
                    } else {
                        // Combine angles using sin/cos addition formulas
                        $t = $su * $cv + $cu * $sv;
                        $cv = $cu * $cv - $su * $sv;
                        $sv = $t;
                    }
                }
            }

            // Highest power of T for this term
            $nt = $argTbl[$pIdx++];

            // Longitude
            $cu = $lonTbl[$lIdx++];
            $su = $lonTbl[$lIdx++];
            for ($ip = 0; $ip < $nt; $ip++) {
                $cu = $cu * $T + $lonTbl[$lIdx++];
                $su = $su * $T + $lonTbl[$lIdx++];
            }
            $sl += $cu * $cv + $su * $sv;

            // Latitude
            $cu = $latTbl[$bIdx++];
            $su = $latTbl[$bIdx++];
            for ($ip = 0; $ip < $nt; $ip++) {
                $cu = $cu * $T + $latTbl[$bIdx++];
                $su = $su * $T + $latTbl[$bIdx++];
            }
            $sb += $cu * $cv + $su * $sv;

            // Radius
            $cu = $radTbl[$rIdx++];
            $su = $radTbl[$rIdx++];
            for ($ip = 0; $ip < $nt; $ip++) {
                $cu = $cu * $T + $radTbl[$rIdx++];
                $su = $su * $T + $radTbl[$rIdx++];
            }
            $sr += $cu * $cv + $su * $sv;
        }

        // Convert to radians and AU
        $pobj[0] = self::STR * $sl;                          // longitude in radians
        $pobj[1] = self::STR * $sb;                          // latitude in radians
        $pobj[2] = self::STR * $plan->distance * $sr + $plan->distance;  // distance in AU

        return 0; // OK
    }

    /**
     * Adjust position from Earth-Moon barycenter to Earth
     *
     * Uses a short series for the Moon's position to compute
     * the offset from EMB to Earth's center.
     *
     * FULL port of embofs_mosh() from swemplan.c:415-491
     *
     * @param float $tjd Julian day (TT)
     * @param array<float> &$xemb Cartesian equatorial J2000 coordinates of EMB (modified in place)
     */
    public static function embofsMosh(float $tjd, array &$xemb): void
    {
        // Short series for position of the Moon
        $T = ($tjd - self::J1900) / 36525.0;

        // Mean anomaly of moon (MP)
        $a = self::degnorm(((1.44e-5 * $T + 0.009192) * $T + 477198.8491) * $T + 296.104608);
        $a *= self::DEGTORAD;
        $smp = sin($a);
        $cmp = cos($a);
        $s2mp = 2.0 * $smp * $cmp;      // sin(2*MP)
        $c2mp = $cmp * $cmp - $smp * $smp;  // cos(2*MP)

        // Mean elongation of moon (D)
        $a = self::degnorm(((1.9e-6 * $T - 0.001436) * $T + 445267.1142) * $T + 350.737486);
        $a = 2.0 * self::DEGTORAD * $a;
        $s2d = sin($a);
        $c2d = cos($a);

        // Mean distance of moon from its ascending node (F)
        $a = self::degnorm(((-3.e-7 * $T - 0.003211) * $T + 483202.0251) * $T + 11.250889);
        $a *= self::DEGTORAD;
        $sf = sin($a);
        $cf = cos($a);
        $s2f = 2.0 * $sf * $cf;  // sin(2*F)

        $sx = $s2d * $cmp - $c2d * $smp;  // sin(2D - MP)
        $cx = $c2d * $cmp + $s2d * $smp;  // cos(2D - MP)

        // Mean longitude of moon (LP)
        $L = ((1.9e-6 * $T - 0.001133) * $T + 481267.8831) * $T + 270.434164;

        // Mean anomaly of sun (M)
        $M = self::degnorm(((-3.3e-6 * $T - 1.50e-4) * $T + 35999.0498) * $T + 358.475833);

        // Ecliptic longitude of the moon
        $L = $L
            + 6.288750 * $smp
            + 1.274018 * $sx
            + 0.658309 * $s2d
            + 0.213616 * $s2mp
            - 0.185596 * sin(self::DEGTORAD * $M)
            - 0.114336 * $s2f;

        // Ecliptic latitude of the moon
        $af = $smp * $cf;
        $bx = $cmp * $sf;
        $B = 5.128189 * $sf
            + 0.280606 * ($af + $bx)     // sin(MP+F)
            + 0.277693 * ($af - $bx)     // sin(MP-F)
            + 0.173238 * ($s2d * $cf - $c2d * $sf);  // sin(2D-F)
        $B *= self::DEGTORAD;

        // Parallax of the moon
        $p = 0.950724
            + 0.051818 * $cmp
            + 0.009531 * $cx
            + 0.007843 * $c2d
            + 0.002824 * $c2mp;
        $p *= self::DEGTORAD;

        // Normalize longitude
        $L = self::degnorm($L);
        $L *= self::DEGTORAD;

        // Distance in AU
        $dist = 4.263523e-5 / sin($p);

        // Convert to rectangular ecliptic coordinates
        $xyz = [$L, $B, $dist];
        Coordinates::polCart($xyz, $xyz);

        // Convert to equatorial (ecliptic to equator of date)
        $seps = sin(self::meanObliquityRad($tjd));
        $ceps = cos(self::meanObliquityRad($tjd));
        Coordinates::coortrf2($xyz, $xyz, -$seps, $ceps);

        // Precess to equinox of J2000.0
        Precession::precess($xyz, $tjd, 0, 1);  // J_TO_J2000 = 1

        // EMB -> Earth: subtract Moon/(EARTH_MOON_MRAT + 1)
        $factor = Constants::EARTH_MOON_MRAT + 1.0;
        for ($i = 0; $i < 3; $i++) {
            $xemb[$i] -= $xyz[$i] / $factor;
        }
    }

    /**
     * Mean obliquity of the ecliptic in radians
     * Simple approximation for embofs_mosh
     *
     * @param float $tjd Julian day
     * @return float Mean obliquity in radians
     */
    private static function meanObliquityRad(float $tjd): float
    {
        // Lieske 1976 formula (good enough for Moon short series)
        $T = ($tjd - self::J2000) / 36525.0;
        $eps = 84381.448 - 46.8150 * $T - 0.00059 * $T * $T + 0.001813 * $T * $T * $T;
        return $eps * self::STR;  // arcsec to radians
    }

    /**
     * Normalize angle to 0-360 degrees
     *
     * @param float $x Angle in degrees
     * @return float Normalized angle [0, 360)
     */
    private static function degnorm(float $x): float
    {
        $y = fmod($x, 360.0);
        if ($y < 0.0) {
            $y += 360.0;
        }
        return $y;
    }

    /**
     * Main Moshier ephemeris entry point
     *
     * Computes heliocentric cartesian equatorial J2000 coordinates
     * for a planet, including Earth handling and speeds.
     *
     * FULL port of swi_moshplan() from swemplan.c:276-384
     *
     * @param float $tjd Julian day (TT)
     * @param int $ipli Internal planet index (SEI_EARTH=0, SEI_MERCURY=2, etc.)
     * @param bool $doSave Whether to save results in SwedState->pldat[]
     * @param array<float>|null &$xpret Output: planet coordinates [x,y,z,vx,vy,vz] or null
     * @param array<float>|null &$xeret Output: Earth coordinates [x,y,z,vx,vy,vz] or null
     * @param string|null &$serr Error message
     * @return int 0=OK, -1=ERR
     */
    public static function moshplan(
        float $tjd,
        int $ipli,
        bool $doSave,
        ?array &$xpret,
        ?array &$xeret,
        ?string &$serr
    ): int {
        $swed = SwedState::getInstance();
        $pdp = &$swed->pldat[$ipli];
        $pedp = &$swed->pldat[MoshierConstants::SEI_EARTH];

        // pnoint2msh mapping
        $pnoint2msh = [2, 2, 0, 1, 3, 4, 5, 6, 7, 8]; // C: pnoint2msh[]
        $iplm = $pnoint2msh[$ipli] ?? -1;
        if ($iplm < 0 || $ipli > 9) {
            $serr = "Invalid planet index $ipli for Moshier ephemeris";
            return -1;
        }

        // Check date range (with margin for speed at edge)
        if ($tjd < MoshierConstants::MOSHPLEPH_START - 0.3 ||
            $tjd > MoshierConstants::MOSHPLEPH_END + 0.3) {
            $serr = sprintf(
                "jd %f outside Moshier planet range %.2f .. %.2f",
                $tjd, MoshierConstants::MOSHPLEPH_START, MoshierConstants::MOSHPLEPH_END
            );
            return -1;
        }

        // Use oec2000 for ecliptic-equatorial transformation
        $seps2000 = $swed->oec2000->seps;
        $ceps2000 = $swed->oec2000->ceps;

        // Local or saved coordinates
        $xxe = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xxp = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];

        // Determine if we need Earth
        $doEarth = ($doSave || $ipli === MoshierConstants::SEI_EARTH || $xeret !== null);

        // ======= EARTH COMPUTATION =======
        if ($doEarth) {
            // Check if already computed for this time
            if ($tjd === $pedp->teval && $pedp->iephe === Constants::SEFLG_MOSEPH) {
                // Use cached values
                $xxe = $pedp->x;
            } else {
                // EMB heliocentric ecliptic J2000 polar
                $xe = [0.0, 0.0, 0.0];
                self::moshplan2($tjd, $pnoint2msh[MoshierConstants::SEI_EMB], $xe);

                // Convert to cartesian
                Coordinates::polCart($xe, $xe);

                // Convert ecliptic to equatorial J2000
                Coordinates::coortrf2($xe, $xe, -$seps2000, $ceps2000);

                // EMB -> Earth correction
                self::embofsMosh($tjd, $xe);

                // Copy position to xxe
                $xxe[0] = $xe[0];
                $xxe[1] = $xe[1];
                $xxe[2] = $xe[2];

                // Save if requested
                if ($doSave) {
                    $pedp->teval = $tjd;
                    $pedp->xflgs = -1;  // Will be set properly in app_pos_etc_plan
                    $pedp->iephe = Constants::SEFLG_MOSEPH;
                }

                // Compute speed using finite difference
                $x2 = [0.0, 0.0, 0.0];
                self::moshplan2($tjd - self::PLAN_SPEED_INTV, $pnoint2msh[MoshierConstants::SEI_EMB], $x2);
                Coordinates::polCart($x2, $x2);
                Coordinates::coortrf2($x2, $x2, -$seps2000, $ceps2000);
                self::embofsMosh($tjd - self::PLAN_SPEED_INTV, $x2);

                for ($i = 0; $i < 3; $i++) {
                    $xxe[$i + 3] = ($xxe[$i] - $x2[$i]) / self::PLAN_SPEED_INTV;
                }

                // Store in pedp->x
                if ($doSave) {
                    for ($i = 0; $i < 6; $i++) {
                        $pedp->x[$i] = $xxe[$i];
                    }
                }
            }

            // Return Earth coordinates if requested
            if ($xeret !== null) {
                for ($i = 0; $i < 6; $i++) {
                    $xeret[$i] = $xxe[$i];
                }
            }
        }

        // ======= IF EARTH IS REQUESTED =======
        if ($ipli === MoshierConstants::SEI_EARTH) {
            if ($xpret !== null) {
                for ($i = 0; $i < 6; $i++) {
                    $xpret[$i] = $xxe[$i];
                }
            }
            return 0;
        }

        // ======= OTHER PLANET =======
        // Check if already computed
        if ($tjd === $pdp->teval && $pdp->iephe === Constants::SEFLG_MOSEPH) {
            $xxp = $pdp->x;
        } else {
            // Compute planet position
            $xp = [0.0, 0.0, 0.0];
            self::moshplan2($tjd, $iplm, $xp);
            Coordinates::polCart($xp, $xp);
            Coordinates::coortrf2($xp, $xp, -$seps2000, $ceps2000);

            $xxp[0] = $xp[0];
            $xxp[1] = $xp[1];
            $xxp[2] = $xp[2];

            if ($doSave) {
                $pdp->teval = $tjd;
                $pdp->xflgs = -1;
                $pdp->iephe = Constants::SEFLG_MOSEPH;
            }

            // Compute speed
            $dt = self::PLAN_SPEED_INTV;
            $x2 = [0.0, 0.0, 0.0];
            self::moshplan2($tjd - $dt, $iplm, $x2);
            Coordinates::polCart($x2, $x2);
            Coordinates::coortrf2($x2, $x2, -$seps2000, $ceps2000);

            for ($i = 0; $i < 3; $i++) {
                $xxp[$i + 3] = ($xxp[$i] - $x2[$i]) / $dt;
            }

            // Store in pdp->x
            if ($doSave) {
                for ($i = 0; $i < 6; $i++) {
                    $pdp->x[$i] = $xxp[$i];
                }
            }
        }

        // Return planet coordinates if requested
        if ($xpret !== null) {
            for ($i = 0; $i < 6; $i++) {
                $xpret[$i] = $xxp[$i];
            }
        }

        return 0;
    }
}
