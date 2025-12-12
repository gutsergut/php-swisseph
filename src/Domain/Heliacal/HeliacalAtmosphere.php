<?php

declare(strict_types=1);

namespace Swisseph\Domain\Heliacal;

/**
 * Atmospheric physics functions for heliacal calculations
 * Port from swehel.c lines 601-1100
 *
 * Implements atmospheric extinction models based on:
 * - Schaefer, Archaeoastronomy XV, 2000
 * - Various atmospheric physics models
 */
class HeliacalAtmosphere
{
    // Cache for kOZ calculation
    private static float $koz_last = 0.0;
    private static float $alts_last_koz = -999.0;
    private static float $sunra_last_koz = -999.0;

    // Cache for ka calculation
    private static float $ka_last = 0.0;
    private static float $alts_last_ka = -999.0;
    private static float $sunra_last_ka = -999.0;

    // Cache for Deltam calculation
    private static float $deltam_last = 0.0;
    private static float $alts_last_dm = -999.0;
    private static float $alto_last_dm = -999.0;
    private static float $sunra_last_dm = -999.0;

    /**
     * Water vapor extinction coefficient
     * Port from swehel.c:801-808
     *
     * From Schaefer, Archaeoastronomy XV, 2000, page 128
     *
     * @param float $HeightEye Observer height above sea level [meters]
     * @param float $TempS Surface temperature [Celsius]
     * @param float $RH Relative humidity [%]
     * @return float Water vapor extinction coefficient kW
     */
    public static function kW(float $HeightEye, float $TempS, float $RH): float
    {
        $WT = 0.031;
        $WT *= 0.94 * ($RH / 100.0) * exp($TempS / 15)
               * exp(-1 * $HeightEye / HeliacalConstants::SCALE_H_WATER);
        return $WT;
    }

    /**
     * Ozone extinction coefficient
     * Port from swehel.c:815-845
     *
     * From Schaefer, Archaeoastronomy XV, 2000, page 128
     * Includes day/night variation: changes from 100% to 30% during twilight
     *
     * @param float $AltS Sun altitude [degrees]
     * @param float $sunra Sun right ascension [degrees]
     * @param float $Lat Observer latitude [degrees]
     * @return float Ozone extinction coefficient kOZ
     */
    public static function kOZ(float $AltS, float $sunra, float $Lat): float
    {
        // Use cache to avoid recalculation
        if ($AltS == self::$alts_last_koz && $sunra == self::$sunra_last_koz) {
            return self::$koz_last;
        }

        self::$alts_last_koz = $AltS;
        self::$sunra_last_koz = $sunra;

        $OZ = 0.031;
        $LT = $Lat * HeliacalConstants::DEGTORAD;

        // Base ozone extinction
        $kOZret = $OZ * (3.0 + 0.4 * ($LT * cos($sunra * HeliacalConstants::DEGTORAD) - cos(3 * $LT))) / 3.0;

        // Day/night variation (altitude of sun < start astronomical twilight)
        // KO changes from 100% to 30%
        // See extinction section of Vistas in Astronomy page 343
        $altslim = -$AltS - 12;
        if ($altslim < 0) {
            $altslim = 0;
        }

        $CHANGEKO = (100 - 11.6 * HeliacalUtils::min(6, $altslim)) / 100;

        self::$koz_last = $kOZret * $CHANGEKO;
        return self::$koz_last;
    }

    /**
     * Rayleigh scattering coefficient
     * Port from swehel.c:848-861
     *
     * Accounts for wavelength-dependent scattering
     * From Schaefer, Archaeoastronomy XV, 2000, page 128
     *
     * @param float $AltS Sun altitude [degrees]
     * @param float $HeightEye Observer height above sea level [meters]
     * @return float Rayleigh extinction coefficient kR
     */
    public static function kR(float $AltS, float $HeightEye): float
    {
        // Depending on day/night vision (altitude of sun < start astronomical twilight),
        // lambda eye sensitivity changes
        // See extinction section of Vistas in Astronomy page 343
        $val = -$AltS - 12;
        if ($val < 0) {
            $val = 0;
        }
        if ($val > 6) {
            $val = 6;
        }

        $CHANGEK = 1 - 0.166667 * $val;
        $LAMBDA = 0.55 + ($CHANGEK - 1) * 0.04;

        // From Schaefer, Archaeoastronomy XV, 2000, page 128
        return 0.1066 * exp(-1 * $HeightEye / HeliacalConstants::SCALE_H_RAYLEIGH)
               * pow($LAMBDA / 0.55, -4);
    }

