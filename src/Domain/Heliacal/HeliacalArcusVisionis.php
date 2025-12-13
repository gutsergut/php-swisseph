<?php

declare(strict_types=1);

namespace Swisseph\Domain\Heliacal;

use Swisseph\Constants;
use function abs;
use function cos;
use function fabs;
use function sin;
use function sprintf;
use function strncmp;

/**
 * Arcus Visionis calculations for heliacal phenomena.
 *
 * Implements the geometric arcus visionis method for determining
 * the visibility of celestial objects near the Sun.
 *
 * References:
 * - Schaefer (1993): "Astronomy and the limits of vision" Archaeoastronomy
 * - Yallop (1998): "A Method for Predicting the First Visibility of the Crescent Moon"
 *
 * Source: swehel.c lines 1464-1900
 */
final class HeliacalArcusVisionis
{
    /**
     * Calculate topocentric arcus visionis using bisection method.
     *
     * Finds the altitude difference between object and Sun at which
     * the object's magnitude equals the visual limiting magnitude.
     * Uses bisection method (binary search) to find the root.
     *
     * @param float $Magn Object magnitude
     * @param array $dobs Observer parameters [age, SN, ...] (6 elements)
     * @param float $AltO Object altitude (degrees, topocentric unrefracted)
     * @param float $AziO Object azimuth (degrees)
     * @param float $AltM Moon altitude (degrees, or -90 if Moon not relevant)
     * @param float $AziM Moon azimuth (degrees)
     * @param float $JDNDaysUT Julian day number (UT)
     * @param float $AziS Sun azimuth (degrees)
     * @param float $sunra Sun right ascension (degrees)
     * @param float $Lat Geographic latitude (degrees)
     * @param float $HeightEye Observer height above sea level (meters)
     * @param array $datm Atmospheric parameters [Press, Temp, RH, VR] (4 elements)
     * @param int $helflag Calculation flags (SE_HELFLAG_*)
     * @param string|null &$serr Error string (output)
     * @return array{int, float} [status, arcus_visionis] - status: OK/ERR, arcus_visionis in degrees
     *
     * Source: swehel.c lines 1562-1598
     */
    public static function TopoArcVisionis(
        float $Magn,
        array $dobs,
        float $AltO,
        float $AziO,
        float $AltM,
        float $AziM,
        float $JDNDaysUT,
        float $AziS,
        float $sunra,
        float $Lat,
        float $HeightEye,
        array $datm,
        int $helflag,
        ?string &$serr
    ): array {
        $xR = 0.0;
        $Xl = 45.0;
        $scotopic_flag = null;

        // Calculate function values at left and right bounds
        $Yl = HeliacalVision::VisLimMagn(
            $dobs, $AltO, $AziO, $AltM, $AziM, $JDNDaysUT,
            $AltO - $Xl, $AziS, $sunra, $Lat, $HeightEye,
            $datm, $helflag, $scotopic_flag, $serr
        );
        $Yl = $Magn - $Yl;

        $Yr = HeliacalVision::VisLimMagn(
            $dobs, $AltO, $AziO, $AltM, $AziM, $JDNDaysUT,
            $AltO - $xR, $AziS, $sunra, $Lat, $HeightEye,
            $datm, $helflag, $scotopic_flag, $serr
        );
        $Yr = $Magn - $Yr;

        // Bisection method: http://en.wikipedia.org/wiki/Bisection_method
        if (($Yl * $Yr) <= 0) {
            while (abs($xR - $Xl) > HeliacalConstants::EPSILON) {
                // Calculate midpoint of domain
                $Xm = ($xR + $Xl) / 2.0;
                $AltSi = $AltO - $Xm;
                $AziSi = $AziS;

                $Ym = HeliacalVision::VisLimMagn(
                    $dobs, $AltO, $AziO, $AltM, $AziM, $JDNDaysUT,
                    $AltSi, $AziSi, $sunra, $Lat, $HeightEye,
                    $datm, $helflag, $scotopic_flag, $serr
                );
                $Ym = $Magn - $Ym;

                if (($Yl * $Ym) > 0) {
                    // Throw away left half
                    $Xl = $Xm;
                    $Yl = $Ym;
                } else {
                    // Throw away right half
                    $xR = $Xm;
                    $Yr = $Ym;
                }
            }
            $Xm = ($xR + $Xl) / 2.0;
        } else {
            $Xm = 99.0;
        }

        if ($Xm < $AltO) {
            $Xm = $AltO;
        }

        return [Constants::OK, $Xm];
    }

