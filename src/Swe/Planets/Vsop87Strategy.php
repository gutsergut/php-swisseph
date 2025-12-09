<?php

namespace Swisseph\Swe\Planets;

use Swisseph\Constants;

class Vsop87Strategy implements EphemerisStrategy
{
    public function supports(int $ipl, int $iflag): bool
    {
        return (bool)($iflag & Constants::SEFLG_VSOP87)
            && $ipl !== Constants::SE_SUN
            && $ipl !== Constants::SE_MOON
            && $ipl !== Constants::SE_EARTH;
    }

    public function compute(float $jd_tt, int $ipl, int $iflag): StrategyResult
    {
        // Маппинг планет на директории с VSOP87 данными
        $base = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'vsop87';
        $planetDir = null;
        $planetName = '';

        switch ($ipl) {
            case Constants::SE_MERCURY:
                $planetDir = $base . DIRECTORY_SEPARATOR . 'mercury';
                $planetName = 'Mercury';
                break;
            case Constants::SE_VENUS:
                $planetName = 'Venus';
                break;
            case Constants::SE_MARS:
                $planetName = 'Mars';
                break;
            case Constants::SE_JUPITER:
                $planetName = 'Jupiter';
                break;
            case Constants::SE_SATURN:
                $planetName = 'Saturn';
                break;
            case Constants::SE_URANUS:
                $planetName = 'Uranus';
                break;
            case Constants::SE_NEPTUNE:
                $planetName = 'Neptune';
                break;
            case Constants::SE_PLUTO:
                // VSOP87 не включает Pluto (нет аналитического решения)
                return StrategyResult::err('VSOP87 does not support Pluto (use SWIEPH)', Constants::SE_ERR);
            default:
                return StrategyResult::err('Unsupported planet for VSOP87', Constants::SE_ERR);
        }

        // Проверяем, что данные загружены
        if ($planetDir === null) {
            return StrategyResult::err("VSOP87 data not yet ingested for $planetName (only Mercury available)", Constants::SE_ERR);
        }

        $loader = new \Swisseph\Domain\Vsop87\VsopSegmentedLoader();
        $model = $loader->loadPlanet($planetDir);
        $calc = new \Swisseph\Domain\Vsop87\Vsop87Calculator();
        [$Ldeg, $Bdeg, $Rau] = $calc->compute($model, $jd_tt);

        $lon = \Swisseph\Math::degToRad($Ldeg);
        $lat = \Swisseph\Math::degToRad($Bdeg);
        $cl = cos($lon);
        $sl = sin($lon);
        $cb = cos($lat);
        $sb = sin($lat);
        $xh = $Rau * $cb * $cl;
        $yh = $Rau * $cb * $sl;
        $zh = $Rau * $sb;

        // Подготовим Earth/SunBary в SwedState
        if (!isset(\Swisseph\SwephFile\SwephConstants::PNOEXT2INT[Constants::SE_EARTH])) {
            return StrategyResult::err('Swiss Ephemeris Earth mapping missing', Constants::SE_ERR);
        }
        $ipliEarth = \Swisseph\SwephFile\SwephConstants::PNOEXT2INT[Constants::SE_EARTH];
        $dummy = [];
        $xperet = $xpsret = $xpmret = null;
        $retc_e = \Swisseph\SwephFile\SwephPlanCalculator::calculate(
            $jd_tt,
            $ipliEarth,
            Constants::SE_EARTH,
            \Swisseph\SwephFile\SwephConstants::SEI_FILE_PLANET,
            $iflag,
            true,
            $dummy,
            $xperet,
            $xpsret,
            $xpmret,
            $serr
        );
        if ($retc_e < 0) {
            return StrategyResult::err($serr ?? 'Earth ephemeris error', Constants::SE_ERR);
        }

        $swed_plan = \Swisseph\SwephFile\SwedState::getInstance();
        $earth_pd = $swed_plan->pldat[\Swisseph\SwephFile\SwephConstants::SEI_EARTH] ?? null;
        $sunb_pd  = $swed_plan->pldat[\Swisseph\SwephFile\SwephConstants::SEI_SUNBARY] ?? null;

        // Проверяем, что SunBary заполнен (teval установлен или x[0..2] ненулевые)
        $sunbFilled = $sunb_pd && ($sunb_pd->teval !== 0.0 || $sunb_pd->x[0] !== 0.0 || $sunb_pd->x[1] !== 0.0 || $sunb_pd->x[2] !== 0.0);
        if (!$sunbFilled) {
            return StrategyResult::err('Sun barycenter not computed', Constants::SE_ERR);
        }

        $xpret = [
            $xh + $sunb_pd->x[0],
            $yh + $sunb_pd->x[1],
            $zh + $sunb_pd->x[2],
            0.0,
            0.0,
            0.0,
        ];

        // Скорости (всегда), центральная разность, SunBary t±dt
        $dt = Constants::PLAN_SPEED_INTV;
        [$Ldeg_p, $Bdeg_p, $Rau_p] = $calc->compute($model, $jd_tt + $dt);
        [$Ldeg_m, $Bdeg_m, $Rau_m] = $calc->compute($model, $jd_tt - $dt);
        $lon_p = \Swisseph\Math::degToRad($Ldeg_p);
        $lat_p = \Swisseph\Math::degToRad($Bdeg_p);
        $lon_m = \Swisseph\Math::degToRad($Ldeg_m);
        $lat_m = \Swisseph\Math::degToRad($Bdeg_m);
        $cl_p = cos($lon_p);
        $sl_p = sin($lon_p);
        $cb_p = cos($lat_p);
        $sb_p = sin($lat_p);
        $cl_m = cos($lon_m);
        $sl_m = sin($lon_m);
        $cb_m = cos($lat_m);
        $sb_m = sin($lat_m);
        $xh_p = $Rau_p * $cb_p * $cl_p;
        $yh_p = $Rau_p * $cb_p * $sl_p;
        $zh_p = $Rau_p * $sb_p;
        $xh_m = $Rau_m * $cb_m * $cl_m;
        $yh_m = $Rau_m * $cb_m * $sl_m;
        $zh_m = $Rau_m * $sb_m;
        // Инициализируем массивы для получения SunBary позиций
        $xps_plus = array_fill(0, 6, 0.0);
        $xps_minus = array_fill(0, 6, 0.0);
        $ipliSunb = \Swisseph\SwephFile\SwephConstants::SEI_SUNBARY;
        $ret_sp = \Swisseph\SwephFile\SwephPlanCalculator::calculate(
            $jd_tt + $dt,
            $ipliSunb,
            Constants::SE_SUN,
            \Swisseph\SwephFile\SwephConstants::SEI_FILE_PLANET,
            $iflag,
            false,
            $dummy,
            $dummy,
            $xps_plus,
            $dummy,
            $serr
        );
        $ret_sm = \Swisseph\SwephFile\SwephPlanCalculator::calculate(
            $jd_tt - $dt,
            $ipliSunb,
            Constants::SE_SUN,
            \Swisseph\SwephFile\SwephConstants::SEI_FILE_PLANET,
            $iflag,
            false,
            $dummy,
            $dummy,
            $xps_minus,
            $dummy,
            $serr
        );
        if ($ret_sp < 0 || $ret_sm < 0) {
            return StrategyResult::err($serr ?? 'Sun barycenter speed calculation failed', Constants::SE_ERR);
        }
        $xb_p = $xh_p + $xps_plus[0];
        $yb_p = $yh_p + $xps_plus[1];
        $zb_p = $zh_p + $xps_plus[2];
        $xb_m = $xh_m + $xps_minus[0];
        $yb_m = $yh_m + $xps_minus[1];
        $zb_m = $zh_m + $xps_minus[2];
        $xpret[3] = ($xb_p - $xb_m) / (2.0 * $dt);
        $xpret[4] = ($yb_p - $yb_m) / (2.0 * $dt);
        $xpret[5] = ($zb_p - $zb_m) / (2.0 * $dt);

        // Пайплайн полного видимого результата
        $final = PlanetApparentPipeline::computeFinal($jd_tt, $ipl, $iflag, $xpret);
        return StrategyResult::okFinal($final);
    }
}
