<?php

namespace Swisseph;

/**
 * Minimal houses implementation: Equal houses ('E') + Asc/MC via ARMC / obliquity.
 * This is an approximation to bootstrap API; accuracy will be refined later.
 */
/**
 * Общие помощники для расчёта домов: ARMC, Asc/MC, позиционирование по куспам.
 * Содержит только универсальные утилиты, специфические системы вынесены в Strategies.
 */
final class Houses
{
    /**
    * Compute ARMC (in radians) from GMST (hours) and geographic longitude (degrees).
    * @param float $jd_ut JD(UT)
    * @param float $geolon_deg географическая долгота (восток +) в градусах
    * @return float ARMC в радианах [0,2π)
     */
    public static function armcFromSidereal(float $jd_ut, float $geolon_deg): float
    {
        $gmst_h = Sidereal::gmstHoursFromJdUt($jd_ut);
        // ARMC = (GMST * 15 + geolon) in degrees
        $armc_deg = $gmst_h * 15.0 + $geolon_deg;
        return Math::degToRad(Math::normAngleDeg($armc_deg));
    }

    /**
     * Ascendant and Midheaven for given ARMC, latitude, obliquity.
     * Uses Asc1() from Swiss Ephemeris for precise calculation.
     * @param float $armc_rad ARMC в радианах
     * @param float $geolat_rad широта в радианах
     * @param float $eps_rad наклон эклиптики в радианах
     * @return array{0:float,1:float} [asc_lon_rad, mc_lon_rad] в радианах
     */
    public static function ascMcFromArmc(float $armc_rad, float $geolat_rad, float $eps_rad): array
    {
        // Convert to degrees for formulas
        $armc_deg = Math::radToDeg($armc_rad);
        $geolat_deg = Math::radToDeg($geolat_rad);
        $sine = \sin($eps_rad);
        $cose = \cos($eps_rad);

        // Calculate Ascendant using Asc1 (swehouse.c:973)
        $asc_deg = self::asc1($armc_deg + 90.0, $geolat_deg, $sine, $cose);

        // Calculate MC using armc_to_mc formula (swehouse.c:860-888)
        $mc_deg = self::armcToMc($armc_deg, $eps_rad);

        return [Math::degToRad($asc_deg), Math::degToRad($mc_deg)];
    }

    // equalCusps/equalHousePosition перенесены в стратегию Equal

    /**
     * Generic house position based on ecliptic cusps (radians).
     * Assumes cusp[1..12] are ordered along ecliptic direction (increasing from Asc with wrapping).
     * Returns (0,12], integer value n.0 exactly on cusp n.
     * @param float $asc_rad Asc в радианах
     * @param array $cusps_rad куспы [1..12] в радианах
     * @param float $obj_lon_rad долгота объекта на эклиптике в радианах
     * @return float позиция дома (1..12) с дробной частью внутри дома
     */
    public static function positionFromCusps(float $asc_rad, array $cusps_rad, float $obj_lon_rad): float
    {
        // Build increasing sequence starting from cusp 1 (Asc)
        $edges = [];
        for ($i = 1; $i <= 12; $i++) {
            $edges[$i] = Math::normAngleRad($cusps_rad[$i]);
        }
        // Ensure monotonic by unwrapping around Asc
        $seq = [];
        $base = $edges[1];
        $prev = $base;
        $seq[1] = $base;
        for ($i = 2; $i <= 12; $i++) {
            $x = $edges[$i];
            // unwrap relative to prev
            while ($x < $prev) { $x += Math::TWO_PI; }
            $seq[$i] = $x;
            $prev = $x;
        }
        // Append cusp 13 as cusp 1 + 2π for interval computation
        $seq[13] = $seq[1] + Math::TWO_PI;

        // Unwrap object longitude near Asc reference
        $lon = Math::normAngleRad($obj_lon_rad);
        while ($lon < $seq[1]) { $lon += Math::TWO_PI; }
        while ($lon >= $seq[13]) { $lon -= Math::TWO_PI; }

        // Find interval [seq[k], seq[k+1]) containing lon
        for ($k = 1; $k <= 12; $k++) {
            $a = $seq[$k]; $b = $seq[$k+1];
            if ($lon == $a) {
                return (float)$k; // exactly on cusp k
            }
            if ($lon > $a && $lon < $b) {
                $frac = ($lon - $a) / ($b - $a);
                $pos = $k + $frac;
                if ($pos > 12.0) { $pos -= 12.0; }
                return $pos;
            }
        }
        // If exactly on last cusp
        return 12.0;
    }

    // placidusCusps перенесён в стратегию Placidus

    /**
     * Koch houses scaffold: not yet implemented. Returns zeros as sentinel.
     */
    // kochCusps перенесён в стратегию Koch

    /**
     * Porphyry houses: each quadrant (Asc→MC, MC→Desc, Desc→IC, IC→Asc) is divided into three equal ecliptic arcs.
     * Input: Asc and MC ecliptic longitudes in radians. Returns cusp[1..12] in radians.
     */
    // porphyryCusps перенесён в стратегию Porphyry

    /**
     * Whole Sign houses: cusp 1 is 0° начала знака, где находится Asc; далее по 30°.
     * Возвращает cusp[1..12] в радианах.
     */
    // wholeSignCusps перенесён в стратегию WholeSign

