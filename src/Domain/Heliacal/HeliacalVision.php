<?php

declare(strict_types=1);

namespace Swisseph\Domain\Heliacal;

/**
 * Vision and optics functions for heliacal calculations
 * Port from swehel.c lines 180-1450
 */
class HeliacalVision
{
    /**
     * Contrast Visibility Angle (CVA)
     * Port from swehel.c:180-197
     *
     * Calculates critical viewing angle based on Schaefer 1993 model
     * Uses different formulas for scotopic (dark-adapted) vs photopic (light-adapted) vision
     *
     * @param float $B Background brightness [nanoLamberts]
     * @param float $SN Snellen ratio (visual acuity, 1.0 = normal 20/20 vision)
     * @param int $helflag Heliacal flags (SE_HELFLAG_VISLIM_SCOTOPIC, SE_HELFLAG_VISLIM_PHOTOPIC)
     * @return float Critical Visibility Angle [degrees]
     */
    public static function CVA(float $B, float $SN, int $helflag): float
    {
        // Schaefer, Astronomy and the limits of vision, Archaeoastronomy, 1993
        $is_scotopic = false;

        // Use 1394 nL (not BNIGHT=1479) to make the function continuous
        if ($B < 1394) {
            $is_scotopic = true;
        }

        // Override based on flags
        if ($helflag & HeliacalConstants::SE_HELFLAG_VISLIM_PHOTOPIC) {
            $is_scotopic = false;
        }
        if ($helflag & HeliacalConstants::SE_HELFLAG_VISLIM_SCOTOPIC) {
            $is_scotopic = true;
        }

        if ($is_scotopic) {
            // Scotopic (dark-adapted) vision formula
            // Convert from arcseconds to degrees (/3600)
            $cva = HeliacalUtils::min(900, 380 / $SN * pow(10, 0.3 * pow($B, -0.29)));
            return $cva / 60.0 / 60.0; // arcseconds to degrees
        } else {
            // Photopic (light-adapted) vision formula
            $cva = (40.0 / $SN) * pow(10, 8.28 * pow($B, -0.29));
            return $cva / 60.0 / 60.0; // arcseconds to degrees
        }
    }

    /**
     * Calculate pupil diameter
     * Port from swehel.c:202-207
     *
     * Age-dependent pupil diameter based on Garstang 2000
     *
     * @param float $Age Observer's age [years]
     * @param float $B Background brightness [nanoLamberts]
     * @return float Pupil diameter [mm]
     */
    public static function PupilDia(float $Age, float $B): float
    {
        // Age dependency from Garstang [2000]
        $dia = (0.534 - 0.00211 * $Age
                - (0.236 - 0.00127 * $Age) * HeliacalUtils::tanh(0.4 * log($B) / log(10) - 2.2))
                * 10;
        return $dia;
    }