    /**
     * Calculate heliacal angle (optimum altitude for visibility).
     *
     * Finds the object altitude at which the arcus visionis is minimum.
     * Returns three angles:
     * - dangret[0]: Object altitude at minimum arcus visionis (degrees)
     * - dangret[1]: Minimum arcus visionis value (degrees)
     * - dangret[2]: Sun altitude at minimum (degrees) = dangret[0] - dangret[1]
     *
     * Algorithm:
     * 1. Coarse search from 2° to 20° to find approximate minimum
     * 2. Bisection refinement to 0.1° precision
     * 3. Uses derivative test (compare Ym vs ymd at Xm+0.025) to find minimum
     *
     * @param float $Magn Object magnitude
     * @param array $dobs Observer parameters [age, SN, ...] (6 elements)
     * @param float $AziO Object azimuth (degrees)
     * @param float $AltM Moon altitude (degrees)
     * @param float $AziM Moon azimuth (degrees)
     * @param float $JDNDaysUT Julian day number (UT)
     * @param float $AziS Sun azimuth (degrees)
     * @param array $dgeo Geographic location [lon, lat, height] (3 elements)
     * @param array $datm Atmospheric parameters [Press, Temp, RH, VR] (4 elements)
     * @param int $helflag Calculation flags (SE_HELFLAG_*)
     * @param string|null &$serr Error string (output)
     * @return array{int, array} [status, dangret] - status: OK/ERR, dangret: [AltO_min, ArcVis_min, AltS_min]
     *
     * Source: swehel.c lines 1636-1717
     */
    public static function HeliacalAngle(
        float $Magn,
        array $dobs,
        float $AziO,
        float $AltM,
        float $AziM,
        float $JDNDaysUT,
        float $AziS,
        array $dgeo,
        array $datm,
        int $helflag,
        ?string &$serr
    ): array {
        $dangret = [0.0, 0.0, 0.0];

        $sunra = HeliacalGeometry::SunRA($JDNDaysUT, $helflag, $serr);
        $Lat = $dgeo[1];
        $HeightEye = $dgeo[2];

        // PLSV mode: simplified formula
        if (HeliacalConstants::PLSV === 1) {
            $dangret[0] = HeliacalConstants::CRITICAL_ANGLE;
            $dangret[1] = HeliacalConstants::CRITICAL_ANGLE + $Magn * 2.492 + 13.447;
            $dangret[2] = -($Magn * 2.492 + 13.447);
            return [Constants::OK, $dangret];
        }

        // Coarse search: find approximate minimum
        $minx = 2.0;
        $maxx = 20.0;
        $xmin = 0.0;
        $ymin = 10000.0;

        for ($x = $minx; $x <= $maxx; $x += 1.0) {
            [$status, $Arc] = self::TopoArcVisionis(
                $Magn, $dobs, $x, $AziO, $AltM, $AziM, $JDNDaysUT,
                $AziS, $sunra, $Lat, $HeightEye, $datm, $helflag, $serr
            );
            if ($status === Constants::ERR) {
                return [Constants::ERR, $dangret];
            }
            if ($Arc < $ymin) {
                $ymin = $Arc;
                $xmin = $x;
            }
        }

        // Bisection refinement
        $Xl = $xmin - 1.0;
        $xR = $xmin + 1.0;

        [$status, $Yr] = self::TopoArcVisionis(
            $Magn, $dobs, $xR, $AziO, $AltM, $AziM, $JDNDaysUT,
            $AziS, $sunra, $Lat, $HeightEye, $datm, $helflag, $serr
        );
        if ($status === Constants::ERR) {
            return [Constants::ERR, $dangret];
        }

        [$status, $Yl] = self::TopoArcVisionis(
            $Magn, $dobs, $Xl, $AziO, $AltM, $AziM, $JDNDaysUT,
            $AziS, $sunra, $Lat, $HeightEye, $datm, $helflag, $serr
        );
        if ($status === Constants::ERR) {
            return [Constants::ERR, $dangret];
        }

        // Bisection method: http://en.wikipedia.org/wiki/Bisection_method
        while (abs($xR - $Xl) > 0.1) {
            // Calculate midpoint of domain
            $Xm = ($xR + $Xl) / 2.0;
            $DELTAx = 0.025;
            $xmd = $Xm + $DELTAx;

            [$status, $Ym] = self::TopoArcVisionis(
                $Magn, $dobs, $Xm, $AziO, $AltM, $AziM, $JDNDaysUT,
                $AziS, $sunra, $Lat, $HeightEye, $datm, $helflag, $serr
            );
            if ($status === Constants::ERR) {
                return [Constants::ERR, $dangret];
            }

            [$status, $ymd] = self::TopoArcVisionis(
                $Magn, $dobs, $xmd, $AziO, $AltM, $AziM, $JDNDaysUT,
                $AziS, $sunra, $Lat, $HeightEye, $datm, $helflag, $serr
            );
            if ($status === Constants::ERR) {
                return [Constants::ERR, $dangret];
            }

            if ($Ym >= $ymd) {
                // Throw away left half
                $Xl = $Xm;
                $Yl = $Ym;
            } else {
                // Throw away right half
                $xR = $Xm;
                $Yr = $Ym;
            }
        }

        $Xm = ($xR + $Xl) / 2.0;
        $Ym = ($Yr + $Yl) / 2.0;

        $dangret[1] = $Ym;           // Minimum arcus visionis
        $dangret[2] = $Xm - $Ym;     // Sun altitude at minimum
        $dangret[0] = $Xm;           // Object altitude at minimum

        return [Constants::OK, $dangret];
    }

