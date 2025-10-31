<?php

declare(strict_types=1);

namespace Swisseph\Swe\FixedStars;

/**
 * Built-in star data and lookup tables for fixed star calculations.
 *
 * Contains:
 * - Built-in stars for Hindu sidereal ephemerides (Spica, Revati, Pushya, Mula)
 * - Galactic reference points (Galactic Center, Galactic Poles)
 * - Solar mass distribution table for gravitational deflection (meff)
 */
final class StarCatalogData
{
    /**
     * Get built-in star CSV record by name.
     *
     * Built-in stars are used for Hindu sidereal ayanamshas:
     * - SE_SIDM_TRUE_CITRA: Spica (α Vir)
     * - SE_SIDM_TRUE_REVATI: Revati (ζ Psc)
     * - SE_SIDM_TRUE_PUSHYA: Pushya (δ Cnc)
     * - SE_SIDM_TRUE_MULA: Mula (λ Sco)
     * - SE_SIDM_GALCENT_*: Sgr A* (Galactic Center)
     * - SE_SIDM_GALEQU_IAU1958: Galactic Pole IAU 1958
     * - SE_SIDM_GALEQU_TRUE/MULA: Galactic Pole
     *
     * @param string $star Star name (case-insensitive)
     * @param string $sstar Formatted search name
     * @return string|null CSV record or null if not found
     */
    public static function getBuiltinStar(string $star, string $sstar): ?string
    {
        // Ayanamsha SE_SIDM_TRUE_CITRA - Spica
        if (stripos($star, 'spica') === 0) {
            return 'Spica,alVir,ICRS,13,25,11.57937,-11,09,40.7501,-42.35,-30.67,1,13.06,0.97,-10,3672';
        }

        // Ayanamsha SE_SIDM_TRUE_REVATI - Revati (zeta Psc)
        if (strpos($star, ',zePsc') !== false || stripos($star, 'revati') === 0) {
            return 'Revati,zePsc,ICRS,01,13,43.88735,+07,34,31.2745,145,-55.69,15,18.76,5.187,06,174';
        }

        // Ayanamsha SE_SIDM_TRUE_PUSHYA - Pushya (delta Cnc)
        if (strpos($star, ',deCnc') !== false || stripos($star, 'pushya') === 0) {
            return 'Pushya,deCnc,ICRS,08,44,41.09921,+18,09,15.5034,-17.67,-229.26,17.14,24.98,3.94,18,2027';
        }

        // Ayanamsha SE_SIDM_TRUE_MULA - Mula (lambda Sco)
        if (strpos($star, ',laSco') !== false || stripos($star, 'mula') === 0) {
            return 'Mula,laSco,ICRS,17,33,36.52012,-37,06,13.7648,-8.53,-30.8,-3,5.71,1.62,-37,11673';
        }

        // Ayanamsha SE_SIDM_GALCENT_* - Galactic Center (Sgr A*)
        if (strpos($star, ',SgrA*') !== false) {
            return 'Gal. Center,SgrA*,2000,17,45,40.03599,-29,00,28.1699,-2.755718425,-5.547,0.0,0.125,999.99,0,0';
        }

        // Ayanamsha SE_SIDM_GALEQU_IAU1958 - Galactic Pole IAU1958
        if (strpos($star, ',GP1958') !== false) {
            return 'Gal. Pole IAU1958,GP1958,1950,12,49,0.0,27,24,0.0,0.0,0.0,0.0,0.0,0.0,0,0';
        }

        // Ayanamsha SE_SIDM_GALEQU_TRUE / SE_SIDM_GALEQU_MULA - Galactic Pole
        if (strpos($star, ',GPol') !== false) {
            return 'Gal. Pole,GPol,ICRS,12,51,36.7151981,27,06,11.193172,0.0,0.0,0.0,0.0,0.0,0,0';
        }

        return null;
    }

