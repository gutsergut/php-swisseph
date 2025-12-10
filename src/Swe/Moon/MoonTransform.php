<?php

namespace Swisseph\Swe\Moon;

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;
use Swisseph\Swe\Observer\Observer;

/**
 * Moon position transformations
 * Port of app_pos_etc_moon() from sweph.c:4086-4280
 */
class MoonTransform
{
    private const AUNIT = 1.4959787066e11; // AU in meters
    private const CLIGHT = 299792458.0;    // Speed of light m/s

    /**
     * Apply all transformations to Moon position
     *
     * Full port of app_pos_etc_moon() from sweph.c:4086-4280
     *
     * @param int $iflag Calculation flags
     * @param string|null &$serr Error message
     * @return int OK or ERR
     */
    public static function appPosEtc(int $iflag, ?string &$serr = null): int
    {
        $swed = SwedState::getInstance();
        $pedp = &$swed->pldat[SwephConstants::SEI_EARTH];
        $psdp = &$swed->pldat[SwephConstants::SEI_SUNBARY];
        $pdp = &$swed->pldat[SwephConstants::SEI_MOON];

        // Check if conversions already done
        // From sweph.c:4098-4104
        $flg1 = $iflag & ~Constants::SEFLG_EQUATORIAL & ~Constants::SEFLG_XYZ;
        $flg2 = $pdp->xflgs & ~Constants::SEFLG_EQUATORIAL & ~Constants::SEFLG_XYZ;
        if ($flg1 === $flg2) {
            $pdp->xflgs = $iflag;
            $pdp->iephe = $iflag & Constants::SEFLG_EPHMASK;
            return Constants::SE_OK;
        }

        // Copy coordinates to working array
        // From sweph.c:4106-4110
        // CRITICAL: Ensure pdp->x always has 6 elements before copying
        // In C, plan_data.x is double[6] so always has 6 elements
        // In PHP, we must ensure this explicitly
        while (count($pdp->x) < 6) {
            $pdp->x[] = 0.0;
        }

        $xx = array_fill(0, 6, 0.0);
        $xxm = array_fill(0, 6, 0.0);
        for ($i = 0; $i <= 5; $i++) {
            $xx[$i] = $pdp->x[$i];
            $xxm[$i] = $xx[$i];
        }

        /***********************************
         * to solar system barycentric
         ***********************************/
        // From sweph.c:4111-4113
        for ($i = 0; $i <= 5; $i++) {
            $xx[$i] += $pedp->x[$i];
        }

        /*******************************
         * observer
         *******************************/
        // From sweph.c:4114-4151
        $xobs = array_fill(0, 6, 0.0);

        if ($iflag & Constants::SEFLG_TOPOCTR) {
            if (getenv('DEBUG_MOON')) {
                error_log(sprintf("DEBUG [MoonTransform] TOPOCTR flag detected, xxm BEFORE=[%.15f, %.15f, %.15f]",
                    $xxm[0], $xxm[1], $xxm[2]));
            }

            // Topocentric: get observer position
            // From sweph.c:4119-4137
            if ($swed->topd->teval !== $pdp->teval || $swed->topd->teval === 0.0) {
                if (getenv('DEBUG_MOON')) {
                    error_log("DEBUG [MoonTransform] Calling Observer::getObserver()");
                }
                if (Observer::getObserver($pdp->teval, $iflag | Constants::SEFLG_NONUT, true, $xobs, $serr) !== Constants::SE_OK) {
                    return Constants::SE_ERR;
                }
                if (getenv('DEBUG_MOON')) {
                    error_log(sprintf("DEBUG [MoonTransform] xobs AFTER getObserver=[%.15f, %.15f, %.15f]",
                        $xobs[0], $xobs[1], $xobs[2]));
                }
            } else {
                if (getenv('DEBUG_MOON')) {
                    error_log("DEBUG [MoonTransform] Using cached xobs from topd");
                }
                for ($i = 0; $i <= 5; $i++) {
                    $xobs[$i] = $swed->topd->xobs[$i];
                }
                if (getenv('DEBUG_MOON')) {
                    error_log(sprintf("DEBUG [MoonTransform] xobs FROM topd=[%.15f, %.15f, %.15f]",
                        $xobs[0], $xobs[1], $xobs[2]));
                }
            }

            // CRITICAL: Subtract observer position from geocentric Moon
            // From sweph.c:4131-4133
            for ($i = 0; $i <= 5; $i++) {
                $xxm[$i] -= $xobs[$i];
            }

            if (getenv('DEBUG_MOON')) {
                error_log(sprintf("DEBUG [MoonTransform] xxm AFTER subtract xobs=[%.15f, %.15f, %.15f]",
                    $xxm[0], $xxm[1], $xxm[2]));
            }

            // Add Earth position to observer
            // From sweph.c:4135-4136
            for ($i = 0; $i <= 5; $i++) {
                $xobs[$i] += $pedp->x[$i];
            }

            if (getenv('DEBUG_MOON')) {
                error_log(sprintf("DEBUG [MoonTransform] xobs AFTER add Earth=[%.15f, %.15f, %.15f]",
                    $xobs[0], $xobs[1], $xobs[2]));
            }
        } elseif ($iflag & Constants::SEFLG_BARYCTR) {
            // Barycentric
            for ($i = 0; $i <= 5; $i++) {
                $xobs[$i] = 0.0;
            }
            for ($i = 0; $i <= 5; $i++) {
                $xxm[$i] += $pedp->x[$i];
            }
        } elseif ($iflag & Constants::SEFLG_HELCTR) {
            // Heliocentric
            for ($i = 0; $i <= 5; $i++) {
                $xobs[$i] = $psdp->x[$i];
            }
            for ($i = 0; $i <= 5; $i++) {
                $xxm[$i] += $pedp->x[$i] - $psdp->x[$i];
            }
        } else {
            // Geocentric (default)
            for ($i = 0; $i <= 5; $i++) {
                $xobs[$i] = $pedp->x[$i];
            }
        }

        /*******************************
         * light-time                  *
         *******************************/
        // From sweph.c:4152-4211
        $t = $pdp->teval;
        $xobs2 = array_fill(0, 6, 0.0);
        $xe = array_fill(0, 6, 0.0);
        $xs = array_fill(0, 6, 0.0);

        if (!($iflag & Constants::SEFLG_TRUEPOS)) {
            // Calculate light-time
            $dt = sqrt(self::squareSum($xxm)) * self::AUNIT / self::CLIGHT / 86400.0;
            $t = $pdp->teval - $dt;

            // Recalculate positions at light-time
            // From sweph.c:4164-4200
            switch ($pdp->iephe) {
                case Constants::SEFLG_SWIEPH:
                    // From sweph.c:4180-4189
                    $retc = \Swisseph\SwephFile\SwephPlanCalculator::calculate(
                        $t,
                        SwephConstants::SEI_MOON,
                        Constants::SE_MOON,
                        SwephConstants::SEI_FILE_MOON,
                        $iflag,
                        false, // NO_SAVE
                        $xx,
                        $xe,
                        $xs,
                        $xxm_dummy,
                        $serr
                    );
                    if ($retc !== Constants::SE_OK) {
                        return $retc;
                    }

                    if (getenv('DEBUG_MOON')) {
                        error_log(sprintf("DEBUG [MoonTransform] AFTER light-time sweplan: xx (geocentric Moon)=[%.15f, %.15f, %.15f]",
                            $xx[0], $xx[1], $xx[2]));
                        error_log(sprintf("DEBUG [MoonTransform] AFTER light-time sweplan: xe (Earth)=[%.15f, %.15f, %.15f]",
                            $xe[0], $xe[1], $xe[2]));
                    }

                    // Add Earth position
                    for ($i = 0; $i <= 5; $i++) {
                        $xx[$i] += $xe[$i];
                    }

                    if (getenv('DEBUG_MOON')) {
                        error_log(sprintf("DEBUG [MoonTransform] AFTER adding xe: xx (barycentric Moon)=[%.15f, %.15f, %.15f]",
                            $xx[0], $xx[1], $xx[2]));
                    }
                    break;

                case Constants::SEFLG_MOSEPH:
                    // Approximate method for Moshier
                    // From sweph.c:4191-4201
                    for ($i = 0; $i <= 2; $i++) {
                        $xx[$i] -= $dt * $xx[$i + 3];
                        $xe[$i] = $pedp->x[$i] - $dt * $pedp->x[$i + 3];
                        $xe[$i + 3] = $pedp->x[$i + 3];
                        $xs[$i] = 0.0;
                        $xs[$i + 3] = 0.0;
                    }
                    break;

                default:
                    // JPL ephemeris would go here
                    $serr = "JPL ephemeris not supported for Moon yet";
                    return Constants::SE_ERR;
            }

            // Get observer position at light-time
            // From sweph.c:4202-4217
            if ($iflag & Constants::SEFLG_TOPOCTR) {
                if (Observer::getObserver($t, $iflag | Constants::SEFLG_NONUT, false, $xobs2, $serr) !== Constants::SE_OK) {
                    return Constants::SE_ERR;
                }
                for ($i = 0; $i <= 5; $i++) {
                    $xobs2[$i] += $xe[$i];
                }
            } elseif ($iflag & Constants::SEFLG_BARYCTR) {
                for ($i = 0; $i <= 5; $i++) {
                    $xobs2[$i] = 0.0;
                }
            } elseif ($iflag & Constants::SEFLG_HELCTR) {
                for ($i = 0; $i <= 5; $i++) {
                    $xobs2[$i] = $xs[$i];
                }
            } else {
                for ($i = 0; $i <= 5; $i++) {
                    $xobs2[$i] = $xe[$i];
                }
            }
        }

        /*************************
         * to correct center
         *************************/
        // From sweph.c:4218-4220
        if (getenv('DEBUG_MOON')) {
            error_log(sprintf("DEBUG [MoonTransform] BEFORE 'to correct center': xx=[%.15f, %.15f, %.15f]",
                $xx[0], $xx[1], $xx[2]));
            error_log(sprintf("DEBUG [MoonTransform] xobs to subtract=[%.15f, %.15f, %.15f]",
                $xobs[0], $xobs[1], $xobs[2]));
        }

        for ($i = 0; $i <= 5; $i++) {
            $xx[$i] -= $xobs[$i];
        }

        if (getenv('DEBUG_MOON')) {
            error_log(sprintf("DEBUG [MoonTransform] AFTER 'to correct center': xx=[%.15f, %.15f, %.15f]",
                $xx[0], $xx[1], $xx[2]));
        }

        /**********************************
         * 'annual' aberration of light   *
         **********************************/
        // From sweph.c:4221-4237
        if (!($iflag & Constants::SEFLG_TRUEPOS) && !($iflag & Constants::SEFLG_NOABERR)) {
            // Apply aberration
            self::aberrLight($xx, $xobs, $iflag);

            // Speed correction for aberration
            // From sweph.c:4231-4234
            if ($iflag & Constants::SEFLG_SPEED) {
                for ($i = 3; $i <= 5; $i++) {
                    $xx[$i] += $xobs[$i] - $xobs2[$i];
                }
            }
        }

        // If no speed, set to zero
        // From sweph.c:4238-4241
        if (!($iflag & Constants::SEFLG_SPEED)) {
            for ($i = 3; $i <= 5; $i++) {
                $xx[$i] = 0.0;
            }
        }

        // ICRS to J2000
        // From sweph.c:4242-4245
        if (!($iflag & Constants::SEFLG_ICRS) && self::getDenum(SwephConstants::SEI_MOON, $iflag) >= 403) {
            \Swisseph\Bias::bias($xx, $t, $iflag, false);
        }

        // Save J2000 coordinates
        // From sweph.c:4246-4248
        $xxsv = array_fill(0, 6, 0.0);
        for ($i = 0; $i <= 5; $i++) {
            $xxsv[$i] = $xx[$i];
        }

        /************************************************
         * precession, equator 2000 -> equator of date *
         ************************************************/
        // From sweph.c:4249-4256
        $oe = null;
        if (!($iflag & Constants::SEFLG_J2000)) {
            \Swisseph\Precession::precess($xx, $pdp->teval, $iflag, Constants::J2000_TO_J);
            if ($iflag & Constants::SEFLG_SPEED) {
                \Swisseph\Precession::precessSpeed($xx, $pdp->teval, $iflag, Constants::J2000_TO_J);
            }
            $oe = $swed->oec;
        } else {
            $oe = $swed->oec2000;
        }

        // Final transformations and coordinate conversions
        // From sweph.c:4257
        if (getenv('DEBUG_MOON_STEP')) {
            error_log(sprintf("[MoonTransform] PRE appPosRest xyz=%.12f,%.12f,%.12f", $xx[0], $xx[1], $xx[2]));
        }

        // Реальный обликвитет: ранее использовался $oe->eps (0.0 неинициализированный), что ломало
        // поворот equatorial->ecliptic (отсутствие sin(eps)). Берём средний обликвитет даты.
        // Unified obliquity source: use SwedState cached epsilon data
        $swed = \Swisseph\SwephFile\SwedState::getInstance();
        if ($iflag & Constants::SEFLG_J2000) {
            // Use precomputed J2000 obliquity from oec2000
            $seps = $swed->oec2000->seps;
            $ceps = $swed->oec2000->ceps;
            $eps  = $swed->oec2000->eps;
        } else {
            if ($swed->oec->needsUpdate($pdp->teval)) {
                $swed->oec->calculate($pdp->teval, $iflag);
            }
            $seps = $swed->oec->seps;
            $ceps = $swed->oec->ceps;
            $eps  = $swed->oec->eps;
        }
        if (getenv('DEBUG_MOON_STEP')) {
            error_log(sprintf("[MoonTransform] Obliquity (mean) eps=%.12f rad (%.9f°)", $eps, $eps * Constants::RADTODEG));
        }

        // Вызываем CoordinateTransform напрямую (appPosRest обёртка упрощена)
        \Swisseph\CoordinateTransform::appPosRest($pdp, $iflag, $xx, $seps, $ceps);
        $pdp->xflgs = $iflag;
        $pdp->iephe = $iflag & Constants::SEFLG_EPHMASK;
        $retc = Constants::SE_OK;

        if (getenv('DEBUG_MOON_STEP')) {
            $lon = $pdp->xreturn[0];
            $ra  = $pdp->xreturn[12];
            $diffLonRa = ($lon - $ra) * 3600.0;
            error_log(sprintf('[MoonTransform] POST appPosRest lon-ra diff=%.2f"', $diffLonRa));
        }

        return $retc;
    }

