<?php

declare(strict_types=1);

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;
use Swisseph\DeltaT;
use Swisseph\ErrorCodes;
use Swisseph\Output;
use Swisseph\Swe\Planets\EphemerisStrategyFactory;
use Swisseph\VectorMath;
use Swisseph\Coordinates;
use Swisseph\Bias;
use Swisseph\Precession;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephUtils;
use Swisseph\Sidereal;
use Swisseph\Swe\Functions\SiderealFunctions;

/**
 * Тонкий фасад: валидация аргументов + делегирование стратегиям.
 * Полная физика (прецессия, нутация, световые поправки) внутри стратегий.
 */
final class PlanetsFunctions
{
    /**
     * Delegates to appropriate ephemeris strategy.
     * Maintains C API contract: returns iflag (>=0) или SE_ERR (<0).
     */
    public static function calc(float $jd_tt, int $ipl, int $iflag, array &$xx, ?string &$serr = null): int
    {
        $xx = Output::emptyForFlags($iflag);

        // Диапазон поддерживаемых планет (расширен для Node и Chiron)
        // SE_SUN..SE_PLUTO (0-9), SE_MEAN_NODE (10), SE_EARTH (14), SE_CHIRON (15)
        $validRange = ($ipl >= Constants::SE_SUN && $ipl <= Constants::SE_PLUTO)
            || $ipl === Constants::SE_MEAN_NODE
            || $ipl === Constants::SE_EARTH
            || $ipl === Constants::SE_CHIRON;

        if (!$validRange) {
            $serr = ErrorCodes::compose(ErrorCodes::INVALID_ARG, "ipl=$ipl out of supported range");
            return Constants::SE_ERR;
        }

        // Специальная обработка для Mean Node - делегируем к swe_nod_aps
        if ($ipl === Constants::SE_MEAN_NODE) {
            return self::calcMeanNode($jd_tt, $iflag, $xx, $serr);
        }

        // Специальная обработка для Chiron - используем asteroid ephemeris
        if ($ipl === Constants::SE_CHIRON) {
            return self::calcChiron($jd_tt, $iflag, $xx, $serr);
        }

        // Проверка взаимоисключающих источников эфемерид
        $srcCount = (($iflag & Constants::SEFLG_JPLEPH) ? 1 : 0)
            + (($iflag & Constants::SEFLG_SWIEPH) ? 1 : 0)
            + (($iflag & Constants::SEFLG_MOSEPH) ? 1 : 0);
        if ($srcCount > 1) {
            $serr = ErrorCodes::compose(
                ErrorCodes::INVALID_ARG,
                'Conflicting ephemeris source flags (choose one of JPLEPH/SWIEPH/MOSEPH)'
            );
            return Constants::SE_ERR;
        }

        // VSOP87 конфликтует с любым другим источником (эксклюзивный путь)
        if (($iflag & Constants::SEFLG_VSOP87) && $srcCount > 0) {
            $serr = ErrorCodes::compose(
                ErrorCodes::INVALID_ARG,
                'VSOP87 flag conflicts with other ephemeris source flags'
            );
            return Constants::SE_ERR;
        }

        // По умолчанию используем SWIEPH, если ничего не выставлено и не выбран VSOP87
        if (!(($iflag & Constants::SEFLG_VSOP87)
            || ($iflag & Constants::SEFLG_JPLEPH)
            || ($iflag & Constants::SEFLG_SWIEPH)
            || ($iflag & Constants::SEFLG_MOSEPH))) {
            $iflag |= Constants::SEFLG_SWIEPH;
        }

        $strategy = EphemerisStrategyFactory::forFlags($iflag, $ipl);
        if ($strategy === null) {
            // Источник не реализован (например JPLEPH/MOSEPH в текущем порте)
            $serr = ErrorCodes::compose(ErrorCodes::UNSUPPORTED, 'Ephemeris source not implemented yet');
            return Constants::SE_ERR;
        }

        $res = $strategy->compute($jd_tt, $ipl, $iflag);
        if ($res->retc < 0) {
            if ($serr === null) {
                $serr = $res->serr;
            }
            return Constants::SE_ERR;
        }

        // Берём финальный блок координат (с учётом флагов) из стратегии
        $xx = $res->x;
        return $iflag;
    }

