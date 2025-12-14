<?php

declare(strict_types=1);

namespace Swisseph\Swe\Planets;

use Swisseph\Constants;
use Swisseph\SwephFile\SwephConstants;

/**
 * Общий пайплайн видимых координат из барицентрического J2000 xyz(+v).
 * Порядок строго как в C: light-time → geocentric → deflection → aberration → precession → app_pos_rest.
 */
final class PlanetApparentPipeline
{
    /**
     * @param float $jd_tt TT юлианская дата
     * @param int   $ipl   SE_* индекс планеты
     * @param int   $iflag флаги calc
     * @param array $x_bary_j2000 [x,y,z,dx,dy,dz] AU, AU/day в эклиптике J2000 барицентр
     * @return array{0:float,1:float,2:float,3:float,4:float,5:float}
     */
    public static function computeFinal(float $jd_tt, int $ipl, int $iflag, array $x_bary_j2000): array
    {
        $xx = $x_bary_j2000;

        $swed = \Swisseph\SwephFile\SwedState::getInstance();
        $earth_pd = $swed->pldat[SwephConstants::SEI_EARTH] ?? null;
        $sunb_pd  = $swed->pldat[SwephConstants::SEI_SUNBARY] ?? null;

        // Определяем наблюдателя (xobs) в зависимости от флага
        // BARYCTR: xobs = [0,0,0,0,0,0]
        // HELCTR:  xobs = Sun barycentric position
        // default: xobs = Earth barycentric position
        $xobs = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        if ($iflag & Constants::SEFLG_BARYCTR) {
            // Барицентрический: наблюдатель в барицентре
            $xobs = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        } elseif ($iflag & Constants::SEFLG_HELCTR) {
            if ($sunb_pd) {
                $xobs = $sunb_pd->x;
            }
        } else {
            if ($earth_pd) {
                $xobs = $earth_pd->x;
            }
        }

        // 1) Light-time (две итерации) — пропускаем только для TRUEPOS или SE_EARTH
        // xxsp = коррекция скорости из-за изменения dt во времени
        $xxsp = [0.0, 0.0, 0.0];
        $dt_light_for_defl = 0.0; // save for deflection later
        if (!($iflag & Constants::SEFLG_TRUEPOS) && $ipl !== Constants::SE_EARTH) {
            $c_au_per_day = 173.144632674240; // скорость света в AU/day
            $xx0 = $xx; // сохраняем оригинальные координаты (для xxsp)

            /*
             * Speed correction for light-time (как в C sweph.c:2554-2588)
             * Apparent speed is influenced by the fact that dt changes with time.
             * This makes a difference of several hundredths of an arc second / day.
             * We compute:
             * 1. true position - apparent position at time t - 1
             * 2. true position - apparent position at time t
             * 3. the difference is the part of daily motion from change of dt
             */
            if ($iflag & Constants::SEFLG_SPEED) {
                // xxsv = position at t-1 (грубо: pos - speed)
                $xxsv = [$xx[0] - $xx[3], $xx[1] - $xx[4], $xx[2] - $xx[5]];
                $xxsp = $xxsv;

                // итерация для позиции t-1
                for ($j = 0; $j <= 1; $j++) { // niter = 1 для SWIEPH
                    $dx = [$xxsp[0], $xxsp[1], $xxsp[2]];
                    if (!($iflag & Constants::SEFLG_HELCTR) && !($iflag & Constants::SEFLG_BARYCTR)) {
                        $dx[0] -= ($xobs[0] - $xobs[3]);
                        $dx[1] -= ($xobs[1] - $xobs[4]);
                        $dx[2] -= ($xobs[2] - $xobs[5]);
                    }
                    // new dt for t-1
                    $dt_sp = sqrt($dx[0]*$dx[0] + $dx[1]*$dx[1] + $dx[2]*$dx[2]) / $c_au_per_day;
                    // rough apparent position at t-1
                    $xxsp[0] = $xxsv[0] - $dt_sp * $xx0[3];
                    $xxsp[1] = $xxsv[1] - $dt_sp * $xx0[4];
                    $xxsp[2] = $xxsv[2] - $dt_sp * $xx0[5];
                }
                // true position - apparent position at time t-1
                $xxsp[0] = $xxsv[0] - $xxsp[0];
                $xxsp[1] = $xxsv[1] - $xxsp[1];
                $xxsp[2] = $xxsv[2] - $xxsp[2];
            }

            // Light-time iterations: compute dt_light
            // dx = planet - observer
            $dx0p = $xx[0] - $xobs[0];
            $dx1p = $xx[1] - $xobs[1];
            $dx2p = $xx[2] - $xobs[2];
            $r = sqrt($dx0p*$dx0p + $dx1p*$dx1p + $dx2p*$dx2p);
            $dt_light = ($r > 0.0) ? ($r / $c_au_per_day) : 0.0;

            // Rough apparent position for iteration 1
            for ($i = 0; $i < 3; $i++) {
                $xx[$i] = $xx0[$i] - $xx0[$i + 3] * $dt_light;
            }

            // Iteration 2 for better dt_light
            $dx0p = $xx[0] - $xobs[0];
            $dx1p = $xx[1] - $xobs[1];
            $dx2p = $xx[2] - $xobs[2];
            $r2 = sqrt($dx0p*$dx0p + $dx1p*$dx1p + $dx2p*$dx2p);
            if ($r2 > 0.0) {
                $dt_light = $r2 / $c_au_per_day;
            }
            $dt_light_for_defl = $dt_light;

            // For SWIEPH: recalculate ephemeris at t - dt_light (C sweph.c:2648-2655)
            // This gives accurate position AND velocity at light-time corrected epoch
            $t_apparent = $jd_tt - $dt_light;

            // Determine ipli and ifno based on planet type
            // For numbered asteroids (> SE_AST_OFFSET), use SEI_ANYBODY and SEI_FILE_ANY_AST
            // For planetary moons (SE_PLMOON_OFFSET < ipl < SE_AST_OFFSET), special handling needed
            if ($ipl > Constants::SE_PLMOON_OFFSET && $ipl < Constants::SE_AST_OFFSET) {
                // PLANETARY MOONS: special light-time correction
                // Per C sweph.c:2604-2611, 2689: calc_center_body adds moon relative coords to planet
                // We must:
                // 1. Recalculate parent planet at t_apparent
                // 2. Recalculate moon relative coords at t_apparent
                // 3. Add them together

                // Extract parent planet from moon code
                $moonSubCode = $ipl - Constants::SE_PLMOON_OFFSET;
                $parentPlanet = (int)($moonSubCode / 100); // e.g., 5 for Jupiter
                $parentIpli = SwephConstants::PNOEXT2INT[$parentPlanet] ?? null;

                if ($parentIpli !== null) {
                    // 1. Get parent planet at t_apparent
                    $xpParent_lt = [];
                    $xpEarth_lt = [];
                    $xpSun_lt = [];
                    $xpMoon_lt = null;
                    $serrParent_lt = null;

                    $retc = \Swisseph\SwephFile\SwephPlanCalculator::calculate(
                        $t_apparent,
                        $parentIpli,
                        $parentPlanet,
                        SwephConstants::SEI_FILE_PLANET,
                        $iflag,
                        false, // NO_SAVE
                        $xpParent_lt,
                        $xpEarth_lt,
                        $xpSun_lt,
                        $xpMoon_lt,
                        $serrParent_lt
                    );

                    if ($retc >= 0 && !empty($xpParent_lt)) {
                        // 2. Get moon relative coords at t_apparent
                        $xMoonRel_lt = [];
                        $serrMoon_lt = null;

                        $retc2 = \Swisseph\SwephFile\SwephCalculator::calculate(
                            $t_apparent,
                            SwephConstants::SEI_ANYBODY,
                            $ipl,
                            SwephConstants::SEI_FILE_ANY_AST,
                            $iflag,
                            null, // No xsunb
                            false, // NO_SAVE
                            $xMoonRel_lt,
                            $serrMoon_lt
                        );

                        if ($retc2 >= 0 && !empty($xMoonRel_lt)) {
                            // 3. Add parent planet + moon relative coords
                            for ($i = 0; $i < 6; $i++) {
                                $xx[$i] = ($xpParent_lt[$i] ?? 0.0) + ($xMoonRel_lt[$i] ?? 0.0);
                            }
                        }
                    }
                }
            } elseif ($ipl > Constants::SE_AST_OFFSET) {
                $ipli = SwephConstants::SEI_ANYBODY;
                $ifno = SwephConstants::SEI_FILE_ANY_AST;

                $xx_lt = [];
                $xearth_lt = [];
                $xsun_lt = [];
                $xmoon_lt = null;
                $serr_lt = null;
                $retc = \Swisseph\SwephFile\SwephPlanCalculator::calculate(
                    $t_apparent,
                    $ipli,
                    $ipl,
                    $ifno,
                    $iflag,
                    false, // NO_SAVE
                    $xx_lt,
                    $xearth_lt,
                    $xsun_lt,
                    $xmoon_lt,
                    $serr_lt
                );

                if ($retc >= 0 && !empty($xx_lt)) {
                    for ($i = 0; $i < 6; $i++) {
                        $xx[$i] = $xx_lt[$i];
                    }
                }
            } elseif ($ipl >= Constants::SE_CHIRON && $ipl <= Constants::SE_VESTA) {
                $ipli = SwephConstants::PNOEXT2INT[$ipl] ?? SwephConstants::SEI_CHIRON;
                $ifno = SwephConstants::SEI_FILE_MAIN_AST;

                $xx_lt = [];
                $xearth_lt = [];
                $xsun_lt = [];
                $xmoon_lt = null;
                $serr_lt = null;
                $retc = \Swisseph\SwephFile\SwephPlanCalculator::calculate(
                    $t_apparent,
                    $ipli,
                    $ipl,
                    $ifno,
                    $iflag,
                    false, // NO_SAVE
                    $xx_lt,
                    $xearth_lt,
                    $xsun_lt,
                    $xmoon_lt,
                    $serr_lt
                );

                if ($retc >= 0 && !empty($xx_lt)) {
                    for ($i = 0; $i < 6; $i++) {
                        $xx[$i] = $xx_lt[$i];
                    }
                }
            } else {
                $ipli = SwephConstants::PNOEXT2INT[$ipl] ?? 0;
                $ifno = SwephConstants::SEI_FILE_PLANET;

                $xx_lt = [];
                $xearth_lt = [];
                $xsun_lt = [];
                $xmoon_lt = null;
                $serr_lt = null;
                $retc = \Swisseph\SwephFile\SwephPlanCalculator::calculate(
                    $t_apparent,
                    $ipli,
                    $ipl,
                    $ifno,
                    $iflag,
                    false, // NO_SAVE
                    $xx_lt,
                    $xearth_lt,
                    $xsun_lt,
                    $xmoon_lt,
                    $serr_lt
                );

                if ($retc >= 0 && !empty($xx_lt)) {
                    for ($i = 0; $i < 6; $i++) {
                        $xx[$i] = $xx_lt[$i];
                    }
                }
            }

            // part of daily motion resulting from change of dt
            if ($iflag & Constants::SEFLG_SPEED) {
                $xxsp[0] = $xx0[0] - $xx[0] - $xxsp[0];
                $xxsp[1] = $xx0[1] - $xx[1] - $xxsp[1];
                $xxsp[2] = $xx0[2] - $xx[2] - $xxsp[2];
            }
        }

        // SEFLG_CENTER_BODY: add center-of-body offset for outer planets (C sweph.c:2604-2611, 2693)
        // For Jupiter-Pluto, the 9n99 files contain the relative offset of the physical center
        // from the planet+moons barycenter. We add this offset to get center coordinates.
        // CRITICAL: C code calls sweph() with xsunb=NULL to get raw relative coordinates!
        $ipli_for_cob = SwephConstants::PNOEXT2INT[$ipl] ?? -1;
        if (($iflag & Constants::SEFLG_CENTER_BODY)
            && $ipli_for_cob >= SwephConstants::SEI_MARS
            && $ipli_for_cob <= SwephConstants::SEI_PLUTO
        ) {
            // Calculate iplmoon: Jupiter(5) -> 9599, Saturn(6) -> 9699, etc.
            $iplmoon_cob = $ipl * 100 + 9099;

            // Get center-of-body offset at light-time corrected epoch
            // The offset is computed at t_apparent (same as planet position)
            $t_cob = $jd_tt;
            if (!($iflag & Constants::SEFLG_TRUEPOS) && isset($dt_light_for_defl) && $dt_light_for_defl > 0) {
                $t_cob = $jd_tt - $dt_light_for_defl;
            }

            // Read center-of-body relative coordinates from 9n99 file
            // CRITICAL: Pass xsunb=null to get RAW coordinates without helio->bary conversion!
            // This matches C sweph.c:2608: sweph(t, iplmoon, ..., NULL, NO_SAVE, xcom, serr)
            $xcom = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
            $serr_cob = null;
            $retc_cob = \Swisseph\SwephFile\SwephCalculator::calculate(
                $t_cob,
                SwephConstants::SEI_ANYBODY,
                $iplmoon_cob,
                SwephConstants::SEI_FILE_ANY_AST,
                $iflag,
                null,  // xsunb = NULL - NO helio->bary conversion, gives relative offset!
                false, // NO_SAVE
                $xcom,
                $serr_cob
            );

            if (getenv('DEBUG_CENTER_BODY')) {
                error_log(sprintf("DEBUG CENTER_BODY: ipl=%d, iplmoon=%d, t_cob=%.10f", $ipl, $iplmoon_cob, $t_cob));
                error_log(sprintf("DEBUG CENTER_BODY: xcom=[%.15f,%.15f,%.15f,%.15f,%.15f,%.15f]",
                    $xcom[0], $xcom[1], $xcom[2], $xcom[3] ?? 0, $xcom[4] ?? 0, $xcom[5] ?? 0));
                error_log(sprintf("DEBUG CENTER_BODY: xx BEFORE=[%.15f,%.15f,%.15f]", $xx[0], $xx[1], $xx[2]));
            }

            if ($retc_cob >= 0 && !empty($xcom)) {
                // Add center-of-body offset to barycentric position (C sweph.c:2449-2453)
                for ($i = 0; $i <= 5; $i++) {
                    $xx[$i] += $xcom[$i];
                }
            }

            if (getenv('DEBUG_CENTER_BODY')) {
                error_log(sprintf("DEBUG CENTER_BODY: xx AFTER=[%.15f,%.15f,%.15f]", $xx[0], $xx[1], $xx[2]));
            }
        }

        // 2) Преобразование к системе отсчёта наблюдателя
        if ($iflag & Constants::SEFLG_HELCTR) {
            if ($sunb_pd) {
                for ($i = 0; $i < 6; $i++) {
                    $xx[$i] -= $sunb_pd->x[$i];
                }
            }
        } elseif (!($iflag & Constants::SEFLG_BARYCTR)) {
            if ($ipl === Constants::SE_EARTH) {
                for ($i = 0; $i < 6; $i++) {
                    $xx[$i] = 0.0;
                }
            } elseif ($earth_pd) {
                for ($i = 0; $i < 6; $i++) {
                    $xx[$i] -= $earth_pd->x[$i];
                }
            }
            // Apply light-time speed correction (C sweph.c:2720-2724)
            // "Apparent speed is also influenced by the change of dt during motion"
            if (!($iflag & Constants::SEFLG_TRUEPOS) && ($iflag & Constants::SEFLG_SPEED)) {
                $xx[3] -= $xxsp[0];
                $xx[4] -= $xxsp[1];
                $xx[5] -= $xxsp[2];
            }
        }

        // 3) Дефлексия света (если не TRUEPOS и не выключено)
        // SEFLG_NOGDEFL неявно включён для SEFLG_HELCTR или SEFLG_BARYCTR
        $do_deflect = !($iflag & Constants::SEFLG_TRUEPOS)
                    && !($iflag & Constants::SEFLG_NOGDEFL)
                    && !($iflag & Constants::SEFLG_HELCTR)
                    && !($iflag & Constants::SEFLG_BARYCTR);
        if ($do_deflect && $sunb_pd && $earth_pd) {
            $px = $xx[0];
            $py = $xx[1];
            $pz = $xx[2];
            $r_geo = sqrt($px*$px + $py*$py + $pz*$pz);
            $dt_light = ($r_geo > 0.0) ? ($r_geo / 173.144632674240) : 0.0;
            $xearth_now = [
                $earth_pd->x[0], $earth_pd->x[1], $earth_pd->x[2],
                $earth_pd->x[3], $earth_pd->x[4], $earth_pd->x[5]
            ];
            $xearth_dt = [
                $earth_pd->x[0] - $dt_light * $earth_pd->x[3],
                $earth_pd->x[1] - $dt_light * $earth_pd->x[4],
                $earth_pd->x[2] - $dt_light * $earth_pd->x[5],
                $earth_pd->x[3], $earth_pd->x[4], $earth_pd->x[5]
            ];
            $xsun_now = [
                $sunb_pd->x[0], $sunb_pd->x[1], $sunb_pd->x[2],
                $sunb_pd->x[3], $sunb_pd->x[4], $sunb_pd->x[5]
            ];
            $xsun_dt = [
                $sunb_pd->x[0] - $dt_light * $sunb_pd->x[3],
                $sunb_pd->x[1] - $dt_light * $sunb_pd->x[4],
                $sunb_pd->x[2] - $dt_light * $sunb_pd->x[5],
                $sunb_pd->x[3], $sunb_pd->x[4], $sunb_pd->x[5]
            ];
            $vec = [$xx[0], $xx[1], $xx[2], $xx[3], $xx[4], $xx[5]];
            \Swisseph\Swe\FixedStars\StarTransforms::deflectLight(
                $vec,
                $xearth_now,
                $xearth_dt,
                $xsun_now,
                $xsun_dt,
                $dt_light,
                $iflag
            );
            $n = ($iflag & Constants::SEFLG_SPEED) ? 6 : 3;
            for ($i = 0; $i < $n; $i++) {
                $xx[$i] = $vec[$i];
            }
        }

        // 4) Аберрация (если не TRUEPOS и не выключено, и не гелиоцентр/барицентр)
        $do_aberr = !($iflag & Constants::SEFLG_TRUEPOS)
                    && !($iflag & Constants::SEFLG_NOABERR)
                    && !($iflag & Constants::SEFLG_HELCTR)
                    && !($iflag & Constants::SEFLG_BARYCTR);
        if ($do_aberr && $earth_pd) {
            $dt_ab = Constants::PLAN_SPEED_INTV;
            $xe_now = [
                $earth_pd->x[0], $earth_pd->x[1], $earth_pd->x[2],
                $earth_pd->x[3], $earth_pd->x[4], $earth_pd->x[5]
            ];
            $xe_prev = [
                $earth_pd->x[0] - $dt_ab * $earth_pd->x[3],
                $earth_pd->x[1] - $dt_ab * $earth_pd->x[4],
                $earth_pd->x[2] - $dt_ab * $earth_pd->x[5],
                $earth_pd->x[3], $earth_pd->x[4], $earth_pd->x[5]
            ];
            $vec = [$xx[0], $xx[1], $xx[2], $xx[3], $xx[4], $xx[5]];
            if ($iflag & Constants::SEFLG_SPEED) {
                \Swisseph\Swe\FixedStars\StarTransforms::aberrLightEx($vec, $xe_now, $xe_prev, $dt_ab, $iflag);
            } else {
                \Swisseph\Swe\FixedStars\StarTransforms::aberrLight($vec, $xe_now);
            }
            $n = ($iflag & Constants::SEFLG_SPEED) ? 6 : 3;
            for ($i = 0; $i < $n; $i++) {
                $xx[$i] = $vec[$i];
            }
        }

        // Clear speed if not requested
        if (!($iflag & Constants::SEFLG_SPEED)) {
            for ($i = 3; $i <= 5; $i++) {
                $xx[$i] = 0.0;
            }
        }

        // 5) ICRS to J2000 frame bias
        // In C: if (!(iflag & SEFLG_ICRS) && swi_get_denum(ipli, epheflag) >= 403)
        // For SWIEPH, DE number is always >= 403, so we apply bias unless ICRS flag
        if (!($iflag & Constants::SEFLG_ICRS)) {
            $xx = \Swisseph\Bias::apply($xx, $jd_tt, $iflag, \Swisseph\Bias::MODEL_DEFAULT, false);
        }

        // 6) Прецессия к дате (если требуется)
        if (!($iflag & Constants::SEFLG_J2000)) {
            \Swisseph\Precession::precess($xx, $jd_tt, $iflag, Constants::J2000_TO_J);
            if ($iflag & Constants::SEFLG_SPEED) {
                \Swisseph\Precession::precessSpeed($xx, $jd_tt, $iflag, Constants::J2000_TO_J);
            }
        }

        // 7) app_pos_rest + выбор среза
        // For planetary moons and asteroids, use SEI_ANYBODY slot
        // Per C sweph.c:2478-2479: if (ipli > SE_PLMOON_OFFSET || ipli > SE_AST_OFFSET)
        if ($ipl > Constants::SE_PLMOON_OFFSET || $ipl > Constants::SE_AST_OFFSET) {
            $result_slot = SwephConstants::SEI_ANYBODY;
        } else {
            $result_slot = SwephConstants::PNOEXT2INT[$ipl] ?? 0;
            if ($ipl === Constants::SE_SUN) {
                $result_slot = SwephConstants::SEI_SUNBARY;
            }
        }
        $pdp = &$swed->pldat[$result_slot];
        if ($iflag & Constants::SEFLG_J2000) {
            $seps = $swed->oec2000->seps;
            $ceps = $swed->oec2000->ceps;
        } else {
            if ($swed->oec->needsUpdate($jd_tt)) {
                $swed->oec->calculate($jd_tt, $iflag);
            }
            $seps = $swed->oec->seps;
            $ceps = $swed->oec->ceps;
        }
        \Swisseph\CoordinateTransform::appPosRest($pdp, $iflag, $xx, $seps, $ceps);
        $offset = 0;
        if ($iflag & Constants::SEFLG_EQUATORIAL) {
            $offset = ($iflag & Constants::SEFLG_XYZ) ? 18 : 12;
        } else {
            $offset = ($iflag & Constants::SEFLG_XYZ) ? 6 : 0;
        }
        $out = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        for ($i = 0; $i < 6; $i++) {
            $out[$i] = $pdp->xreturn[$offset + $i];
        }
        return $out;
    }

