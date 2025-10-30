<?php

declare(strict_types=1);

namespace Swisseph\Nutation;

use Swisseph\Data\NutationTables2000;
use Swisseph\Math;

/**
 * IAU 2000A Planetary Nutation
 *
 * Calculates planetary nutation component using MHB2000 model.
 *
 * Uses 687-term series with contributions from planetary perturbations.
 * Only applicable to IAU 2000A (not used in 2000B).
 *
 * References:
 * - Mathews, Herring & Buffett (2002)
 * - Souchay et al. (1999) for planetary longitudes
 */
final class Iau2000Planetary
{
    /**
     * Calculate planetary nutation
     *
     * @param float $jd Julian day (TT)
     * @return array{0: float, 1: float} [dpsi, deps] in degrees (not radians!)
     */
    public static function calc(float $jd): array
    {
        $T = ($jd - 2451545.0) / 36525.0;

        // Calculate Delaunay arguments for planetary nutation (MHB2000)
        $delaunay = FundamentalArguments::calcDelaunayMHB2000($jd);
        $AL = $delaunay['AL'];
        $ALSU = $delaunay['ALSU'];
        $AF = $delaunay['AF'];
        $AD = $delaunay['AD'];
        $AOM = $delaunay['AOM'];

        // Calculate planetary mean longitudes (Souchay et al. 1999)
        $planets = FundamentalArguments::calcSouchay1999($jd);
        $ALME = $planets['ALME'];
        $ALVE = $planets['ALVE'];
        $ALEA = $planets['ALEA'];
        $ALMA = $planets['ALMA'];
        $ALJU = $planets['ALJU'];
        $ALSA = $planets['ALSA'];
        $ALUR = $planets['ALUR'];
        $ALNE = $planets['ALNE'];

        // General accumulated precession
        $APA = FundamentalArguments::calcGeneralPrecession($jd);

        $dpsi = 0.0;
        $deps = 0.0;

        // Get planetary nutation tables
        $npl = NutationTables2000::getNpl();
        $icpl = NutationTables2000::getIcpl();

        // Iterate in reverse order (687 terms)
        for ($i = NutationTables2000::NPL - 1; $i >= 0; $i--) {
            // Get argument multipliers (14 values per term)
            $n = $npl[$i];

            // Compute argument: sum of (multiplier * fundamental_argument)
            $darg = $n[0]  * $AL    // L   - Mean anomaly of Moon
                  + $n[1]  * $ALSU  // L'  - Mean anomaly of Sun
                  + $n[2]  * $AF    // F   - Mean argument of latitude of Moon
                  + $n[3]  * $AD    // D   - Mean elongation of Moon from Sun
                  + $n[4]  * $AOM   // Om  - Mean longitude of ascending node of Moon
                  + $n[5]  * $ALME  // LMe - Mean longitude of Mercury
                  + $n[6]  * $ALVE  // LVe - Mean longitude of Venus
                  + $n[7]  * $ALEA  // LEa - Mean longitude of Earth
                  + $n[8]  * $ALMA  // LMa - Mean longitude of Mars
                  + $n[9]  * $ALJU  // LJu - Mean longitude of Jupiter
                  + $n[10] * $ALSA  // LSa - Mean longitude of Saturn
                  + $n[11] * $ALUR  // LUr - Mean longitude of Uranus
                  + $n[12] * $ALNE  // LNe - Mean longitude of Neptune
                  + $n[13] * $APA;  // pA  - General accumulated precession

            $darg = Math::normAngleRad($darg);

            $sinarg = sin($darg);
            $cosarg = cos($darg);

            // Get coefficients (4 values per term)
            // Format: [iS_psi, iC_psi, iS_eps, iC_eps]
            $c = $icpl[$i];

            // Accumulate nutation contributions
            $dpsi += $c[0] * $sinarg + $c[1] * $cosarg;
            $deps += $c[2] * $sinarg + $c[3] * $cosarg;
        }

        // Coefficients are directly in microarcseconds (not 0.1 mas like luni-solar)
        // Convert to degrees: microarcseconds / 3600 / 1000000
        $dpsi /= (3600.0 * 1000000.0);
        $deps /= (3600.0 * 1000000.0);

        return [$dpsi, $deps];
    }

    /**
     * Apply IAU 2006 (P03) precession-nutation corrections
     *
     * These corrections are required when using IAU 2006/2000A precession-nutation model.
     * Based on Capitaine et al. (2005) A&A 412, 366.
     *
     * @param float $jd Julian day (TT)
     * @param array{M: float, SM: float, F: float, D: float, OM: float} $args Fundamental arguments (radians)
     * @return array{0: float, 1: float} [dpsi, deps] corrections in degrees
     */
    public static function calcP03Corrections(float $jd, array $args): array
    {
        $T = ($jd - 2451545.0) / 36525.0;

        $F = $args['F'];
        $D = $args['D'];
        $OM = $args['OM'];

        // IAU 2006 corrections for adoption of P03 precession
        // Values in microarcseconds
        $dpsi = -8.1 * sin($OM)
              - 0.6 * sin(2.0 * $F - 2.0 * $D + 2.0 * $OM);

        $dpsi += $T * (47.8 * sin($OM)
                     + 3.7 * sin(2.0 * $F - 2.0 * $D + 2.0 * $OM)
                     + 0.6 * sin(2.0 * $F + 2.0 * $OM)
                     - 0.6 * sin(2.0 * $OM));

        $deps = $T * (-25.6 * cos($OM)
                    - 1.6 * cos(2.0 * $F - 2.0 * $D + 2.0 * $OM));

        // Convert from microarcseconds to degrees
        $dpsi /= (3600.0 * 1000000.0);
        $deps /= (3600.0 * 1000000.0);

        return [$dpsi, $deps];
    }
}