    /**
     * Calculate optical instrument correction factor
     * Port from swehel.c:224-302
     *
     * Calculates correction factors for telescopes/binoculars
     * Uses different formulas for scotopic vs photopic vision
     *
     * @param float $Bback Background brightness [nanoLamberts]
     * @param float $kX Atmospheric extinction coefficient
     * @param array $dobs Observer parameters [Age, SN, Binocular, OpticMag, OpticDia, OpticTrans]
     * @param float $JDNDaysUT Julian Day Number (currently unused, for API compatibility)
     * @param string $ObjectName Object name (currently unused, for future moon calculations)
     * @param int $TypeFactor 0=intensity factor, 1=background factor
     * @param int $helflag Heliacal flags
     * @return float Optical correction factor
     */
    public static function OpticFactor(
        float $Bback,
        float $kX,
        array $dobs,
        float $JDNDaysUT,
        string $ObjectName,
        int $TypeFactor,
        int $helflag
    ): float {
        // Extract observer parameters
        $Age = $dobs[0];
        $SN = $dobs[1];
        $Binocular = $dobs[2];
        $OpticMag = $dobs[3];
        $OpticDia = $dobs[4];
        $OpticTrans = $dobs[5];

        // Avoid division by zero
        $SNi = $SN;
        if ($SNi <= 0.00000001) {
            $SNi = 0.00000001;
        }

        // Standard pupil diameter (23 years old reference from Garstang)
        $Pst = self::PupilDia(23, $Bback);

        // If using naked eye (OpticMag=1)
        if ($OpticMag == 1) {
            $OpticTrans = 1;
            $OpticDia = $Pst;
        }

        // Color indices from Schaefer
        $CIb = 0.7; // Color of background (from Ben Sugerman)
        $CIi = 0.5; // Color index for white (from Ben Sugerman)

        // Object size (for moon, would need to be calculated based on JDNDaysUT)
        $ObjectSize = 0;

        // Binocular factor
        $Fb = 1;
        if ($Binocular == 0) {
            $Fb = 1.41;
        }

        // Determine scotopic vs photopic vision
        $is_scotopic = false;
        // Use 1645 nL (not BNIGHT) to make the function continuous
        if ($Bback < 1645) {
            $is_scotopic = true;
        }
        if ($helflag & HeliacalConstants::SE_HELFLAG_VISLIM_PHOTOPIC) {
            $is_scotopic = false;
        }
        if ($helflag & HeliacalConstants::SE_HELFLAG_VISLIM_SCOTOPIC) {
            $is_scotopic = true;
        }

        if ($is_scotopic) {
            // Scotopic vision factors
            $Fe = pow(10, 0.48 * $kX);
            $Fsc = HeliacalUtils::min(1,
                (1 - pow($Pst / 124.4, 4)) / (1 - pow(($OpticDia / $OpticMag / 124.4), 4))
            );
            $Fci = pow(10, -0.4 * (1 - $CIi / 2.0));
            $Fcb = pow(10, -0.4 * (1 - $CIb / 2.0));
        } else {
            // Photopic vision factors
            $Fe = pow(10, 0.4 * $kX);
            $Fsc = HeliacalUtils::min(1,
                pow(($OpticDia / $OpticMag / $Pst), 2)
                * (1 - exp(-pow(($Pst / 6.2), 2)))
                / (1 - exp(-pow(($OpticDia / $OpticMag / 6.2), 2)))
            );
            $Fci = 1;
            $Fcb = 1;
        }

        // Common factors
        $Ft = 1 / $OpticTrans;
        $Fp = HeliacalUtils::max(1, pow(($Pst / ($OpticMag * self::PupilDia($Age, $Bback))), 2));
        $Fa = pow(($Pst / $OpticDia), 2);
        $Fr = (1 + 0.03 * pow(($OpticMag * $ObjectSize / self::CVA($Bback, $SNi, $helflag)), 2)) / pow($SNi, 2);
        $Fm = pow($OpticMag, 2);

        // Return appropriate factor based on type
        if ($TypeFactor == 0) {
            // Intensity factor
            return $Fb * $Fe * $Ft * $Fp * $Fa * $Fr * $Fsc * $Fci;
        } else {
            // Background factor
            return $Fb * $Ft * $Fp * $Fa * $Fm * $Fsc * $Fcb;
        }
    }

    /**
     * Calculate visual limiting magnitude
     * Port from swehel.c:1382-1444
     *
     * Calculates the faintest magnitude visible under given atmospheric conditions
     * Based on Schaefer 1993 model
     *
     * @param array $dobs Observer parameters [Age, SN, Binocular, OpticMag, OpticDia, OpticTrans]
     * @param float $AltO Object altitude [degrees]
     * @param float $AziO Object azimuth [degrees]
     * @param float $AltM Moon altitude [degrees]
     * @param float $AziM Moon azimuth [degrees]
     * @param float $JDNDaysUT Julian Day Number
     * @param float $AltS Sun altitude [degrees]
     * @param float $AziS Sun azimuth [degrees]
     * @param float $sunra Sun right ascension [degrees]
     * @param float $Lat Observer latitude [degrees]
     * @param float $HeightEye Observer height above sea level [meters]
     * @param array $datm Atmospheric parameters [pressure, temperature, humidity, VR]
     * @param int $helflag Heliacal flags
     * @param int|null &$scotopic_flag Output: scotopic vision flag (1=scotopic, 0=photopic, 2=transitional)
     * @param string &$serr Error message output
     * @return float Visual limiting magnitude
     */
    public static function VisLimMagn(
        array $dobs,
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
        ?int &$scotopic_flag,
        string &$serr
    ): float {
        $log10 = 2.302585092994; // ln(10)

        // Calculate sky brightness and atmospheric extinction
        $Bsk = HeliacalBrightness::Bsky($AltO, $AziO, $AltM, $AziM, $JDNDaysUT, $AltS, $AziS,
                                        $sunra, $Lat, $HeightEye, $datm, $helflag, $serr);
        $kX = HeliacalAtmosphere::Deltam($AltO, $AltS, $sunra, $Lat, $HeightEye, $datm, $helflag, $serr);
        $CorrFactor1 = self::OpticFactor($Bsk, $kX, $dobs, $JDNDaysUT, "", 1, $helflag);
        $CorrFactor2 = self::OpticFactor($Bsk, $kX, $dobs, $JDNDaysUT, "", 0, $helflag);

        // Determine scotopic vs photopic vision
        $is_scotopic = false;
        // Use 1645 nL (not BNIGHT) to make the function continuous
        if ($Bsk < 1645) {
            $is_scotopic = true;
        }
        if ($helflag & HeliacalConstants::SE_HELFLAG_VISLIM_PHOTOPIC) {
            $is_scotopic = false;
        }
        if ($helflag & HeliacalConstants::SE_HELFLAG_VISLIM_SCOTOPIC) {
            $is_scotopic = true;
        }

        // Set scotopic flag output
        if ($scotopic_flag !== null) {
            $scotopic_flag = $is_scotopic ? 1 : 0;

            // Check if in transitional zone
            $BNIGHT = HeliacalConstants::BNIGHT;
            $BNIGHT_FACTOR = 1.1; // Not in constants, hardcoded in C
            if ($BNIGHT * $BNIGHT_FACTOR > $Bsk && $BNIGHT / $BNIGHT_FACTOR < $Bsk) {
                $scotopic_flag |= 2; // Transitional zone
            }
        }

        // Calculate threshold from Schaefer, Archaeoastronomy XV, 2000, page 129
        if ($is_scotopic) {
            $C1 = 1.5848931924611e-10; // pow(10, -9.8)
            $C2 = 0.012589254117942;    // pow(10, -1.9)
        } else {
            $C1 = 4.4668359215096e-9;   // pow(10, -8.35)
            $C2 = 1.2589254117942e-6;   // pow(10, -5.9)
        }

        // Apply corrections to sky brightness
        $Bsk = $Bsk * $CorrFactor1;
        $Th = $C1 * pow(1 + sqrt($C2 * $Bsk), 2) * $CorrFactor2;

        // Visual limiting magnitude of point source
        return -16.57 - 2.5 * (log($Th) / $log10);
    }

