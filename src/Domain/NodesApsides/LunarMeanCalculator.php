<?php

declare(strict_types=1);

namespace Swisseph\Domain\NodesApsides;

use Swisseph\Constants;
use Swisseph\Coordinates;
use Swisseph\Math;

/**
 * Calculator for mean lunar nodes and apsides
 * Full port of swi_mean_lunar_elements from swemmoon.c
 */
class LunarMeanCalculator
{
    private const J2000 = 2451545.0;

    /**
     * Calculate mean lunar nodes and apsides with historical corrections
     *
     * @param float $tjdEt Julian day ET/TT
     * @param array &$xnasc Output: ascending node [lon, lat, dist, dlon, dlat, ddist]
     * @param array &$xndsc Output: descending node
     * @param array &$xperi Output: perigee
     * @param array &$xaphe Output: apogee or focal point
     * @param bool $doFocalPoint Return focal point instead of apogee
     * @param bool $withSpeed Calculate speeds via finite differences
     * @param int $iflag Calculation flags (for coordinate transformations)
     */
    public static function calculate(
        float $tjdEt,
        array &$xnasc,
        array &$xndsc,
        array &$xperi,
        array &$xaphe,
        bool $doFocalPoint,
        bool $withSpeed,
        int $iflag = 0
    ): void {
        // Range check
        if ($tjdEt < Constants::MOSHNDEPH_START || $tjdEt > Constants::MOSHNDEPH_END) {
            // Outside range: return zeros
            $xnasc = $xndsc = $xperi = $xaphe = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
            return;
        }

        $t = ($tjdEt - self::J2000) / 36525.0;

        // Compute mean elements of Moon and Sun
        [$SWELP, $NF, $MP, $D, $M] = self::meanElements($t);

        // Node longitude (mean), apply historical correction table
        $node = Math::normAngleDeg(($SWELP - $NF) / 3600.0); // from arcsec to deg
        $dcorNode = self::corrMeanNode($tjdEt); // degrees
        $node = Math::normAngleDeg($node - $dcorNode);

        // Perigee longitude (mean), apply apsis correction, project onto ecliptic
        $peri = Math::normAngleDeg(($SWELP - $MP) / 3600.0 + 180.0); // +PI as in C
        $dcorPeri = self::corrMeanApog($tjdEt);
        $peri = Math::normAngleDeg($peri - $dcorPeri);

        // Project apogee onto ecliptic: rotate by -incl around node line
        $nodeRad = Math::degToRad($node);
        $periRad = Math::degToRad($peri);

        $pol = [$periRad - $nodeRad, 0.0, 1.0];
        $cart = [];
        Coordinates::polCart($pol, $cart);

        // Rotate around x by -MOON_MEAN_INCL
        $se = sin(Math::degToRad(-LunarMeanConstants::MOON_MEAN_INCL));
        $ce = cos(Math::degToRad(-LunarMeanConstants::MOON_MEAN_INCL));
        $cart2 = [];
        Coordinates::coortrf2($cart, $cart2, $se, $ce);

        $pol2 = [];
        Coordinates::cartPol($cart2, $pol2);
        $periProj = Math::normAngleDeg(Math::radToDeg($pol2[0]) + $node);

        // Fill outputs (lat=0, distances from mean ellipse)
        $xnasc[0] = $node;
        $xnasc[1] = 0.0;
        $xnasc[2] = LunarMeanConstants::MOON_MEAN_DIST / LunarMeanConstants::AUNIT;

        $xndsc[0] = Math::normAngleDeg($node + 180.0);
        $xndsc[1] = 0.0;
        $xndsc[2] = LunarMeanConstants::MOON_MEAN_DIST / LunarMeanConstants::AUNIT;

        $xperi[0] = $periProj;
        $xperi[1] = 0.0;
        $xperi[2] = LunarMeanConstants::MOON_MEAN_DIST *
            (1.0 - LunarMeanConstants::MOON_MEAN_ECC) / LunarMeanConstants::AUNIT;

        $xaphe[0] = Math::normAngleDeg($periProj + 180.0);
        $xaphe[1] = 0.0;
        if ($doFocalPoint) {
            $xaphe[2] = LunarMeanConstants::MOON_MEAN_DIST *
                LunarMeanConstants::MOON_MEAN_ECC * 2.0 / LunarMeanConstants::AUNIT;
        } else {
            $xaphe[2] = LunarMeanConstants::MOON_MEAN_DIST *
                (1.0 + LunarMeanConstants::MOON_MEAN_ECC) / LunarMeanConstants::AUNIT;
        }

        // Speeds: compute via finite differences as in swe_moshmoon
        if ($withSpeed) {
            $dt = 0.001; // small step; mean node speed intv in C is 0.001
            $tmp1 = $tmp2 = $tmp3 = $tmp4 = [];

            self::calculate($tjdEt + $dt, $tmp1, $tmp2, $tmp3, $tmp4, $doFocalPoint, false, $iflag);
            $xn1 = $tmp1;
            $xp1 = $tmp3;
            $xa1 = $tmp4;

            self::calculate($tjdEt - $dt, $tmp1, $tmp2, $tmp3, $tmp4, $doFocalPoint, false, $iflag);
            $xn2 = $tmp1;
            $xp2 = $tmp3;
            $xa2 = $tmp4;

            // Speeds (deg/day for angles, AU/day for distances)
            $xnasc[3] = Math::angleDiffDeg($xn1[0], $xn2[0]) / (2 * $dt);
            $xndsc[3] = Math::angleDiffDeg($xn1[0] + 180.0, $xn2[0] + 180.0) / (2 * $dt);
            $xperi[3] = Math::angleDiffDeg($xp1[0], $xp2[0]) / (2 * $dt);
            $xaphe[3] = Math::angleDiffDeg($xa1[0], $xa2[0]) / (2 * $dt);

            $xnasc[4] = 0.0;
            $xndsc[4] = 0.0;
            $xperi[4] = 0.0;
            $xaphe[4] = 0.0;

            $xnasc[5] = ($xn1[2] - $xn2[2]) / (2 * $dt);
            $xndsc[5] = ($xn1[2] - $xn2[2]) / (2 * $dt);
            $xperi[5] = ($xp1[2] - $xp2[2]) / (2 * $dt);
            $xaphe[5] = ($xa1[2] - $xa2[2]) / (2 * $dt);
        } else {
            $xnasc[3] = $xnasc[4] = $xnasc[5] = 0.0;
            $xndsc[3] = $xndsc[4] = $xndsc[5] = 0.0;
            $xperi[3] = $xperi[4] = $xperi[5] = 0.0;
            $xaphe[3] = $xaphe[4] = $xaphe[5] = 0.0;
        }

        // Transform from mean ecliptic to true ecliptic of date
        CoordinateTransformer::transformMeanToTrue(
            $tjdEt,
            $xnasc,
            $xndsc,
            $xperi,
            $xaphe,
            $iflag,
            false  // is_true_nodaps = FALSE for lunar mean nodes too
        );
    }

