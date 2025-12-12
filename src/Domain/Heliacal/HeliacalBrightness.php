<?php

declare(strict_types=1);

namespace Swisseph\Domain\Heliacal;

use Swisseph\Swe;

/**
 * Sky brightness functions for heliacal calculations
 * Port from swehel.c lines 780-1300
 *
 * Implements sky brightness models from Schaefer, Archaeoastronomy XV, 2000
 */
class HeliacalBrightness
{
    /**
     * Calculate angular distance between two points (Haversine formula)
     * Port from swehel.c:780-795
     *
     * From R.W. Sinnott, "Virtues of the Haversine", Sky and Telescope 68(2), 1984, p.159
     * http://www.movable-type.co.uk/scripts/GIS-FAQ-5.1.html
     *
     * @param float $LatA Latitude/Altitude of point A [radians]
     * @param float $LongA Longitude/Azimuth of point A [radians]
     * @param float $LatB Latitude/Altitude of point B [radians]
     * @param float $LongB Longitude/Azimuth of point B [radians]
     * @return float Angular distance [radians]
     */
    public static function DistanceAngle(float $LatA, float $LongA, float $LatB, float $LongB): float
    {
        $dlon = $LongB - $LongA;
        $dlat = $LatB - $LatA;

        // Haversine formula
        $sindlat2 = sin($dlat / 2);
        $sindlon2 = sin($dlon / 2);
        $corde = $sindlat2 * $sindlat2 + cos($LatA) * cos($LatB) * $sindlon2 * $sindlon2;

        if ($corde > 1) {
            $corde = 1;
        }

        return 2 * asin(sqrt($corde));
    }

    /**
     * Calculate Moon phase angle
     * Port from swehel.c:1170-1184
     *
     * Angle between Sun and Moon as seen from object location
     *
     * @param float $AltM Moon altitude [degrees]
     * @param float $AziM Moon azimuth [degrees]
     * @param float $AltS Sun altitude [degrees]
     * @param float $AziS Sun azimuth [degrees]
     * @return float Phase angle [degrees]
     */
    public static function MoonPhase(float $AltM, float $AziM, float $AltS, float $AziS): float
    {
        $AltMi = $AltM * HeliacalConstants::DEGTORAD;
        $AltSi = $AltS * HeliacalConstants::DEGTORAD;
        $AziMi = $AziM * HeliacalConstants::DEGTORAD;
        $AziSi = $AziS * HeliacalConstants::DEGTORAD;
        $MoonAvgPar = 0.95;

        return 180 - acos(cos($AziSi - $AziMi - $MoonAvgPar * HeliacalConstants::DEGTORAD)
                         * cos($AltMi + $MoonAvgPar * HeliacalConstants::DEGTORAD)
                         * cos($AltSi)
                         + sin($AltSi) * sin($AltMi + $MoonAvgPar * HeliacalConstants::DEGTORAD))
                   / HeliacalConstants::DEGTORAD;
    }

    /**
     * Calculate Moon's brightness
     * Port from swehel.c:1157-1168
     *
     * Moon's brightness changes with distance
     * From http://hem.passagen.se/pausch/comp/ppcomp.html#15
     *
     * @param float $dist Distance to Moon [km]
     * @param float $phasemoon Phase angle [degrees]
     * @return float Moon magnitude
     */
    public static function MoonsBrightness(float $dist, float $phasemoon): float
    {
        $log10 = 2.302585092994; // ln(10)

        return -21.62
               + 5 * log($dist / (HeliacalConstants::RA / 1000)) / $log10
               + 0.026 * abs($phasemoon)
               + 0.000000004 * pow($phasemoon, 4);
    }