    /**
     * Determine topocentric arcus visionis for named object.
     *
     * Wrapper function that:
     * 1. Calculates object magnitude
     * 2. Gets object altitude and azimuth
     * 3. Gets Moon position (or sets to -90° if object is Moon)
     * 4. Gets Sun azimuth
     * 5. Calls TopoArcVisionis()
     *
     * @param array $dobs Observer parameters [age, SN, ...] (6 elements)
     * @param float $JDNDaysUT Julian day number (UT)
     * @param array $dgeo Geographic location [lon, lat, height] (3 elements)
     * @param array $datm Atmospheric parameters [Press, Temp, RH, VR] (4 elements)
     * @param string $ObjectName Object name (planet or star)
     * @param int $helflag Calculation flags (SE_HELFLAG_*)
     * @param string|null &$serr Error string (output)
     * @return array{int, float} [status, arcus_visionis] - status: OK/ERR, arcus_visionis in degrees
     *
     * Source: swehel.c lines 1759-1786
     */
    public static function DeterTAV(
        array $dobs,
        float $JDNDaysUT,
        array $dgeo,
        array $datm,
        string $ObjectName,
        int $helflag,
        ?string &$serr
    ): array {
        $sunra = HeliacalGeometry::SunRA($JDNDaysUT, $helflag, $serr);

        // Get object magnitude
        $Magn = 0.0;
        $status = HeliacalMagnitude::Magnitude($JDNDaysUT, $dgeo, $ObjectName, $helflag, $Magn, $serr);
        if ($status === Constants::ERR) {
            return [Constants::ERR, 0.0];
        }

        // Get object altitude
        $AltO = 0.0;
        $status = HeliacalGeometry::ObjectLoc($JDNDaysUT, $dgeo, $datm, $ObjectName, 0, $helflag, $AltO, $serr);
        if ($status === Constants::ERR) {
            return [Constants::ERR, 0.0];
        }

        // Get object azimuth
        $AziO = 0.0;
        $status = HeliacalGeometry::ObjectLoc($JDNDaysUT, $dgeo, $datm, $ObjectName, 1, $helflag, $AziO, $serr);
        if ($status === Constants::ERR) {
            return [Constants::ERR, 0.0];
        }

        // Get Moon position (or -90° if object is Moon)
        if (strncmp($ObjectName, "moon", 4) === 0) {
            $AltM = -90.0;
            $AziM = 0.0;
        } else {
            $AltM = 0.0;
            $status = HeliacalGeometry::ObjectLoc($JDNDaysUT, $dgeo, $datm, "moon", 0, $helflag, $AltM, $serr);
            if ($status === Constants::ERR) {
                return [Constants::ERR, 0.0];
            }

            $AziM = 0.0;
            $status = HeliacalGeometry::ObjectLoc($JDNDaysUT, $dgeo, $datm, "moon", 1, $helflag, $AziM, $serr);
            if ($status === Constants::ERR) {
                return [Constants::ERR, 0.0];
            }
        }

        // Get Sun azimuth
        $AziS = 0.0;
        $status = HeliacalGeometry::ObjectLoc($JDNDaysUT, $dgeo, $datm, "sun", 1, $helflag, $AziS, $serr);
        if ($status === Constants::ERR) {
            return [Constants::ERR, 0.0];
        }

        // Calculate topocentric arcus visionis
        return self::TopoArcVisionis(
            $Magn, $dobs, $AltO, $AziO, $AltM, $AziM, $JDNDaysUT,
            $AziS, $sunra, $dgeo[1], $dgeo[2], $datm, $helflag, $serr
        );
    }