    /**
     * Aerosol extinction coefficient
     * Port from swehel.c:881-938
     *
     * Can be calculated from:
     * 1. Meteorological range (VR >= 1 km)
     * 2. Total extinction coefficient (0 < VR < 1)
     * 3. Schaefer formula (VR == 0)
     *
     * @param float $AltS Sun altitude [degrees]
     * @param float $sunra Sun right ascension [degrees]
     * @param float $Lat Observer latitude [degrees]
     * @param float $HeightEye Observer height above sea level [meters]
     * @param float $TempS Surface temperature [Celsius]
     * @param float $RH Relative humidity [%]
     * @param float $VR Meteorological range [km] or total extinction coefficient
     * @param string &$serr Error message output
     * @return float Aerosol extinction coefficient ka
     */
    public static function ka(
        float $AltS,
        float $sunra,
        float $Lat,
        float $HeightEye,
        float $TempS,
        float $RH,
        float $VR,
        ?string &$serr = null): float {
        // Use cache
        if ($AltS == self::$alts_last_ka && $sunra == self::$sunra_last_ka) {
            return self::$ka_last;
        }

        self::$alts_last_ka = $AltS;
        self::$sunra_last_ka = $sunra;

        $SL = HeliacalUtils::sgn($Lat);

        // Day/night lambda sensitivity variation
        $CHANGEKA = 1 - 0.166667 * HeliacalUtils::min(6, HeliacalUtils::max(-$AltS - 12, 0));
        $LAMBDA = 0.55 + ($CHANGEKA - 1) * 0.04;

        if ($VR != 0) {
            if ($VR >= 1) {
                // Visibility range method
                // From http://www1.cs.columbia.edu/CAVE/publications/pdfs/Narasimhan_CVPR03.pdf
                // http://www.icao.int/anb/SG/AMOSSG/meetings/amossg3/wp/SN11Rev.pdf
                // MOR = 2.995/ke
                // Factor 1.3 is relation between "prevailing visibility" and
                // meteorological range (Koshmeider 1920s)
                $BetaVr = 3.912 / $VR;
                $Betaa = $BetaVr - (self::kW($HeightEye, $TempS, $RH) / HeliacalConstants::SCALE_H_WATER
                         + self::kR($AltS, $HeightEye) / HeliacalConstants::SCALE_H_RAYLEIGH)
                         * 1000 * HeliacalConstants::ASTR2TAU;
                $kaact = $Betaa * HeliacalConstants::SCALE_H_AEROSOL / 1000 * HeliacalConstants::TAU2ASTR;

                if ($kaact < 0) {
                    $serr = "The provided Meteorological range is too long, when taking into account other atmospheric parameters";
                }
            } else {
                // Total extinction coefficient method (VR is ktot)
                $kaact = $VR - self::kW($HeightEye, $TempS, $RH)
                         - self::kR($AltS, $HeightEye)
                         - self::kOZ($AltS, $sunra, $Lat);

                if ($kaact < 0) {
                    $serr = "The provided atmospheric coefficient (ktot) is too low, when taking into account other atmospheric parameters";
                }
            }
        } else {
            // Schaefer formula
            // From Schaefer, Archaeoastronomy XV, 2000, page 128

            // Humidity bounds (in C code, only applied outside SIMULATE_VICTORVB)
            $RH_adj = $RH;
            if ($RH_adj <= 0.00000001) {
                $RH_adj = 0.00000001;
            }
            if ($RH_adj >= 99.99999999) {
                $RH_adj = 99.99999999;
            }

            $kaact = 0.1 * exp(-1 * $HeightEye / HeliacalConstants::SCALE_H_AEROSOL)
                     * pow(1 - 0.32 / log($RH_adj / 100.0), 1.33)
                     * (1 + 0.33 * $SL * sin($sunra * HeliacalConstants::DEGTORAD));
            $kaact = $kaact * pow($LAMBDA / 0.55, -1.3);
        }

        self::$ka_last = $kaact;
        return $kaact;
    }