    /**
     * Get solar mass distribution for gravitational deflection calculation.
     *
     * Returns effective mass fraction for a given distance from sun center.
     * Table computed with classic treatment of photon passing gravity field, multiplied by 2.
     *
     * Port of meff() from sweph.c:6021-6036
     *
     * @param float $r Distance from sun center in solar radii (0.0 to 1.0)
     * @return float Effective mass fraction (0.0 to 1.0)
     */
    public static function getMeff(float $r): float
    {
        // Mass distribution lookup table (101 entries, descending by radius)
        static $effArr = [
            ['r' => 1.000, 'm' => 1.000000],
            ['r' => 0.990, 'm' => 0.999979],
            ['r' => 0.980, 'm' => 0.999940],
            ['r' => 0.970, 'm' => 0.999881],
            ['r' => 0.960, 'm' => 0.999811],
            ['r' => 0.950, 'm' => 0.999724],
            ['r' => 0.940, 'm' => 0.999622],
            ['r' => 0.930, 'm' => 0.999497],
            ['r' => 0.920, 'm' => 0.999354],
            ['r' => 0.910, 'm' => 0.999192],
            ['r' => 0.900, 'm' => 0.999000],
            ['r' => 0.890, 'm' => 0.998786],
            ['r' => 0.880, 'm' => 0.998535],
            ['r' => 0.870, 'm' => 0.998242],
            ['r' => 0.860, 'm' => 0.997919],
            ['r' => 0.850, 'm' => 0.997571],
            ['r' => 0.840, 'm' => 0.997198],
            ['r' => 0.830, 'm' => 0.996792],
            ['r' => 0.820, 'm' => 0.996316],
            ['r' => 0.810, 'm' => 0.995791],
            ['r' => 0.800, 'm' => 0.995226],
            ['r' => 0.790, 'm' => 0.994625],
            ['r' => 0.780, 'm' => 0.993991],
            ['r' => 0.770, 'm' => 0.993326],
            ['r' => 0.760, 'm' => 0.992598],
            ['r' => 0.750, 'm' => 0.991770],
            ['r' => 0.740, 'm' => 0.990873],
            ['r' => 0.730, 'm' => 0.989919],
            ['r' => 0.720, 'm' => 0.988912],
            ['r' => 0.710, 'm' => 0.987856],
            ['r' => 0.700, 'm' => 0.986755],
            ['r' => 0.690, 'm' => 0.985610],
            ['r' => 0.680, 'm' => 0.984398],
            ['r' => 0.670, 'm' => 0.982986],
            ['r' => 0.660, 'm' => 0.981437],
            ['r' => 0.650, 'm' => 0.979779],
            ['r' => 0.640, 'm' => 0.978024],
            ['r' => 0.630, 'm' => 0.976182],
            ['r' => 0.620, 'm' => 0.974256],
            ['r' => 0.610, 'm' => 0.972253],
            ['r' => 0.600, 'm' => 0.970174],
            ['r' => 0.590, 'm' => 0.968024],
            ['r' => 0.580, 'm' => 0.965594],
            ['r' => 0.570, 'm' => 0.962797],
            ['r' => 0.560, 'm' => 0.959758],
            ['r' => 0.550, 'm' => 0.956515],
            ['r' => 0.540, 'm' => 0.953088],
            ['r' => 0.530, 'm' => 0.949495],
            ['r' => 0.520, 'm' => 0.945741],
            ['r' => 0.510, 'm' => 0.941838],
            ['r' => 0.500, 'm' => 0.937790],
            ['r' => 0.490, 'm' => 0.933563],
            ['r' => 0.480, 'm' => 0.928668],
            ['r' => 0.470, 'm' => 0.923288],
            ['r' => 0.460, 'm' => 0.917527],
            ['r' => 0.450, 'm' => 0.911432],
            ['r' => 0.440, 'm' => 0.905035],
            ['r' => 0.430, 'm' => 0.898353],
            ['r' => 0.420, 'm' => 0.891022],
            ['r' => 0.410, 'm' => 0.882940],
            ['r' => 0.400, 'm' => 0.874312],
            ['r' => 0.390, 'm' => 0.865206],
            ['r' => 0.380, 'm' => 0.855423],
            ['r' => 0.370, 'm' => 0.844619],
            ['r' => 0.360, 'm' => 0.833074],
            ['r' => 0.350, 'm' => 0.820876],
            ['r' => 0.340, 'm' => 0.808031],
            ['r' => 0.330, 'm' => 0.793962],
            ['r' => 0.320, 'm' => 0.778931],
            ['r' => 0.310, 'm' => 0.763021],
            ['r' => 0.300, 'm' => 0.745815],
            ['r' => 0.290, 'm' => 0.727557],
            ['r' => 0.280, 'm' => 0.708234],
            ['r' => 0.270, 'm' => 0.687583],
            ['r' => 0.260, 'm' => 0.665741],
            ['r' => 0.250, 'm' => 0.642597],
            ['r' => 0.240, 'm' => 0.618252],
            ['r' => 0.230, 'm' => 0.592586],
            ['r' => 0.220, 'm' => 0.565747],
            ['r' => 0.210, 'm' => 0.537697],
            ['r' => 0.200, 'm' => 0.508554],
            ['r' => 0.190, 'm' => 0.478420],
            ['r' => 0.180, 'm' => 0.447322],
            ['r' => 0.170, 'm' => 0.415454],
            ['r' => 0.160, 'm' => 0.382892],
            ['r' => 0.150, 'm' => 0.349955],
            ['r' => 0.140, 'm' => 0.316691],
            ['r' => 0.130, 'm' => 0.283565],
            ['r' => 0.120, 'm' => 0.250431],
            ['r' => 0.110, 'm' => 0.218327],
            ['r' => 0.100, 'm' => 0.186794],
            ['r' => 0.090, 'm' => 0.156287],
            ['r' => 0.080, 'm' => 0.128421],
            ['r' => 0.070, 'm' => 0.102237],
            ['r' => 0.060, 'm' => 0.077393],
            ['r' => 0.050, 'm' => 0.054833],
            ['r' => 0.040, 'm' => 0.036361],
            ['r' => 0.030, 'm' => 0.020953],
            ['r' => 0.020, 'm' => 0.009645],
            ['r' => 0.010, 'm' => 0.002767],
            ['r' => 0.000, 'm' => 0.000000]
        ];

        // Boundary conditions
        if ($r <= 0.0) {
            return 0.0;
        } elseif ($r >= 1.0) {
            return 1.0;
        }

        // Find bracket in lookup table (table is sorted descending by r)
        $i = 0;
        while ($i < count($effArr) && $effArr[$i]['r'] > $r) {
            $i++;
        }

        // Linear interpolation between eff_arr[i-1] and eff_arr[i]
        $f = ($r - $effArr[$i - 1]['r']) / ($effArr[$i]['r'] - $effArr[$i - 1]['r']);
        $m = $effArr[$i - 1]['m'] + $f * ($effArr[$i]['m'] - $effArr[$i - 1]['m']);

        return $m;
    }
}