    /**
     * Calculate sum of squares (for distance)
     */
    private static function squareSum(array $x): float
    {
        return $x[0] * $x[0] + $x[1] * $x[1] + $x[2] * $x[2];
    }

    /**
     * Apply aberration of light
     * Полный порт aberr_light() из sweph.c:3645-3660 без упрощений.
     * Меняет только позицию (x,y,z). Скорость корректируется отдельно (см. блок после вызова).
     */
    private static function aberrLight(array &$xx, array $xobs, int $iflag): void
    {
        // Отключено флагами TRUEPOS / NOABERR (проверено до вызова)
        // xobs содержит положение/скорость наблюдателя (земли или топо) в AU и AU/day.
        // xx содержит положение Луны относительно корректного центра (в AU).

        // Если радиус нулевой — ничего не делаем (защита от деления на ноль)
        $ru = sqrt($xx[0]*$xx[0] + $xx[1]*$xx[1] + $xx[2]*$xx[2]);
        if ($ru === 0.0) {
            return;
        }

        // Скорость наблюдателя (земли) в долях скорости света.
        // xobs[3..5] в AU/day. v/c = v(AU/day) * AUNIT(m) / (CLIGHT(m/s)*86400(s/day))
        $v = [
            $xobs[3] * Constants::AUNIT / (Constants::CLIGHT * 86400.0),
            $xobs[4] * Constants::AUNIT / (Constants::CLIGHT * 86400.0),
            $xobs[5] * Constants::AUNIT / (Constants::CLIGHT * 86400.0),
        ];

        $v2 = $v[0]*$v[0] + $v[1]*$v[1] + $v[2]*$v[2];
        // Компонент sqrt(1 - β^2)
        $b1 = sqrt(1.0 - $v2);

        // Скалярное произведение направления на объект (u) и скорости наблюдателя
        $f1 = ($xx[0]*$v[0] + $xx[1]*$v[1] + $xx[2]*$v[2]) / $ru;
        $f2 = 1.0 + $f1 / (1.0 + $b1);

        // Применяем релятивистскую формулу сложения скоростей к направлению
        // xx' = (b1 * xx + f2 * |u| * v) / (1 + f1)
        $denom = 1.0 + $f1;
        // Защита от патологического случая (не должен возникнуть для реальных скоростей Земли ~10^-4)
        if ($denom === 0.0) {
            return;
        }
        for ($i = 0; $i <= 2; $i++) {
            $xx[$i] = ($b1 * $xx[$i] + $f2 * $ru * $v[$i]) / $denom;
        }

        if (getenv('DEBUG_MOON_ABERR')) {
            error_log(sprintf('DEBUG [MoonAberr] Applied annual aberration: b1=%.12f f1=%.12e f2=%.12e', $b1, $f1, $f2));
        }
    }

    /**
     * Get DE number for ephemeris
     */
    private static function getDenum(int $ipli, int $iflag): int
    {
        // Return 405 for Swiss Ephemeris (DE405 based)
        if ($iflag & Constants::SEFLG_SWIEPH) {
            return 405;
        }
        return 0;
    }

    /**
     * Final coordinate transformations
     * Port of app_pos_rest() from swephlib.c
     */
    private static function appPosRest(
        object $pdp,
        int $iflag,
        array $xx,
        array $xxsv,
        object $oe,
        ?string &$serr
    ): int {
        // This would call the full app_pos_rest implementation
        // For now, delegate to existing CoordinateTransform
        $eps = $oe->eps ?? 0.40909280422232897;
        $seps = sin($eps);
        $ceps = cos($eps);

        \Swisseph\CoordinateTransform::appPosRest($pdp, $iflag, $xx, $seps, $ceps);

        $pdp->xflgs = $iflag;
        $pdp->iephe = $iflag & Constants::SEFLG_EPHMASK;

        return Constants::SE_OK;
    }
}