    /**
     * Modulo helper for lunar mean elements (wrap to 0-1296000 arcsec)
     */
    private static function mods3600(float $x): float
    {
        $lx = $x - 1296000.0 * floor($x / 1296000.0);
        return $lx;
    }

    /**
     * Compute mean elements of Moon
     * Replicates mean_elements() from swemmoon.c (MOSH_MOON_200 disabled branch)
     *
     * @param float $t Centuries from J2000
     * @return array [SWELP, NF, MP, D, M] in arcseconds
     */
    private static function meanElements(float $t): array
    {
        $fracT = fmod($t, 1.0);

        // Mean anomaly of sun = l' (J. Laskar)
        $M = self::mods3600(129600000.0 * $fracT - 3418.961646 * $t + 1287104.76154);
        $poly = 1.62e-20;
        $poly = $poly * $t - 1.0390e-17;
        $poly = $poly * $t - 3.83508e-15;
        $poly = $poly * $t + 4.237343e-13;
        $poly = $poly * $t + 8.8555011e-11;
        $poly = $poly * $t - 4.77258489e-8;
        $poly = $poly * $t - 1.1297037031e-5;
        $poly = $poly * $t + 1.4732069041e-4;
        $poly = $poly * $t - 0.552891801772;
        $M += $poly * ($t * $t);

        // Mean distance from ascending node = F
        $NF = self::mods3600(
            1739232000.0 * $fracT + 295263.0983 * $t - 2.079419901760e-01 * $t + 335779.55755
        );

        // Mean anomaly of moon = l
        $MP = self::mods3600(
            1717200000.0 * $fracT + 715923.4728 * $t - 2.035946368532e-01 * $t + 485868.28096
        );

        // Mean elongation of moon = D
        $D = self::mods3600(
            1601856000.0 * $fracT + 1105601.4603 * $t + 3.962893294503e-01 * $t + 1072260.73512
        );

        // Mean longitude of moon (ecliptic/equinox of date)
        $SWELP = self::mods3600(
            1731456000.0 * $fracT + 1108372.83264 * $t - 6.784914260953e-01 * $t + 785939.95571
        );

        // Higher degree secular terms
        $NF += ((-1.312045233711e+01) * ($t * $t)
            + (-1.138215912580e-03) * ($t * $t * $t)
            + (-9.646018347184e-06) * ($t * $t * $t * $t));

        $MP += ((3.146734198839e+01) * ($t * $t)
            + (4.768357585780e-02) * ($t * $t * $t)
            + (-3.421689790404e-04) * ($t * $t * $t * $t));

        $D += ((-6.847070905410e+00) * ($t * $t)
            + (-5.834100476561e-03) * ($t * $t * $t)
            + (-2.905334122698e-04) * ($t * $t * $t * $t));

        $SWELP += ((-5.663161722088e+00) * ($t * $t)
            + (5.722859298199e-03) * ($t * $t * $t)
            + (-8.466472828815e-05) * ($t * $t * $t * $t));

        return [$SWELP, $NF, $MP, $D, $M];
    }