    /**
     * Calculate crescent width of Moon (Yallop 1998).
     *
     * W = 0.27245 * p * (1 + sin(h') * sin(p)) * (1 - cos(Δh) * cos(ΔAz))
     *
     * where:
     * - p = parallax (degrees)
     * - h' = geocentric altitude of object (degrees)
     * - Δh = altitude difference Sun - object (degrees)
     * - ΔAz = azimuth difference Sun - object (degrees)
     *
     * @param float $AltO Object altitude (degrees, topocentric)
     * @param float $AziO Object azimuth (degrees)
     * @param float $AltS Sun altitude (degrees)
     * @param float $AziS Sun azimuth (degrees)
     * @param float $parallax Parallax (degrees)
     * @return float Crescent width (degrees)
     *
     * Reference: Yallop 1998, page 3
     * Source: swehel.c lines 1732-1741
     */
    public static function WidthMoon(
        float $AltO,
        float $AziO,
        float $AltS,
        float $AziS,
        float $parallax
    ): float {
        // Geocentric altitude = topocentric altitude + parallax
        $GeoAltO = $AltO + $parallax;

        return 0.27245 * $parallax
            * (1.0 + sin($GeoAltO * HeliacalConstants::DEGTORAD) * sin($parallax * HeliacalConstants::DEGTORAD))
            * (1.0 - cos(($AltS - $GeoAltO) * HeliacalConstants::DEGTORAD) * cos(($AziS - $AziO) * HeliacalConstants::DEGTORAD));
    }

    /**
     * Calculate crescent length of Moon.
     *
     * Formula from Sultan 2005:
     * L = (D - 0.3 * (D + W) / (2 * W)) / 60
     *
     * where:
     * - D = Moon diameter (arcminutes)
     * - W = crescent width (arcminutes)
     * - Result converted back to degrees
     *
     * @param float $W Crescent width (degrees)
     * @param float $Diamoon Moon diameter (degrees, or 0 for default)
     * @return float Crescent length (degrees)
     *
     * Reference: http://calendar.ut.ac.ir/Fa/Crescent/Data/Sultan2005.pdf
     * Source: swehel.c lines 1743-1753
     */
    public static function LengthMoon(float $W, float $Diamoon): float
    {
        if ($Diamoon === 0.0) {
            $Diamoon = HeliacalConstants::AVG_RADIUS_MOON * 2.0;
        }

        $Wi = $W * 60.0;      // Convert to arcminutes
        $D = $Diamoon * 60.0; // Convert to arcminutes

        // Crescent length formula
        return ($D - 0.3 * ($D + $Wi) / 2.0 / $Wi) / 60.0;
    }