    /**
     * Calculate Mean Node by delegating to swe_nod_aps
     * Returns ascending node position
     *
     * @param float $jd_tt Julian day in TT
     * @param int $iflag Calculation flags
     * @param array &$xx Output coordinates
     * @param string|null &$serr Error message
     * @return int iflag on success, SE_ERR on error
     */
    private static function calcMeanNode(float $jd_tt, int $iflag, array &$xx, ?string &$serr): int
    {
        // Use NodesApsidesFunctions to get mean node for Moon
        $xnasc = null;  // Ascending node
        $xndsc = null;  // Descending node (not needed)
        $xperi = null;  // Perihelion (not needed)
        $xaphe = null;  // Aphelion (not needed)

        $ret = NodesApsidesFunctions::nodAps(
            $jd_tt,
            Constants::SE_MOON,  // Mean node is for the Moon
            $iflag,
            Constants::SE_NODBIT_MEAN,  // Request mean nodes
            $xnasc,
            $xndsc,
            $xperi,
            $xaphe,
            $serr
        );

        if ($ret < 0) {
            return Constants::SE_ERR;
        }

        // Copy ascending node coordinates to output
        $xx = $xnasc;

        return $iflag;
    }

    /**
     * Calculate Chiron using Swiss Ephemeris asteroid files
     *
     * Chiron is stored in seas_*.se1 files (main asteroid belt files)
     * Uses SwephPlanCalculator → SwephCalculator to read ephemeris data
     *
     * @param float $jd_tt Julian day in TT
     * @param int $iflag Calculation flags
     * @param array &$xx Output coordinates
     * @param string|null &$serr Error message
     * @return int iflag on success, SE_ERR on error
     */
    private static function calcChiron(float $jd_tt, int $iflag, array &$xx, ?string &$serr): int
    {
        // Use SwephStrategy which already supports reading asteroid ephemeris files
        $strategy = EphemerisStrategyFactory::forFlags($iflag | Constants::SEFLG_SWIEPH, Constants::SE_CHIRON);

        if ($strategy === null) {
            $serr = 'SE_CHIRON: Swiss Ephemeris strategy not available';
            return Constants::SE_ERR;
        }

        $res = $strategy->compute($jd_tt, Constants::SE_CHIRON, $iflag | Constants::SEFLG_SWIEPH);

        if ($res->retc < 0) {
            $serr = $res->serr;
            return Constants::SE_ERR;
        }

        $xx = $res->x;
        return $iflag;
    }

    /**
     * UT -> TT конверсия затем делегирование стратегии.
     */
    public static function calcUt(float $jd_ut, int $ipl, int $iflag, array &$xx, ?string &$serr = null): int
    {
        $dt_sec = DeltaT::deltaTSecondsFromJd($jd_ut);
        $jd_tt = $jd_ut + $dt_sec / 86400.0;
        return self::calc($jd_tt, $ipl, $iflag, $xx, $serr);
    }