    /**
     * Calculate night sky brightness
     * Port from swehel.c:1073-1099
     *
     * From Schaefer, Archaeoastronomy XV, 2000, page 128-129
     * Includes sunspot cycle variation (11.1 year period)
     *
     * @param float $AltO Object altitude [degrees]
     * @param float $JDNDayUT Julian Day Number UT
     * @param float $AltS Sun altitude [degrees]
     * @param float $sunra Sun right ascension [degrees]
     * @param float $Lat Observer latitude [degrees]
     * @param float $HeightEye Observer height above sea level [meters]
     * @param array $datm Atmospheric parameters [pressure, temperature, humidity, VR]
     * @param int $helflag Heliacal flags
     * @param string &$serr Error message output
     * @return float Night sky brightness [nanoLamberts]
     */
    public static function Bn(
        float $AltO,
        float $JDNDayUT,
        float $AltS,
        float $sunra,
        float $Lat,
        float $HeightEye,
        array $datm,
        int $helflag,
        ?string &$serr = null): float {
        $PresE = HeliacalAtmosphere::PresEfromPresS($datm[1], $datm[0], $HeightEye);
        $TempE = HeliacalAtmosphere::TempEfromTempS($datm[1], $HeightEye, HeliacalConstants::LAPSE_SA);
        $AppAltO = HeliacalAtmosphere::AppAltfromTopoAlt($AltO, $TempE, $PresE, $helflag);

        // Below altitude of 10 degrees, the Bn stays the same (Vistas in Astronomy, page 343)
        if ($AppAltO < 10) {
            $AppAltO = 10;
        }

        $zend = (90 - $AppAltO) * HeliacalConstants::DEGTORAD;

        // Extract date from JD for sunspot cycle
        $date = Swe::swe_revjul($JDNDayUT, Swe::SE_GREG_CAL);
        $YearB = $date['year'];
        $MonthB = $date['month'];
        $DayB = floor($date['day']);

        // Sunspot cycle adjustment (11.1 year period, reference epoch 1990.33)
        $B0 = 0.0000000000001;
        $Bna = $B0 * (1 + 0.3 * cos(6.283 * ($YearB + (($DayB - 1) / 30.4 + $MonthB - 1) / 12 - 1990.33) / 11.1));

        $kX = HeliacalAtmosphere::Deltam($AltO, $AltS, $sunra, $Lat, $HeightEye, $datm, $helflag, $serr);

        // From Schaefer, Archaeoastronomy XV, 2000, page 129
        $Bnb = $Bna * (0.4 + 0.6 / sqrt(1 - 0.96 * pow(sin($zend), 2))) * pow(10, -0.4 * $kX);

        return HeliacalUtils::max($Bnb, 0) * HeliacalConstants::ERG2NL;
    }

    /**
     * Calculate Moon contribution to sky brightness
     * Port from swehel.c:1186-1216
     *
     * From Schaefer, Archaeoastronomy XV, 2000, page 129
     *
     * @param float $AltO Object altitude [degrees]
     * @param float $AziO Object azimuth [degrees]
     * @param float $AltM Moon altitude [degrees]
     * @param float $AziM Moon azimuth [degrees]
     * @param float $AltS Sun altitude [degrees]
     * @param float $AziS Sun azimuth [degrees]
     * @param float $sunra Sun right ascension [degrees]
     * @param float $Lat Observer latitude [degrees]
     * @param float $HeightEye Observer height above sea level [meters]
     * @param array $datm Atmospheric parameters
     * @param int $helflag Heliacal flags
     * @param string &$serr Error message output
     * @return float Moon brightness contribution [nanoLamberts]
     */
    public static function Bm(
        float $AltO,
        float $AziO,
        float $AltM,
        float $AziM,
        float $AltS,
        float $AziS,
        float $sunra,
        float $Lat,
        float $HeightEye,
        array $datm,
        int $helflag,
        ?string &$serr = null): float {
        $M0 = -11.05;
        $Bm = 0;

        // Check if object IS the moon
        $object_is_moon = false;
        if ($AltO == $AltM && $AziO == $AziM) {
            $object_is_moon = true;
        }

        // Moon only adds light when (partly) above horizon and object is not the moon itself
        if ($AltM > -0.26 && !$object_is_moon) {
            // Angular distance from object to moon
            $RM = self::DistanceAngle($AltO * HeliacalConstants::DEGTORAD, $AziO * HeliacalConstants::DEGTORAD,
                                     $AltM * HeliacalConstants::DEGTORAD, $AziM * HeliacalConstants::DEGTORAD)
                  / HeliacalConstants::DEGTORAD;

            // Don't allow objects behind the Moon
            $lunar_radius = 0.25 * HeliacalConstants::DEGTORAD / HeliacalConstants::DEGTORAD; // 0.25 degrees
            if ($RM <= $lunar_radius) {
                $RM = $lunar_radius;
            }

            $kXM = HeliacalAtmosphere::Deltam($AltM, $AltS, $sunra, $Lat, $HeightEye, $datm, $helflag, $serr);
            $kX = HeliacalAtmosphere::Deltam($AltO, $AltS, $sunra, $Lat, $HeightEye, $datm, $helflag, $serr);
            $C3 = pow(10, -0.4 * $kXM);
            $FM = (62000000.0) / $RM / $RM
                  + pow(10, 6.15 - $RM / 40)
                  + pow(10, 5.36) * (1.06 + pow(cos($RM * HeliacalConstants::DEGTORAD), 2));
            $Bm = $FM * $C3 + 440000 * (1 - $C3);

            $phasemoon = self::MoonPhase($AltM, $AziM, $AltS, $AziS);
            $MM = self::MoonsBrightness(HeliacalConstants::MOON_DISTANCE, $phasemoon);
            $Bm = $Bm * pow(10, -0.4 * ($MM - $M0 + 43.27));
            $Bm = $Bm * (1 - pow(10, -0.4 * $kX));
        }

        return HeliacalUtils::max($Bm, 0) * HeliacalConstants::ERG2NL;
    }