    /**
     * Total extinction coefficient
     * Port from swehel.c:940-962
     *
     * Combines all extinction sources based on ExtType:
     * 0 = ka only, 1 = kW only, 2 = kR only, 3 = kOZ only, 4 = all (ktot)
     *
     * @param float $AltS Sun altitude [degrees]
     * @param float $sunra Sun right ascension [degrees]
     * @param float $Lat Observer latitude [degrees]
     * @param float $HeightEye Observer height above sea level [meters]
     * @param float $TempS Surface temperature [Celsius]
     * @param float $RH Relative humidity [%]
     * @param float $VR Meteorological range [km] or extinction coefficient
     * @param int $ExtType Extinction type (0-4)
     * @param string &$serr Error message output
     * @return float Total extinction coefficient kt
     */
    public static function kt(
        float $AltS,
        float $sunra,
        float $Lat,
        float $HeightEye,
        float $TempS,
        float $RH,
        float $VR,
        int $ExtType,
        ?string &$serr = null): float {
        $kRact = 0;
        $kWact = 0;
        $kOZact = 0;
        $kaact = 0;

        if ($ExtType == 2 || $ExtType == 4) {
            $kRact = self::kR($AltS, $HeightEye);
        }
        if ($ExtType == 1 || $ExtType == 4) {
            $kWact = self::kW($HeightEye, $TempS, $RH);
        }
        if ($ExtType == 3 || $ExtType == 4) {
            $kOZact = self::kOZ($AltS, $sunra, $Lat);
        }
        if ($ExtType == 0 || $ExtType == 4) {
            $kaact = self::ka($AltS, $sunra, $Lat, $HeightEye, $TempS, $RH, $VR, $serr);
        }

        if ($kaact < 0) {
            $kaact = 0;
        }

        return $kWact + $kRact + $kOZact + $kaact;
    }

    /**
     * Calculate airmass
     * Port from swehel.c:964-978
     *
     * Rozenberg (1966) formula with pressure correction
     *
     * @param float $AppAltO Apparent altitude of object [degrees]
     * @param float $Press Atmospheric pressure [mbar]
     * @return float Airmass value
     */
    public static function Airmass(float $AppAltO, float $Press): float
    {
        $zend = (90 - $AppAltO) * HeliacalConstants::DEGTORAD;
        if ($zend > M_PI / 2) {
            $zend = M_PI / 2;
        }

        $airm = 1 / (cos($zend) + 0.025 * exp(-11 * cos($zend)));
        return $Press / 1013 * $airm;
    }

    /**
     * Calculate extinction path length (Xext)
     * Port from swehel.c:980-989
     *
     * @param float $scaleH Scale height [meters]
     * @param float $zend Zenith distance [radians]
     * @param float $Press Atmospheric pressure [mbar]
     * @return float Extinction path length
     */
    public static function Xext(float $scaleH, float $zend, float $Press): float
    {
        return $Press / 1013.0 / (cos($zend) + 0.01 * sqrt($scaleH / 1000.0)
               * exp(-30.0 / sqrt($scaleH / 1000.0) * cos($zend)));
    }

    /**
     * Calculate layer path length (Xlay)
     * Port from swehel.c:991-1001
     *
     * @param float $scaleH Scale height [meters]
     * @param float $zend Zenith distance [radians]
     * @param float $Press Atmospheric pressure [mbar]
     * @return float Layer path length
     */
    public static function Xlay(float $scaleH, float $zend, float $Press): float
    {
        $a = sin($zend) / (1.0 + ($scaleH / HeliacalConstants::RA));
        return $Press / 1013.0 / sqrt(1.0 - $a * $a);
    }