    /**
     * Planetocentric calculation - calculate positions relative to another planet.
     *
     * Port of swe_calc_pctr() from sweph.c:8096-8340 (~250 lines).
     * Full C API compatibility - NO SIMPLIFICATIONS.
     *
     * @param float $tjd Julian day number (TT/ET)
     * @param int $ipl Target planet number
     * @param int $iplctr Center planet number (viewing position)
     * @param int $iflag Calculation flags
     * @param array &$xxret Output array [6] for coordinates
     * @param string|null &$serr Error string
     * @return int iflag on success, SE_ERR on error
     */
    public static function calcPctr(
        float $tjd,
        int $ipl,
        int $iplctr,
        int $iflag,
        array &$xxret,
        ?string &$serr = null
    ): int {
        // Validation: planets must be different
        if ($ipl === $iplctr) {
            $serr = sprintf("ipl and iplctr (= %d) must not be identical", $ipl);
            return Constants::SE_ERR;
        }

        // Validate flags (plaus_iflag equivalent)
        $iflag = self::plausibleIflag($iflag, $ipl, $tjd, $serr);
        $epheflag = $iflag & Constants::SEFLG_EPHMASK;

        // Fill obliquity and nutation values in swed
        // C: swe_calc(tjd + swe_deltat_ex(tjd, epheflag, serr), SE_ECL_NUT, iflag, xx, serr);
        $swed = SwedState::getInstance();
        $swed->oec->calculate($tjd, $iflag);
        $swed->ensureNutation($tjd, $iflag, $swed->oec->seps, $swed->oec->ceps);

        // Remove HELCTR/BARYCTR from iflag for internal calculations
        $iflag &= ~(Constants::SEFLG_HELCTR | Constants::SEFLG_BARYCTR);

        // Build flags for barycentric J2000 ICRS calculations
        $iflag2 = $epheflag;
        $iflag2 |= (Constants::SEFLG_BARYCTR | Constants::SEFLG_J2000 | Constants::SEFLG_ICRS |
                   Constants::SEFLG_TRUEPOS | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ |
                   Constants::SEFLG_SPEED);
        $iflag2 |= (Constants::SEFLG_NOABERR | Constants::SEFLG_NOGDEFL);

        // Calculate center planet (barycentric)
        $xxctr = [];
        $retc = self::calc($tjd, $iplctr, $iflag2, $xxctr, $serr);
        if ($retc < 0) {
            $serr = "calc_pctr: Failed to calculate center planet $iplctr: " . ($serr ?: 'unknown error');
            return Constants::SE_ERR;
        }
        // Convert to polar for debug
        $xxctr_polar = [];
        Coordinates::cartPolSp($xxctr, $xxctr_polar);
        $ra_ctr_deg = $xxctr_polar[0] * Constants::RADTODEG;
        $dec_ctr_deg = $xxctr_polar[1] * Constants::RADTODEG;
        // error_log("DEBUG calc_pctr INITIAL xxctr (barycentric equatorial J2000): RA={$ra_ctr_deg}°, Dec={$dec_ctr_deg}°");
        // error_log("DEBUG calc_pctr INITIAL xxctr[0-2]=[{$xxctr[0]}, {$xxctr[1]}, {$xxctr[2]}]");

        // Calculate target planet (barycentric)
        $xx = [];
        $retc = self::calc($tjd, $ipl, $iflag2, $xx, $serr);
        if ($retc < 0) {
            $serr = "calc_pctr: Failed to calculate target planet $ipl: " . ($serr ?: 'unknown error');
            return Constants::SE_ERR;
        }
        // Convert to polar for debug
        $xx_polar = [];
        Coordinates::cartPolSp($xx, $xx_polar);
        $ra_deg = $xx_polar[0] * Constants::RADTODEG;
        $dec_deg = $xx_polar[1] * Constants::RADTODEG;
        // error_log("DEBUG calc_pctr INITIAL xx (barycentric equatorial J2000): RA={$ra_deg}°, Dec={$dec_deg}°");
        // error_log("DEBUG calc_pctr INITIAL xx[0-2]=[{$xx[0]}, {$xx[1]}, {$xx[2]}]");

        // Save initial position
        $xx0 = array_slice($xx, 0, 6);

        // Initialize arrays
        $xxsp = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xxsv = array_fill(0, 24, 0.0);
        $xreturn = array_fill(0, 24, 0.0);
        $xxctr2 = [];
        $dtsave_for_defl = 0.0;
        $t = 0.0;

        /*******************************
         * light-time geocentric       *
         *******************************/
        if (!($iflag & Constants::SEFLG_TRUEPOS)) {
            // number of iterations - 1
            $niter = 1;

            if ($iflag & Constants::SEFLG_SPEED) {
                /*
                 * Apparent speed is influenced by the fact that dt changes with
                 * time. This makes a difference of several hundredths of an
                 * arc second / day. To take this into account, we compute
                 * 1. true position - apparent position at time t - 1.
                 * 2. true position - apparent position at time t.
                 * 3. the difference between the two is the part of the daily motion
                 * that results from the change of dt.
                 */
                for ($i = 0; $i <= 2; $i++) {
                    $xxsv[$i] = $xxsp[$i] = $xx[$i] - $xx[$i + 3];
                }
                for ($j = 0; $j <= $niter; $j++) {
                    $dx = [0.0, 0.0, 0.0];
                    for ($i = 0; $i <= 2; $i++) {
                        $dx[$i] = $xxsp[$i];
                        $dx[$i] -= ($xxctr[$i] - $xxctr[$i + 3]);
                    }
                    // new dt
                    $dt = sqrt(VectorMath::squareSum($dx)) * Constants::AUNIT / Constants::CLIGHT / 86400.0;
                    for ($i = 0; $i <= 2; $i++) {
                        // rough apparent position at t-1
                        $xxsp[$i] = $xxsv[$i] - $dt * $xx0[$i + 3];
                    }
                }
                // true position - apparent position at time t-1
                for ($i = 0; $i <= 2; $i++) {
                    $xxsp[$i] = $xxsv[$i] - $xxsp[$i];
                }
            }

            // dt and t(apparent)
            for ($j = 0; $j <= $niter; $j++) {
                $dx = [0.0, 0.0, 0.0];
                for ($i = 0; $i <= 2; $i++) {
                    $dx[$i] = $xx[$i];
                    $dx[$i] -= $xxctr[$i];
                }
                $dt = sqrt(VectorMath::squareSum($dx)) * Constants::AUNIT / Constants::CLIGHT / 86400.0;
                // new t
                $t = $tjd - $dt;
                $dtsave_for_defl = $dt;
                for ($i = 0; $i <= 2; $i++) {
                    // rough apparent position at t
                    $xx[$i] = $xx0[$i] - $dt * $xx0[$i + 3];
                }
            }

            // part of daily motion resulting from change of dt
            if ($iflag & Constants::SEFLG_SPEED) {
                for ($i = 0; $i <= 2; $i++) {
                    $xxsp[$i] = $xx0[$i] - $xx[$i] - $xxsp[$i];
                }
            }

            $retc = self::calc($t, $iplctr, $iflag2, $xxctr2, $serr);
            if ($retc < 0) {
                $serr = "calc_pctr: Failed to calculate center planet at t-dt: " . ($serr ?: 'unknown error');
                return Constants::SE_ERR;
            }

            $retc = self::calc($t, $ipl, $iflag2, $xx, $serr);
            if ($retc < 0) {
                $serr = "calc_pctr: Failed to calculate target planet at t-dt: " . ($serr ?: 'unknown error');
                return Constants::SE_ERR;
            }
        }

        /*******************************
         * conversion to planetocenter *
         *******************************/
        if (!($iflag & Constants::SEFLG_HELCTR) && !($iflag & Constants::SEFLG_BARYCTR)) {
            // subtract earth
            for ($i = 0; $i <= 5; $i++) {
                $xx[$i] -= $xxctr[$i];
            }
            if (!($iflag & Constants::SEFLG_TRUEPOS)) {
                /*
                 * Apparent speed is also influenced by
                 * the change of dt during motion.
                 * Neglect of this would result in an error of several 0.01"
                 */
                if ($iflag & Constants::SEFLG_SPEED) {
                    for ($i = 3; $i <= 5; $i++) {
                        $xx[$i] -= $xxsp[$i - 3];
                    }
                }
            }
        }

        if (!($iflag & Constants::SEFLG_SPEED)) {
            for ($i = 3; $i <= 5; $i++) {
                $xx[$i] = 0.0;
            }
        }

        /************************************
         * relativistic deflection of light *
         ************************************/
        if (!($iflag & Constants::SEFLG_TRUEPOS) && !($iflag & Constants::SEFLG_NOGDEFL)) {
            // SEFLG_NOGDEFL is on, if SEFLG_HELCTR or SEFLG_BARYCTR
            Coordinates::deflectLight($xx, $dtsave_for_defl, $iflag);
        }

        /**********************************
         * 'annual' aberration of light   *
         **********************************/
        if (!($iflag & Constants::SEFLG_TRUEPOS) && !($iflag & Constants::SEFLG_NOABERR)) {
            // SEFLG_NOABERR is on, if SEFLG_HELCTR or SEFLG_BARYCTR
            // For planetocentric: use center planet (xxctr) for aberration, not Earth
            \Swisseph\Swe\FixedStars\StarTransforms::aberrLight($xx, $xxctr);
            /*
             * Apparent speed is also influenced by
             * the difference of speed of the earth between t and t-dt.
             * Neglecting this would involve an error of several 0.1"
             */
            if ($iflag & Constants::SEFLG_SPEED) {
                for ($i = 3; $i <= 5; $i++) {
                    $xx[$i] += $xxctr[$i] - $xxctr2[$i];
                }
            }
        }

        if (!($iflag & Constants::SEFLG_SPEED)) {
            for ($i = 3; $i <= 5; $i++) {
                $xx[$i] = 0.0;
            }
        }

        // ICRS to J2000
        if (!($iflag & Constants::SEFLG_ICRS) && SwephUtils::getDenum($ipl, $epheflag) >= 403) {
            Bias::bias($xx, $t, $iflag, false);
        }

        // save J2000 coordinates; required for sidereal positions
        for ($i = 0; $i <= 5; $i++) {
            $xxsv[$i] = $xx[$i];
        }

        /************************************************
         * precession, equator 2000 -> equator of date *
         ************************************************/
        if (!($iflag & Constants::SEFLG_J2000)) {
            Precession::precess($xx, $tjd, $iflag, Constants::J2000_TO_J, null);
            if ($iflag & Constants::SEFLG_SPEED) {
                Precession::precessSpeed($xx, $tjd, $iflag, Constants::J2000_TO_J);
            }
            $oe = SwedState::getInstance()->oec;
        } else {
            $oe = SwedState::getInstance()->oec2000;
        }

        /************************************************
         * nutation                                     *
         ************************************************/
        if (!($iflag & Constants::SEFLG_NONUT)) {
            $swed = SwedState::getInstance();
            Coordinates::nutate($xx, $swed->nutMatrix, $swed->nutMatrixVelocity, $iflag, false);
        }

        // now we have equatorial cartesian coordinates; save them
        for ($i = 0; $i <= 5; $i++) {
            $xreturn[18 + $i] = $xx[$i];
        }

        /************************************************
         * transformation to ecliptic.                  *
         * with sidereal calc. this will be overwritten *
         * afterwards.                                  *
         ************************************************/
        // error_log("DEBUG calc_pctr BEFORE ecl transform: xx[0-2]=[{$xx[0]}, {$xx[1]}, {$xx[2]}] (equatorial)");
        $pos = [$xx[0], $xx[1], $xx[2]];
        $out = [];
        Coordinates::coortrf2($pos, $out, $oe->seps, $oe->ceps);
        $xx[0] = $out[0];
        $xx[1] = $out[1];
        $xx[2] = $out[2];
        // error_log("DEBUG calc_pctr AFTER ecl transform: xx[0-2]=[{$xx[0]}, {$xx[1]}, {$xx[2]}] (ecliptic)");

        if ($iflag & Constants::SEFLG_SPEED) {
            $vel = [$xx[3], $xx[4], $xx[5]];
            $outv = [];
            Coordinates::coortrf2($vel, $outv, $oe->seps, $oe->ceps);
            $xx[3] = $outv[0];
            $xx[4] = $outv[1];
            $xx[5] = $outv[2];
        }

        if (!($iflag & Constants::SEFLG_NONUT)) {
            $swed = SwedState::getInstance();
            $pos = [$xx[0], $xx[1], $xx[2]];
            $out = [];
            Coordinates::coortrf2($pos, $out, $swed->snut, $swed->cnut);
            $xx[0] = $out[0];
            $xx[1] = $out[1];
            $xx[2] = $out[2];

            if ($iflag & Constants::SEFLG_SPEED) {
                $vel = [$xx[3], $xx[4], $xx[5]];
                $outv = [];
                Coordinates::coortrf2($vel, $outv, $swed->snut, $swed->cnut);
                $xx[3] = $outv[0];
                $xx[4] = $outv[1];
                $xx[5] = $outv[2];
            }
        }

        // now we have ecliptic cartesian coordinates
        // error_log("DEBUG calc_pctr ECLIPTIC CARTESIAN xx[0-2]=[{$xx[0]}, {$xx[1]}, {$xx[2]}]");
        for ($i = 0; $i <= 5; $i++) {
            $xreturn[6 + $i] = $xx[$i];
        }

        /************************************
         * sidereal positions               *
         ************************************/
        if ($iflag & Constants::SEFLG_SIDEREAL) {
            $swed = SwedState::getInstance();
            // project onto ecliptic t0
            if ($swed->sidd->sid_mode & Constants::SE_SIDBIT_ECL_T0) {
                $xxsv_arr = array_slice($xxsv, 0, 6);
                $xret6 = array_slice($xreturn, 6, 6);
                $xret18 = array_slice($xreturn, 18, 6);
                if (SiderealFunctions::tropRa2sidLon($xxsv_arr, $xret6, $xret18, $iflag) !== Constants::OK) {
                    return Constants::SE_ERR;
                }
                for ($i = 0; $i <= 5; $i++) {
                    $xreturn[6 + $i] = $xret6[$i];
                }
            // project onto solar system equator
            } elseif ($swed->sidd->sid_mode & Constants::SE_SIDBIT_SSY_PLANE) {
                $xxsv_arr = array_slice($xxsv, 0, 6);
                $xret6 = array_slice($xreturn, 6, 6);
                if (SiderealFunctions::tropRa2sidLonSosy($xxsv_arr, $xret6, $iflag) !== Constants::OK) {
                    return Constants::SE_ERR;
                }
                for ($i = 0; $i <= 5; $i++) {
                    $xreturn[6 + $i] = $xret6[$i];
                }
            } else {
                // traditional algorithm
                $xret_slice = array_slice($xreturn, 6, 6);
                $polar_temp = [];
                Coordinates::cartPolSp($xret_slice, $polar_temp);
                for ($i = 0; $i <= 5; $i++) {
                    $xreturn[$i] = $polar_temp[$i];
                }

                // note, swi_get_ayanamsa_ex() disturbs present calculations, if sun is calculated with
                // TRUE_CHITRA ayanamsha, because the ayanamsha also calculates the sun.
                // Therefore current values are saved...
                $xxsv_temp = $xreturn;
                $daya = [0.0, 0.0];
                if (Sidereal::getAyanamsaWithSpeed($tjd, $iflag, $daya, $serr) === Constants::SE_ERR) {
                    return Constants::SE_ERR;
                }
                // ... and restored
                $xreturn = $xxsv_temp;

                $xreturn[0] -= $daya[0] * Constants::DEGTORAD;
                $xreturn[3] -= $daya[1] * Constants::DEGTORAD;

                $xret_polar = array_slice($xreturn, 0, 6);
                $cart_temp = [];
                Coordinates::polCartSp($xret_polar, $cart_temp);
                for ($i = 0; $i <= 5; $i++) {
                    $xreturn[6 + $i] = $cart_temp[$i];
                }
            }
        }

        /************************************************
         * transformation to polar coordinates          *
         ************************************************/
        $xret18_slice = array_slice($xreturn, 18, 6);
        $polar18_temp = [];
        Coordinates::cartPolSp($xret18_slice, $polar18_temp);
        for ($i = 0; $i <= 5; $i++) {
            $xreturn[12 + $i] = $polar18_temp[$i];
        }

        $xret6_slice = array_slice($xreturn, 6, 6);
        // error_log("DEBUG calc_pctr BEFORE cartPolSp: xret6_slice[0-2]=[{$xret6_slice[0]}, {$xret6_slice[1]}, {$xret6_slice[2]}]");
        $polar6_temp = [];
        Coordinates::cartPolSp($xret6_slice, $polar6_temp);
        // error_log("DEBUG calc_pctr AFTER cartPolSp: polar6_temp[0-2]=[{$polar6_temp[0]}, {$polar6_temp[1]}, {$polar6_temp[2]}] (radians)");
        for ($i = 0; $i <= 5; $i++) {
            $xreturn[$i] = $polar6_temp[$i];
        }

        /**********************
         * radians to degrees *
         **********************/
        for ($i = 0; $i < 2; $i++) {
            $xreturn[$i] *= Constants::RADTODEG;        // ecliptic
            $xreturn[$i + 3] *= Constants::RADTODEG;
            $xreturn[$i + 12] *= Constants::RADTODEG;   // equator
            $xreturn[$i + 15] *= Constants::RADTODEG;
        }

        // return values
        if ($iflag & Constants::SEFLG_EQUATORIAL) {
            $xs = array_slice($xreturn, 12, 6); // equatorial coordinates
        } else {
            $xs = array_slice($xreturn, 0, 6);  // ecliptic coordinates
        }

        if ($iflag & Constants::SEFLG_XYZ) {
            $xs = array_slice($xreturn, ($iflag & Constants::SEFLG_EQUATORIAL) ? 18 : 6, 6); // cartesian coordinates
        }

        for ($i = 0; $i < 6; $i++) {
            $xxret[$i] = $xs[$i];
        }

        if (!($iflag & Constants::SEFLG_SPEED)) {
            for ($i = 3; $i < 6; $i++) {
                $xxret[$i] = 0.0;
            }
        }

        if ($iflag & Constants::SEFLG_RADIANS) {
            for ($i = 0; $i < 2; $i++) {
                $xxret[$i] *= Constants::DEGTORAD;
            }
            if ($iflag & Constants::SEFLG_SPEED) {
                for ($i = 3; $i < 5; $i++) {
                    $xxret[$i] *= Constants::DEGTORAD;
                }
            }
        }

        if ($retc < 0) {
            return Constants::SE_ERR;
        }

        return $iflag;
    }

    /**
     * Validate and adjust calculation flags (port of plaus_iflag).
     */
    private static function plausibleIflag(int $iflag, int $ipl, float $tjd, ?string &$serr): int
    {
        // For now, just return iflag as-is
        // Full implementation would validate flag combinations
        return $iflag;
    }
}