    /**
     * Campanus houses: planes through zenith (local vertical) with azimuth θ = 90°,60°,30°,... etc, intersected with the ecliptic.
     * Implementation builds local horizon basis from latitude and LST (via ARMC), then intersects each house plane with the ecliptic plane.
     * Returns cusp[1..12] in radians.
     */
    // campanusCusps перенесён в стратегию Campanus

    /**
     * Regiomontanus houses: divide the celestial equator into 12 equal 30° hour-angle sectors starting at the local meridian (LST),
     * form great circles that contain the equatorial pole and intersect the equator at α = LST + k*30°, and intersect them with the ecliptic.
     * Returns cusp[1..12] in radians.
     */
    // regiomontanusCusps перенесён в стратегию Regiomontanus

    /**
     * Alcabitius houses: divide the diurnal (Asc->MC) and nocturnal (Desc->IC) semi-arc in hour angle into three equal parts,
     * then project divisions onto the ecliptic along hour circles (constant hour angle).
     * Returns cusp[1..12] in radians.
     */
    // alcabitiusCusps перенесён в стратегию Alcabitius

    // =========================================================================
    // Helper functions for Ascendant calculation (ported from swehouse.c)
    // =========================================================================

    /**
     * Port of Asc1() from swehouse.c:2058-2089
     * Calculate ecliptic longitude where great circle with pole height f
     * intersects ecliptic, given equatorial x1.
     * @param float $x1 equatorial position (degrees)
     * @param float $f pole height / latitude (degrees)
     * @param float $sine sin(obliquity)
     * @param float $cose cos(obliquity)
     * @return float ecliptic longitude (degrees)
     */
    public static function asc1(float $x1, float $f, float $sine, float $cose): float
    {
        $VERY_SMALL = 1e-10; // from swehouse.h:87
        $x1 = Math::normAngleDeg($x1);
        $n = (int) floor($x1 / 90.0) + 1; // quadrant 1..4

        // Near poles
        if (abs(90.0 - $f) < $VERY_SMALL) {
            return 180.0; // north pole
        }
        if (abs(90.0 + $f) < $VERY_SMALL) {
            return 0.0;   // south pole
        }

        // Calculate based on quadrant
        if ($n === 1) {
            $ass = self::asc2($x1, $f, $sine, $cose);
        } elseif ($n === 2) {
            $ass = 180.0 - self::asc2(180.0 - $x1, -$f, $sine, $cose);
        } elseif ($n === 3) {
            $ass = 180.0 + self::asc2($x1 - 180.0, -$f, $sine, $cose);
        } else {
            $ass = 360.0 - self::asc2(360.0 - $x1, $f, $sine, $cose);
        }

        $ass = Math::normAngleDeg($ass);

        // Rounding fixes (swehouse.c:2081-2088)
        foreach ([90.0, 180.0, 270.0, 360.0] as $fix) {
            if (abs($ass - $fix) < $VERY_SMALL) {
                $ass = ($fix == 360.0) ? 0.0 : $fix;
                break;
            }
        }

        return $ass;
    }

    /**
     * Port of Asc2() from swehouse.c:2100-2129
     * Helper for Asc1: calculate for x in range [0,90]
     */
    private static function asc2(float $x, float $f, float $sine, float $cose): float
    {
        $VERY_SMALL = 1e-10;

        // From spherical trigonometry CT5
        $ass = -\tan(Math::degToRad($f)) * $sine + $cose * \cos(Math::degToRad($x));
        if (abs($ass) < $VERY_SMALL) {
            $ass = 0.0;
        }

        $sinx = \sin(Math::degToRad($x));
        if (abs($sinx) < $VERY_SMALL) {
            $sinx = 0.0;
        }

        if ($sinx == 0.0) {
            $ass = ($ass < 0) ? -$VERY_SMALL : $VERY_SMALL;
        } elseif ($ass == 0.0) {
            $ass = ($sinx < 0) ? -90.0 : 90.0;
            return $ass;
        } else {
            $ass = Math::radToDeg(\atan($sinx / $ass));
        }

        if ($ass < 0) {
            $ass = 180.0 + $ass;
        }

        return $ass;
    }

    /**
     * Port of swi_armc_to_mc() from swehouse.c:873-888
     * Calculate Midheaven (MC) from ARMC
     * @param float $armc_deg ARMC in degrees
     * @param float $eps_rad obliquity in radians
     * @return float MC in degrees
     */
    public static function armcToMc(float $armc_deg, float $eps_rad): float
    {
        $VERY_SMALL = 1e-10;
        $armc_deg = Math::normAngleDeg($armc_deg);

        // swehouse.c:876-888
        if (abs($armc_deg - 90.0) > $VERY_SMALL && abs($armc_deg - 270.0) > $VERY_SMALL) {
            // General formula (swehouse.c:877-880)
            $tant = \tan(Math::degToRad($armc_deg));
            $mc_deg = Math::radToDeg(\atan($tant / \cos($eps_rad)));

            // Quadrant adjustment (swehouse.c:880)
            if ($armc_deg > 90.0 && $armc_deg <= 270.0) {
                $mc_deg = Math::normAngleDeg($mc_deg + 180.0);
            }
            return $mc_deg;
        } else {
            // ARMC = 90° or 270° (swehouse.c:882-885)
            if (abs($armc_deg - 90.0) <= $VERY_SMALL) {
                return 90.0;
            } else {
                return 270.0;
            }
        }
    }
}

