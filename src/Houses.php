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
     * @param float $armc_rad ARMC в радианах
     * @param float $geolat_rad широта в радианах
     * @param float $eps_rad наклон эклиптики в радианах
     * @return array{0:float,1:float} [asc_lon_rad, mc_lon_rad] в радианах
     */
    public static function ascMcFromArmc(float $armc_rad, float $geolat_rad, float $eps_rad): array
    {
        // MC: ecliptic longitude where local meridian crosses ecliptic
        $tan_mc = \cos($eps_rad) * \tan($armc_rad);
        $mc = \atan($tan_mc);
        if ($mc < 0) { $mc += Math::PI; }
        // adjust quadrant using armc
        if (\cos($armc_rad) < 0) { $mc += Math::PI; }
        $mc = Math::normAngleRad($mc);

        // Ascendant formula (Meeus approximation)
        $sin_eps = \sin($eps_rad); $cos_eps = \cos($eps_rad);
        $sin_phi = \sin($geolat_rad); $cos_phi = \cos($geolat_rad);
        $tan_phi = $sin_phi / ($cos_phi !== 0.0 ? $cos_phi : 1e-15);
        $lambda = \atan2(-\cos($armc_rad), $sin_eps * $tan_phi + $cos_eps * \sin($armc_rad));
        if ($lambda < 0) { $lambda += Math::TWO_PI; }
        return [$lambda, $mc];
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
}