    /**
     * Apply historical correction to mean node longitude
     * Piecewise linear interpolation per 100-year bins, clamped to DE431 range
     *
     * @param float $jd Julian day
     * @return float Correction in degrees
     */
    private static function corrMeanNode(float $jd): float
    {
        if ($jd < Constants::JPL_DE431_START || $jd > Constants::JPL_DE431_END) {
            return 0.0;
        }

        $dJ = $jd - LunarMeanConstants::CORR_JD_T0GREG;
        $i = (int) floor($dJ / LunarMeanConstants::CORR_DAYSCTY);
        $dfrac = ($dJ - $i * LunarMeanConstants::CORR_DAYSCTY) / LunarMeanConstants::CORR_DAYSCTY;

        $a = LunarMeanConstants::MEAN_NODE_CORR[$i] ?? 0.0;
        $b = LunarMeanConstants::MEAN_NODE_CORR[$i + 1] ?? $a;

        return $a + $dfrac * ($b - $a);
    }

    /**
     * Apply historical correction to mean apogee longitude
     *
     * @param float $jd Julian day
     * @return float Correction in degrees
     */
    private static function corrMeanApog(float $jd): float
    {
        if ($jd < Constants::JPL_DE431_START || $jd > Constants::JPL_DE431_END) {
            return 0.0;
        }

        $dJ = $jd - LunarMeanConstants::CORR_JD_T0GREG;
        $i = (int) floor($dJ / LunarMeanConstants::CORR_DAYSCTY);
        $dfrac = ($dJ - $i * LunarMeanConstants::CORR_DAYSCTY) / LunarMeanConstants::CORR_DAYSCTY;

        $a = LunarMeanConstants::MEAN_APSIS_CORR[$i] ?? 0.0;
        $b = LunarMeanConstants::MEAN_APSIS_CORR[$i + 1] ?? $a;

        return $a + $dfrac * ($b - $a);
    }
}