    /**
     * Специальная обработка для SE_SUN + SEFLG_BARYCTR.
     * Эквивалент C функции app_pos_etc_sbar().
     *
     * @param float $jd_tt TT юлианская дата
     * @param int   $iflag флаги calc
     * @return array{0:float,1:float,2:float,3:float,4:float,5:float}
     */
    public static function appPosEtcSbar(float $jd_tt, int $iflag): array
    {
        $swed = \Swisseph\SwephFile\SwedState::getInstance();
        $psbdp = $swed->pldat[SwephConstants::SEI_SUNBARY] ?? null;
        $psdp = $swed->pldat[SwephConstants::SEI_EARTH] ?? null;

        if ($psbdp === null || $psdp === null) {
            return [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        }

        // Ensure obliquity is calculated (swi_check_ecliptic in C)
        if ($swed->oec->needsUpdate($jd_tt)) {
            $swed->oec->calculate($jd_tt, $iflag);
        }
        // Ensure oec2000 is initialized
        if ($swed->oec2000->needsUpdate(Constants::J2000)) {
            $swed->oec2000->calculate(Constants::J2000, $iflag);
        }

        // the conversions will be done with xx[]
        $xx = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        for ($i = 0; $i <= 5; $i++) {
            $xx[$i] = $psbdp->x[$i];
        }

        // Light-time correction
        // dt = sqrt(xx²) * AUNIT / CLIGHT / 86400.0
        // This is the light-time from SSB to Sun
        if (!($iflag & Constants::SEFLG_TRUEPOS)) {
            $r2 = $xx[0]*$xx[0] + $xx[1]*$xx[1] + $xx[2]*$xx[2];
            $r = sqrt($r2);
            // AUNIT = 1.4959787066e+11 m, CLIGHT = 299792458 m/s
            // AUNIT / CLIGHT / 86400.0 = 0.00577551833... days/AU
            $dt = $r * Constants::AUNIT / Constants::CLIGHT / 86400.0;
            for ($i = 0; $i <= 2; $i++) {
                $xx[$i] -= $dt * $xx[$i + 3];  // apparent position
            }
        }

        if (!($iflag & Constants::SEFLG_SPEED)) {
            for ($i = 3; $i <= 5; $i++) {
                $xx[$i] = 0.0;
            }
        }

        // ICRS to J2000 (frame bias)
        // In C: if (!(iflag & SEFLG_ICRS) && swi_get_denum(SEI_SUN, iflag) >= 403)
        // We apply bias for SWIEPH (DE406+)
        if (!($iflag & Constants::SEFLG_ICRS)) {
            $xx = \Swisseph\Bias::apply($xx, $psdp->teval, $iflag, \Swisseph\Bias::MODEL_DEFAULT, false);
        }

        // save J2000 coordinates for sidereal
        $xxsv = $xx;

        // Precession from J2000 to date
        if (!($iflag & Constants::SEFLG_J2000)) {
            \Swisseph\Precession::precess($xx, $psbdp->teval, $iflag, Constants::J2000_TO_J);
            if ($iflag & Constants::SEFLG_SPEED) {
                \Swisseph\Precession::precessSpeed($xx, $psbdp->teval, $iflag, Constants::J2000_TO_J);
            }
            $seps = $swed->oec->seps;
            $ceps = $swed->oec->ceps;
        } else {
            $seps = $swed->oec2000->seps;
            $ceps = $swed->oec2000->ceps;
        }

        // Use psdp (Earth slot) for xreturn storage as in C
        \Swisseph\CoordinateTransform::appPosRest($psdp, $iflag, $xx, $seps, $ceps);

        // Select output slice based on flags
        $offset = 0;
        if ($iflag & Constants::SEFLG_EQUATORIAL) {
            $offset = ($iflag & Constants::SEFLG_XYZ) ? 18 : 12;
        } else {
            $offset = ($iflag & Constants::SEFLG_XYZ) ? 6 : 0;
        }

        $out = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        for ($i = 0; $i < 6; $i++) {
            $out[$i] = $psdp->xreturn[$offset + $i];
        }
        return $out;
    }
}