    /**
     * Calculate Yallop's q-test value.
     *
     * q = (ARCV - (11.8371 - 6.3226*W + 0.7319*W² - 0.1018*W³)) / 10
     *
     * where:
     * - ARCV = geocentric arcus visionis (degrees)
     * - W = crescent width (arcminutes)
     *
     * q-test criteria:
     * - q >= +0.216: Easily visible
     * - -0.014 to +0.216: Visible under perfect conditions
     * - -0.160 to -0.014: May need optical aid
     * - -0.232 to -0.160: Will need optical aid
     * - q < -0.232: Not visible with any aid
     *
     * @param float $W Crescent width (degrees)
     * @param float $GeoARCVact Geocentric arcus visionis (degrees)
     * @return float q-test value (dimensionless)
     *
     * Reference: Yallop 1998
     * Source: swehel.c lines 1755-1757
     */
    public static function qYallop(float $W, float $GeoARCVact): float
    {
        $Wi = $W * 60.0; // Convert to arcminutes

        return ($GeoARCVact - (11.8371 - 6.3226 * $Wi + 0.7319 * $Wi * $Wi - 0.1018 * $Wi * $Wi * $Wi)) / 10.0;
    }

    /**
     * PUBLIC API: Calculate topocentric arcus visionis.
     *
     * Calculates the altitude difference between object and Sun at which
     * the object becomes visible/invisible.
     *
     * @param float $tjdut Julian day number (UT)
     * @param array $dgeo Geographic location [lon, lat, height] (3 elements)
     *                    - lon: longitude (degrees, eastern positive)
     *                    - lat: latitude (degrees, northern positive)
     *                    - height: altitude above sea level (meters)
     * @param array $datm Atmospheric parameters [Press, Temp, RH, VR] (4 elements)
     * @param array $dobs Observer parameters [age, SN, ...] (6 elements)
     * @param int $helflag Calculation flags (SE_HELFLAG_*)
     * @param float $mag Object magnitude
     * @param float $azi_obj Object azimuth (degrees)
     * @param float $alt_obj Object altitude (degrees, topocentric)
     * @param float $azi_sun Sun azimuth (degrees)
     * @param float $azi_moon Moon azimuth (degrees)
     * @param float $alt_moon Moon altitude (degrees)
     * @param string|null &$serr Error string (output)
     * @return array{int, float} [status, arcus_visionis] - status: OK/ERR, arcus_visionis in degrees
     *
     * Source: swehel.c lines 1600-1610
     */
    public static function swe_topo_arcus_visionis(
        float $tjdut,
        array $dgeo,
        array $datm,
        array $dobs,
        int $helflag,
        float $mag,
        float $azi_obj,
        float $alt_obj,
        float $azi_sun,
        float $azi_moon,
        float $alt_moon,
        ?string &$serr
    ): array {
        // swi_set_tid_acc(tjdut, helflag, 0, serr);
        $sunra = HeliacalGeometry::SunRA($tjdut, $helflag, $serr);
        if ($serr !== null && $serr !== '') {
            return [Constants::ERR, 0.0];
        }

        HeliacalVision::defaultHeliacalParameters($datm, $dgeo, $dobs, $helflag);

        return self::TopoArcVisionis(
            $mag, $dobs, $alt_obj, $azi_obj, $alt_moon, $azi_moon,
            $tjdut, $azi_sun, $sunra, $dgeo[1], $dgeo[2],
            $datm, $helflag, $serr
        );
    }

