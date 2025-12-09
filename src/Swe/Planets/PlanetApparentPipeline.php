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

        // 1) Light-time (две итерации) — только для геоцентрического пути
        if (!($iflag & Constants::SEFLG_HELCTR) && !($iflag & Constants::SEFLG_BARYCTR) && $ipl !== Constants::SE_EARTH && $earth_pd) {
            $c_au_per_day = 173.144632674240; // скорость света в AU/day
            // первая итерация
            $px = $xx[0] - $earth_pd->x[0];
            $py = $xx[1] - $earth_pd->x[1];
            $pz = $xx[2] - $earth_pd->x[2];
            $r  = sqrt($px*$px + $py*$py + $pz*$pz);
            $dt_light = ($r > 0.0) ? ($r / $c_au_per_day) : 0.0;
            for ($i = 0; $i < 3; $i++) {
                $xx[$i] -= $xx[$i + 3] * $dt_light;
            }
            // вторая итерация
            $px = $xx[0] - $earth_pd->x[0];
            $py = $xx[1] - $earth_pd->x[1];
            $pz = $xx[2] - $earth_pd->x[2];
            $r2 = sqrt($px*$px + $py*$py + $pz*$pz);
            if ($r2 > 0.0) {
                $dt2 = $r2 / $c_au_per_day;
                $corr = $dt2 - $dt_light;
                for ($i = 0; $i < 3; $i++) {
                    $xx[$i] -= $xx[$i + 3] * $corr;
                }
                $dt_light = $dt2;
            }
        }

        // 2) Гео/гелио/барио выбор
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
        }

        // 3) Дефлексия света (если не TRUEPOS и не выключено)
        $do_deflect = !($iflag & Constants::SEFLG_TRUEPOS) && !($iflag & Constants::SEFLG_NOGDEFL);
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

        // 4) Аберрация (если не TRUEPOS и не выключено)
        $do_aberr = !($iflag & Constants::SEFLG_TRUEPOS) && !($iflag & Constants::SEFLG_NOABERR);
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

        // 5) Прецессия к дате (если требуется)
        if (!($iflag & Constants::SEFLG_J2000)) {
            \Swisseph\Precession::precess($xx, $jd_tt, $iflag, Constants::J2000_TO_J);
            if ($iflag & Constants::SEFLG_SPEED) {
                \Swisseph\Precession::precessSpeed($xx, $jd_tt, $iflag, Constants::J2000_TO_J);
            }
        }

        // 6) app_pos_rest + выбор среза
        $result_slot = SwephConstants::PNOEXT2INT[$ipl] ?? 0;
        if ($ipl === Constants::SE_SUN) {
            $result_slot = SwephConstants::SEI_SUNBARY;
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
}
