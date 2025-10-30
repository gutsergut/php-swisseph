<?php

declare(strict_types=1);

namespace Swisseph\Nutation;

use Swisseph\Math;

/**
 * Fundamental Arguments for IAU 2000 Nutation
 *
 * Provides calculations for:
 * - Luni-solar fundamental arguments (Simon et al. 1994)
 * - Planetary mean longitudes (Souchay et al. 1999)
 * - General accumulated precession
 *
 * Used by IAU 2000A/B nutation models
 */
final class FundamentalArguments
{
    /**
     * Calculate luni-solar fundamental arguments (Simon et al. 1994)
     *
     * @param float $jd Julian day (TT)
     * @return array{
     *   M: float,
     *   SM: float,
     *   F: float,
     *   D: float,
     *   OM: float
     * } Fundamental arguments in radians
     */
    public static function calcSimon1994(float $jd): array
    {
        $T = ($jd - 2451545.0) / 36525.0;

        // Mean anomaly of the Moon
        $M = 485868.249036
            + $T * (1717915923.2178
            + $T * (31.8792
            + $T * (0.051635
            + $T * (-0.00024470))));
        $M = Math::normAngleDeg($M / 3600.0) * Math::DEG_TO_RAD;

        // Mean anomaly of the Sun
        $SM = 1287104.79305
            + $T * (129596581.0481
            + $T * (-0.5532
            + $T * (0.000136
            + $T * (-0.00001149))));
        $SM = Math::normAngleDeg($SM / 3600.0) * Math::DEG_TO_RAD;

        // Mean argument of the latitude of the Moon
        $F = 335779.526232
            + $T * (1739527262.8478
            + $T * (-12.7512
            + $T * (-0.001037
            + $T * (0.00000417))));
        $F = Math::normAngleDeg($F / 3600.0) * Math::DEG_TO_RAD;

        // Mean elongation of the Moon from the Sun
        $D = 1072260.70369
            + $T * (1602961601.2090
            + $T * (-6.3706
            + $T * (0.006593
            + $T * (-0.00003169))));
        $D = Math::normAngleDeg($D / 3600.0) * Math::DEG_TO_RAD;

        // Mean longitude of the ascending node of the Moon
        $OM = 450160.398036
            + $T * (-6962890.5431
            + $T * (7.4722
            + $T * (0.007702
            + $T * (-0.00005939))));
        $OM = Math::normAngleDeg($OM / 3600.0) * Math::DEG_TO_RAD;

        return [
            'M' => $M,
            'SM' => $SM,
            'F' => $F,
            'D' => $D,
            'OM' => $OM,
        ];
    }

    /**
     * Calculate Delaunay arguments for planetary nutation (MHB2000)
     *
     * Note: Slightly different formulation than Simon 1994,
     * as used in the MHB2000 planetary nutation routine.
     *
     * @param float $jd Julian day (TT)
     * @return array{
     *   AL: float,
     *   ALSU: float,
     *   AF: float,
     *   AD: float,
     *   AOM: float
     * } Delaunay arguments in radians
     */
    public static function calcDelaunayMHB2000(float $jd): array
    {
        $T = ($jd - 2451545.0) / 36525.0;

        // Mean anomaly of the Moon
        $AL = Math::normAngleRad(2.35555598 + 8328.6914269554 * $T);

        // Mean anomaly of the Sun
        $ALSU = Math::normAngleRad(6.24006013 + 628.301955 * $T);

        // Mean argument of the latitude of the Moon
        $AF = Math::normAngleRad(1.627905234 + 8433.466158131 * $T);

        // Mean elongation of the Moon from the Sun
        $AD = Math::normAngleRad(5.198466741 + 7771.3771468121 * $T);

        // Mean longitude of the ascending node of the Moon
        $AOM = Math::normAngleRad(2.18243920 - 33.757045 * $T);

        return [
            'AL' => $AL,
            'ALSU' => $ALSU,
            'AF' => $AF,
            'AD' => $AD,
            'AOM' => $AOM,
        ];
    }

    /**
     * Calculate planetary mean longitudes (Souchay et al. 1999)
     *
     * @param float $jd Julian day (TT)
     * @return array{
     *   ALME: float,
     *   ALVE: float,
     *   ALEA: float,
     *   ALMA: float,
     *   ALJU: float,
     *   ALSA: float,
     *   ALUR: float,
     *   ALNE: float
     * } Planetary longitudes in radians
     */
    public static function calcSouchay1999(float $jd): array
    {
        $T = ($jd - 2451545.0) / 36525.0;

        // Mean longitude of Mercury
        $ALME = Math::normAngleRad(4.402608842 + 2608.7903141574 * $T);

        // Mean longitude of Venus
        $ALVE = Math::normAngleRad(3.176146697 + 1021.3285546211 * $T);

        // Mean longitude of Earth
        $ALEA = Math::normAngleRad(1.753470314 + 628.3075849991 * $T);

        // Mean longitude of Mars
        $ALMA = Math::normAngleRad(6.203480913 + 334.0612426700 * $T);

        // Mean longitude of Jupiter
        $ALJU = Math::normAngleRad(0.599546497 + 52.9690962641 * $T);

        // Mean longitude of Saturn
        $ALSA = Math::normAngleRad(0.874016757 + 21.3299104960 * $T);

        // Mean longitude of Uranus
        $ALUR = Math::normAngleRad(5.481293871 + 7.4781598567 * $T);

        // Mean longitude of Neptune
        $ALNE = Math::normAngleRad(5.321159000 + 3.8127774000 * $T);

        return [
            'ALME' => $ALME,
            'ALVE' => $ALVE,
            'ALEA' => $ALEA,
            'ALMA' => $ALMA,
            'ALJU' => $ALJU,
            'ALSA' => $ALSA,
            'ALUR' => $ALUR,
            'ALNE' => $ALNE,
        ];
    }

    /**
     * Calculate general accumulated precession in longitude
     *
     * @param float $jd Julian day (TT)
     * @return float Precession in radians
     */
    public static function calcGeneralPrecession(float $jd): float
    {
        $T = ($jd - 2451545.0) / 36525.0;

        // General accumulated precession (APA)
        $APA = (0.02438175 + 0.00000538691 * $T) * $T;

        return $APA;
    }
}