    /**
     * Calculate temperature at eye height from surface temperature
     * Port from swehel.c:1003-1011
     *
     * Uses atmospheric lapse rate
     *
     * @param float $TempS Surface temperature [Celsius]
     * @param float $HeightEye Observer height above sea level [meters]
     * @param float $Lapse Lapse rate [K/m] (default LAPSE_SA = 0.0065)
     * @return float Temperature at eye height [Celsius]
     */
    public static function TempEfromTempS(float $TempS, float $HeightEye, float $Lapse): float
    {
        return $TempS - $Lapse * $HeightEye;
    }

    /**
     * Calculate pressure at eye height from surface pressure
     * Port from swehel.c:1013-1031
     *
     * Uses barometric formula
     *
     * @param float $TempS Surface temperature [Celsius]
     * @param float $Press Surface pressure [mbar]
     * @param float $HeightEye Observer height above sea level [meters]
     * @return float Pressure at eye height [mbar]
     */
    public static function PresEfromPresS(float $TempS, float $Press, float $HeightEye): float
    {
        return $Press * exp(-9.80665 * 0.0289644
               / (HeliacalUtils::kelvin($TempS) + 3.25 * $HeightEye / 1000)
               / 8.31441 * $HeightEye);
    }

    /**
     * Calculate magnitude loss due to atmospheric extinction
     * Port from swehel.c:1033-1073
     *
     * From Schaefer, Archaeoastronomy XV, 2000, page 128
     *
     * @param float $AltO Object altitude [degrees]
     * @param float $AltS Sun altitude [degrees]
     * @param float $sunra Sun right ascension [degrees]
     * @param float $Lat Observer latitude [degrees]
     * @param float $HeightEye Observer height above sea level [meters]
     * @param array $datm Atmospheric parameters [pressure, temperature, humidity, VR]
     * @param int $helflag Heliacal flags
     * @param string &$serr Error message output
     * @return float Magnitude loss Deltam
     */
    public static function Deltam(
        float $AltO,
        float $AltS,
        float $sunra,
        float $Lat,
        float $HeightEye,
        array $datm,
        int $helflag,
        ?string &$serr = null): float {
        // Use cache
        if ($AltS == self::$alts_last_dm && $AltO == self::$alto_last_dm && $sunra == self::$sunra_last_dm) {
            return self::$deltam_last;
        }

        self::$alts_last_dm = $AltS;
        self::$alto_last_dm = $AltO;
        self::$sunra_last_dm = $sunra;

        $PresE = self::PresEfromPresS($datm[1], $datm[0], $HeightEye);
        $TempE = self::TempEfromTempS($datm[1], $HeightEye, HeliacalConstants::LAPSE_SA);
        $AppAltO = self::AppAltfromTopoAlt($AltO, $TempE, $PresE, $helflag);

        $staticAirmass = HeliacalConstants::STATIC_AIRMASS;

        if ($staticAirmass == 0) {
            $zend = (90 - $AppAltO) * HeliacalConstants::DEGTORAD;
            if ($zend > M_PI / 2) {
                $zend = M_PI / 2;
            }

            // From Schaefer, Archaeoastronomy XV, 2000, page 128
            $xR = self::Xext(HeliacalConstants::SCALE_H_RAYLEIGH, $zend, $datm[0]);
            $XW = self::Xext(HeliacalConstants::SCALE_H_WATER, $zend, $datm[0]);
            $Xa = self::Xext(HeliacalConstants::SCALE_H_AEROSOL, $zend, $datm[0]);
            $XOZ = self::Xlay(HeliacalConstants::SCALE_H_OZONE, $zend, $datm[0]);

            $deltam = self::kR($AltS, $HeightEye) * $xR
                      + self::kt($AltS, $sunra, $Lat, $HeightEye, $datm[1], $datm[2], $datm[3], 0, $serr) * $Xa
                      + self::kOZ($AltS, $sunra, $Lat) * $XOZ
                      + self::kW($HeightEye, $datm[1], $datm[2]) * $XW;
        } else {
            $deltam = self::kt($AltS, $sunra, $Lat, $HeightEye, $datm[1], $datm[2], $datm[3], 4, $serr)
                      * self::Airmass($AppAltO, $datm[0]);
        }

        self::$deltam_last = $deltam;
        return $deltam;
    }