    /**
     * PUBLIC API: Calculate heliacal angle.
     *
     * Finds the object altitude at which visibility is optimum
     * (arcus visionis is minimum).
     *
     * @param float $tjdut Julian day number (UT)
     * @param array $dgeo Geographic location [lon, lat, height] (3 elements)
     * @param array $datm Atmospheric parameters [Press, Temp, RH, VR] (4 elements)
     * @param array $dobs Observer parameters [age, SN, ...] (6 elements)
     * @param int $helflag Calculation flags (SE_HELFLAG_*)
     * @param float $mag Object magnitude
     * @param float $azi_obj Object azimuth (degrees)
     * @param float $azi_sun Sun azimuth (degrees)
     * @param float $azi_moon Moon azimuth (degrees)
     * @param float $alt_moon Moon altitude (degrees)
     * @param string|null &$serr Error string (output)
     * @return array{int, array} [status, dret]
     *         - status: OK/ERR
     *         - dret: [AltO_min, ArcVis_min, AltS_min] (all in degrees)
     *
     * Source: swehel.c lines 1719-1730
     */
    public static function swe_heliacal_angle(
        float $tjdut,
        array $dgeo,
        array $datm,
        array $dobs,
        int $helflag,
        float $mag,
        float $azi_obj,
        float $azi_sun,
        float $azi_moon,
        float $alt_moon,
        ?string &$serr
    ): array {
        if ($dgeo[2] < Constants::SEI_ECL_GEOALT_MIN || $dgeo[2] > Constants::SEI_ECL_GEOALT_MAX) {
            if ($serr !== null) {
                $serr = sprintf(
                    "location for heliacal events must be between %.0f and %.0f m above sea",
                    Constants::SEI_ECL_GEOALT_MIN,
                    Constants::SEI_ECL_GEOALT_MAX
                );
            }
            return [Constants::ERR, [0.0, 0.0, 0.0]];
        }

        // swi_set_tid_acc(tjdut, helflag, 0, serr);
        HeliacalVision::defaultHeliacalParameters($datm, $dgeo, $dobs, $helflag);

        return self::HeliacalAngle(
            $mag, $dobs, $azi_obj, $alt_moon, $azi_moon,
            $tjdut, $azi_sun, $dgeo, $datm, $helflag, $serr
        );
    }