    /**
     * Calculate twilight sky brightness
     * Port from swehel.c:1218-1244
     *
     * From Schaefer, Archaeoastronomy XV, 2000, page 129
     *
     * @param float $AltO Object altitude [degrees]
     * @param float $AziO Object azimuth [degrees]
     * @param float $AltS Sun altitude [degrees]
     * @param float $AziS Sun azimuth [degrees]
     * @param float $sunra Sun right ascension [degrees]
     * @param float $Lat Observer latitude [degrees]
     * @param float $HeightEye Observer height above sea level [meters]
     * @param array $datm Atmospheric parameters
     * @param int $helflag Heliacal flags
     * @param string &$serr Error message output
     * @return float Twilight brightness [nanoLamberts]
     */
    public static function Btwi(
        float $AltO,
        float $AziO,
        float $AltS,
        float $AziS,
        float $sunra,
        float $Lat,
        float $HeightEye,
        array $datm,
        int $helflag,
        ?string &$serr = null): float {
        $M0 = -11.05;
        $MS = -26.74;

        $PresE = HeliacalAtmosphere::PresEfromPresS($datm[1], $datm[0], $HeightEye);
        $TempE = HeliacalAtmosphere::TempEfromTempS($datm[1], $HeightEye, HeliacalConstants::LAPSE_SA);
        $AppAltO = HeliacalAtmosphere::AppAltfromTopoAlt($AltO, $TempE, $PresE, $helflag);
        $ZendO = 90 - $AppAltO;
        $RS = self::DistanceAngle($AltO * HeliacalConstants::DEGTORAD, $AziO * HeliacalConstants::DEGTORAD,
                                 $AltS * HeliacalConstants::DEGTORAD, $AziS * HeliacalConstants::DEGTORAD)
              / HeliacalConstants::DEGTORAD;
        $kX = HeliacalAtmosphere::Deltam($AltO, $AltS, $sunra, $Lat, $HeightEye, $datm, $helflag, $serr);
        $k = HeliacalAtmosphere::kt($AltS, $sunra, $Lat, $HeightEye, $datm[1], $datm[2], $datm[3], 4, $serr);

        // From Schaefer, Archaeoastronomy XV, 2000, page 129
        $Btwi = pow(10, -0.4 * ($MS - $M0 + 32.5 - $AltS - ($ZendO / (360 * $k))));
        $Btwi = $Btwi * (100 / $RS) * (1 - pow(10, -0.4 * $kX));

        return HeliacalUtils::max($Btwi, 0) * HeliacalConstants::ERG2NL;
    }

    /**
     * Calculate daylight sky brightness
     * Port from swehel.c:1246-1266
     *
     * From Schaefer, Archaeoastronomy XV, 2000, page 129
     *
     * @param float $AltO Object altitude [degrees]
     * @param float $AziO Object azimuth [degrees]
     * @param float $AltS Sun altitude [degrees]
     * @param float $AziS Sun azimuth [degrees]
     * @param float $sunra Sun right ascension [degrees]
     * @param float $Lat Observer latitude [degrees]
     * @param float $HeightEye Observer height above sea level [meters]
     * @param array $datm Atmospheric parameters
     * @param int $helflag Heliacal flags
     * @param string &$serr Error message output
     * @return float Daylight brightness [nanoLamberts]
     */
    public static function Bday(
        float $AltO,
        float $AziO,
        float $AltS,
        float $AziS,
        float $sunra,
        float $Lat,
        float $HeightEye,
        array $datm,
        int $helflag,
        ?string &$serr = null): float {
        $M0 = -11.05;
        $MS = -26.74;

        $RS = self::DistanceAngle($AltO * HeliacalConstants::DEGTORAD, $AziO * HeliacalConstants::DEGTORAD,
                                 $AltS * HeliacalConstants::DEGTORAD, $AziS * HeliacalConstants::DEGTORAD)
              / HeliacalConstants::DEGTORAD;
        $kXS = HeliacalAtmosphere::Deltam($AltS, $AltS, $sunra, $Lat, $HeightEye, $datm, $helflag, $serr);
        $kX = HeliacalAtmosphere::Deltam($AltO, $AltS, $sunra, $Lat, $HeightEye, $datm, $helflag, $serr);

        // From Schaefer, Archaeoastronomy XV, 2000, page 129
        $C4 = pow(10, -0.4 * $kXS);
        $FS = (62000000.0) / $RS / $RS
              + pow(10, (6.15 - $RS / 40))
              + pow(10, 5.36) * (1.06 + pow(cos($RS * HeliacalConstants::DEGTORAD), 2));
        $Bday = $FS * $C4 + 440000.0 * (1 - $C4);
        $Bday = $Bday * pow(10, (-0.4 * ($MS - $M0 + 43.27)));
        $Bday = $Bday * (1 - pow(10, -0.4 * $kX));

        return HeliacalUtils::max($Bday, 0) * HeliacalConstants::ERG2NL;
    }