    /**
     * Convert topocentric altitude to apparent altitude
     * Port from swehel.c:601-620
     *
     * Applies atmospheric refraction correction
     *
     * @param float $AppAlt Apparent altitude [degrees]
     * @param float $TempE Temperature at eye height [Celsius]
     * @param float $PresE Pressure at eye height [mbar]
     * @return float Topocentric altitude [degrees]
     */
    public static function TopoAltfromAppAlt(float $AppAlt, float $TempE, float $PresE): float
    {
        $R = 0;

        if ($AppAlt >= HeliacalConstants::LOWEST_APP_ALT) {
            if ($AppAlt > 17.904104638432) {
                $R = 0.97 / tan($AppAlt * HeliacalConstants::DEGTORAD);
            } else {
                $R = (34.46 + 4.23 * $AppAlt + 0.004 * $AppAlt * $AppAlt)
                     / (1 + 0.505 * $AppAlt + 0.0845 * $AppAlt * $AppAlt);
            }

            $R = ($PresE - 80) / 930 / (1 + 0.00008 * ($R + 39) * ($TempE - 10)) * $R;
            $retalt = $AppAlt - $R * HeliacalConstants::MIN2DEG;
        } else {
            $retalt = $AppAlt;
        }

        return $retalt;
    }

    /**
     * Convert topocentric altitude to apparent altitude
     * Port from swehel.c:626-656
     *
     * Uses Newton derivatives method (analogous to Swiss Ephemeris)
     * Faster than swe_azalt() with acceptable precision
     *
     * @param float $TopoAlt Topocentric altitude [degrees]
     * @param float $TempE Temperature at eye height [Celsius]
     * @param float $PresE Pressure at eye height [mbar]
     * @param int $helflag Heliacal flags (SE_HELFLAG_HIGH_PRECISION for 5 iterations)
     * @return float Apparent altitude [degrees]
     */
    public static function AppAltfromTopoAlt(float $TopoAlt, float $TempE, float $PresE, int $helflag): float
    {
        // Newton derivatives methodology
        $nloop = 2;
        if ($helflag & HeliacalConstants::SE_HELFLAG_HIGH_PRECISION) {
            $nloop = 5;
        }

        $newAppAlt = $TopoAlt;
        $newTopoAlt = 0.0;
        $oudAppAlt = $newAppAlt;
        $oudTopoAlt = $newTopoAlt;

        for ($i = 0; $i <= $nloop; $i++) {
            $newTopoAlt = $newAppAlt - self::TopoAltfromAppAlt($newAppAlt, $TempE, $PresE);
            $verschil = $newAppAlt - $oudAppAlt;
            $oudAppAlt = $newTopoAlt - $oudTopoAlt - $verschil;

            if (($verschil != 0) && ($oudAppAlt != 0)) {
                $verschil = $newAppAlt - $verschil * ($TopoAlt + $newTopoAlt - $newAppAlt) / $oudAppAlt;
            } else {
                $verschil = $TopoAlt + $newTopoAlt;
            }

            $oudAppAlt = $newAppAlt;
            $oudTopoAlt = $newTopoAlt;
            $newAppAlt = $verschil;
        }

        $retalt = $TopoAlt + $newTopoAlt;
        if ($retalt < HeliacalConstants::LOWEST_APP_ALT) {
            $retalt = $TopoAlt;
        }

        return $retalt;
    }
}