    /**
     * Set default heliacal parameters
     * Port from swehel.c:1324-1362
     *
     * Sets default values for atmospheric and observer parameters if not provided
     *
     * @param array &$datm Atmospheric parameters [pressure, temperature, humidity, VR]
     *                     Output: defaults set if values are 0
     * @param array $dgeo Geographic location [longitude, latitude, altitude]
     * @param array &$dobs Observer parameters [Age, SN, Binocular, OpticMag, OpticDia, OpticTrans]
     *                     Output: defaults set if values are 0
     * @param int $helflag Heliacal flags
     * @return void
     */
    public static function defaultHeliacalParameters(
        array &$datm,
        array $dgeo,
        array &$dobs,
        int $helflag
    ): void {
        // Set atmospheric defaults if not provided
        if ($datm[0] <= 0) {
            // Estimate atmospheric pressure according to International Standard Atmosphere (ISA)
            $datm[0] = 1013.25 * pow(1 - 0.0065 * $dgeo[2] / 288, 5.255);

            // Temperature (ISA standard lapse rate)
            if ($datm[1] == 0) {
                $datm[1] = 15 - 0.0065 * $dgeo[2];
            }

            // Relative humidity (independent of pressure and altitude)
            if ($datm[2] == 0) {
                $datm[2] = 40;
            }

            // Note: datm[3] / VR (meteorological range) defaults outside this function
        } else {
            // Humidity bounds checking (except in SIMULATE_VICTORVB mode)
            // In PHP port, we always apply bounds
            if ($datm[2] <= 0.00000001) {
                $datm[2] = 0.00000001;
            }
            if ($datm[2] >= 99.99999999) {
                $datm[2] = 99.99999999;
            }
        }

        // Age of observer (default 36 years)
        if ($dobs[0] == 0) {
            $dobs[0] = 36;
        }

        // SN: Snellen factor of visual acuity (default 1.0 = normal 20/20 vision)
        if ($dobs[1] == 0) {
            $dobs[1] = 1;
        }

        // Optical instrument parameters
        if (!($helflag & HeliacalConstants::SE_HELFLAG_OPTICAL_PARAMS)) {
            // Clear optical params if flag not set
            for ($i = 2; $i <= 5; $i++) {
                $dobs[$i] = 0;
            }
        }

        // If OpticMagn is undefined (0), use naked eye
        if ($dobs[3] == 0) {
            $dobs[2] = 1; // Binocular = 1 (using two eyes)
            $dobs[3] = 1; // OpticMagn = 1 (no magnification, naked eye)
            // dobs[4] and dobs[5] (OpticDia and OpticTrans) will be defaulted in OpticFactor()
        }
    }
}