    /**
     * PUBLIC API: Calculate visual limiting magnitude for object.
     *
     * Returns the faintest magnitude visible under given conditions.
     * Also returns object position, Sun position, Moon position, and
     * actual object magnitude.
     *
     * Output array dret:
     * - dret[0]: Visual limiting magnitude
     * - dret[1]: Object altitude (degrees)
     * - dret[2]: Object azimuth (degrees)
     * - dret[3]: Sun altitude (degrees)
     * - dret[4]: Sun azimuth (degrees)
     * - dret[5]: Moon altitude (degrees)
     * - dret[6]: Moon azimuth (degrees)
     * - dret[7]: Object actual magnitude
     *
     * Return value:
     * - 0: photopic vision
     * - 1: scotopic vision
     * - -2: object below horizon
     * - ERR: error
     *
     * @param float $tjdut Julian day number (UT)
     * @param array $dgeo Geographic location [lon, lat, height] (3 elements)
     * @param array $datm Atmospheric parameters [Press, Temp, RH, VR] (4 elements)
     * @param array $dobs Observer parameters [age, SN, ...] (6 elements)
     * @param string $ObjectName Object name
     * @param int $helflag Calculation flags (SE_HELFLAG_*)
     * @param string|null &$serr Error string (output)
     * @return array{int, array} [retval, dret] - retval: scotopic_flag or error code, dret: 8-element array
     *
     * Source: swehel.c lines 1464-1560
     */
    public static function swe_vis_limit_mag(
        float $tjdut,
        array $dgeo,
        array $datm,
        array $dobs,
        string $ObjectName,
        int $helflag,
        ?string &$serr
    ): array {
        $dret = array_fill(0, 8, 0.0);
        $scotopic_flag = null;

        $ObjectName = strtolower($ObjectName);

        if (HeliacalMagnitude::DeterObject($ObjectName) === Constants::SE_SUN) {
            if ($serr !== null) {
                $serr = "it makes no sense to call swe_vis_limit_mag() for the Sun";
            }
            return [Constants::ERR, $dret];
        }

        // swi_set_tid_acc(tjdut, helflag, 0, serr);
        $sunra = HeliacalGeometry::SunRA($tjdut, $helflag, $serr);
        HeliacalVision::defaultHeliacalParameters($datm, $dgeo, $dobs, $helflag);
        // swe_set_topo(dgeo[0], dgeo[1], dgeo[2]);

        // Get object altitude
        $AltO = 0.0;
        $status = HeliacalGeometry::ObjectLoc($tjdut, $dgeo, $datm, $ObjectName, 0, $helflag, $AltO, $serr);
        if ($status === Constants::ERR) {
            return [Constants::ERR, $dret];
        }

        if ($AltO < 0) {
            if ($serr !== null) {
                $serr = "object is below local horizon";
            }
            $dret[0] = -100.0;
            return [-2, $dret];
        }

        // Get object azimuth
        $AziO = 0.0;
        $status = HeliacalGeometry::ObjectLoc($tjdut, $dgeo, $datm, $ObjectName, 1, $helflag, $AziO, $serr);
        if ($status === Constants::ERR) {
            return [Constants::ERR, $dret];
        }

        // Get Sun position (or -90° if VISLIM_DARK flag)
        if ($helflag & Constants::SE_HELFLAG_VISLIM_DARK) {
            $AltS = -90.0;
            $AziS = 0.0;
        } else {
            $AltS = 0.0;
            $status = HeliacalGeometry::ObjectLoc($tjdut, $dgeo, $datm, "sun", 0, $helflag, $AltS, $serr);
            if ($status === Constants::ERR) {
                return [Constants::ERR, $dret];
            }

            $AziS = 0.0;
            $status = HeliacalGeometry::ObjectLoc($tjdut, $dgeo, $datm, "sun", 1, $helflag, $AziS, $serr);
            if ($status === Constants::ERR) {
                return [Constants::ERR, $dret];
            }
        }

        // Get Moon position (or -90° if object is Moon or NOMOON flag)
        if (
            strncmp($ObjectName, "moon", 4) === 0 ||
            ($helflag & Constants::SE_HELFLAG_VISLIM_DARK) ||
            ($helflag & Constants::SE_HELFLAG_VISLIM_NOMOON)
        ) {
            $AltM = -90.0;
            $AziM = 0.0;
        } else {
            $AltM = 0.0;
            $status = HeliacalGeometry::ObjectLoc($tjdut, $dgeo, $datm, "moon", 0, $helflag, $AltM, $serr);
            if ($status === Constants::ERR) {
                return [Constants::ERR, $dret];
            }

            $AziM = 0.0;
            $status = HeliacalGeometry::ObjectLoc($tjdut, $dgeo, $datm, "moon", 1, $helflag, $AziM, $serr);
            if ($status === Constants::ERR) {
                return [Constants::ERR, $dret];
            }
        }

        // Calculate visual limiting magnitude
        $dret[0] = HeliacalVision::VisLimMagn(
            $dobs, $AltO, $AziO, $AltM, $AziM, $tjdut, $AltS, $AziS,
            $sunra, $dgeo[1], $dgeo[2], $datm, $helflag, $scotopic_flag, $serr
        );

        $dret[1] = $AltO;
        $dret[2] = $AziO;
        $dret[3] = $AltS;
        $dret[4] = $AziS;
        $dret[5] = $AltM;
        $dret[6] = $AziM;

        // Get object magnitude
        $dret[7] = 0.0;
        $status = HeliacalMagnitude::Magnitude($tjdut, $dgeo, $ObjectName, $helflag, $dret[7], $serr);
        if ($status === Constants::ERR) {
            return [Constants::ERR, $dret];
        }

        $retval = $scotopic_flag ?? 0;

        return [$retval, $dret];
    }
}