    /**
     * City light pollution brightness
     * Port from swehel.c:1268-1277
     *
     * Simple pass-through function for city light pollution value
     *
     * @param float $Value City brightness value [nanoLamberts]
     * @param float $Press Atmospheric pressure [mbar] (unused, for API compatibility)
     * @return float City brightness [nanoLamberts]
     */
    public static function Bcity(float $Value, float $Press): float
    {
        // Press parameter unused in C code, kept for API compatibility
        return HeliacalUtils::max($Value, 0);
    }

    /**
     * Calculate total sky brightness
     * Port from swehel.c:1279-1318
     *
     * Combines all brightness sources based on Sun altitude:
     * - Night (Bn + Bcity) when Sun < 0°
     * - Twilight (Btwi) when Sun < -3°
     * - Day (Bday) when Sun > 4°
     * - Transition (min of day/twilight) when -3° < Sun < 4°
     * - Moon (Bm) added when Bsky < 2e8 nL
     *
     * @param float $AltO Object altitude [degrees]
     * @param float $AziO Object azimuth [degrees]
     * @param float $AltM Moon altitude [degrees]
     * @param float $AziM Moon azimuth [degrees]
     * @param float $JDNDaysUT Julian Day Number UT
     * @param float $AltS Sun altitude [degrees]
     * @param float $AziS Sun azimuth [degrees]
     * @param float $sunra Sun right ascension [degrees]
     * @param float $Lat Observer latitude [degrees]
     * @param float $HeightEye Observer height above sea level [meters]
     * @param array $datm Atmospheric parameters
     * @param int $helflag Heliacal flags
     * @param string &$serr Error message output
     * @return float Total sky brightness [nanoLamberts]
     */
    public static function Bsky(
        float $AltO,
        float $AziO,
        float $AltM,
        float $AziM,
        float $JDNDaysUT,
        float $AltS,
        float $AziS,
        float $sunra,
        float $Lat,
        float $HeightEye,
        array $datm,
        int $helflag,
        ?string &$serr = null
    ): float {
        $Bsky = 0;

        if ($AltS < -3) {
            $Bsky += self::Btwi($AltO, $AziO, $AltS, $AziS, $sunra, $Lat, $HeightEye, $datm, $helflag, $serr);
        } else {
            if ($AltS > 4) {
                $Bsky += self::Bday($AltO, $AziO, $AltS, $AziS, $sunra, $Lat, $HeightEye, $datm, $helflag, $serr);
            } else {
                // Transition zone: use minimum of day and twilight
                $Bsky += HeliacalUtils::min(
                    self::Bday($AltO, $AziO, $AltS, $AziS, $sunra, $Lat, $HeightEye, $datm, $helflag, $serr),
                    self::Btwi($AltO, $AziO, $AltS, $AziS, $sunra, $Lat, $HeightEye, $datm, $helflag, $serr)
                );
            }
        }

        // If max. Bm [1E7] <5% of Bsky don't add Bm
        if ($Bsky < 200000000.0) {
            $Bsky += self::Bm($AltO, $AziO, $AltM, $AziM, $AltS, $AziS, $sunra, $Lat, $HeightEye, $datm, $helflag, $serr);
        }

        // Add city light pollution at night
        if ($AltS <= 0) {
            $Bsky += self::Bcity(0, $datm[0]);
        }

        // If max. Bn [250] <5% of Bsky don't add Bn
        if ($Bsky < 5000) {
            $Bsky = $Bsky + self::Bn($AltO, $JDNDaysUT, $AltS, $sunra, $Lat, $HeightEye, $datm, $helflag, $serr);
        }

        return $Bsky;
    }
}
