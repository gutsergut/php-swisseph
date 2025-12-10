<?php

namespace Swisseph\Swe\Planets;

use Swisseph\Constants;

class Vsop87Strategy implements EphemerisStrategy
{
    /** @var array<int, \Swisseph\Domain\Vsop87\VsopPlanetModel> Кэш загруженных моделей планет */
    private static array $modelCache = [];

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
        // dirname(__DIR__, 3) от src/Swe/Planets/ дает php-swisseph/
        $base = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'vsop87';
        $planetDir = null;
        $planetName = '';

        switch ($ipl) {
            case Constants::SE_MERCURY:
                $planetDir = $base . DIRECTORY_SEPARATOR . 'mercury';
                $planetName = 'Mercury';
                break;
            case Constants::SE_VENUS:
                $planetDir = $base . DIRECTORY_SEPARATOR . 'venus';
                $planetName = 'Venus';
                break;
            case Constants::SE_MARS:
                $planetDir = $base . DIRECTORY_SEPARATOR . 'mars';
                $planetName = 'Mars';
                break;
            case Constants::SE_JUPITER:
                $planetDir = $base . DIRECTORY_SEPARATOR . 'jupiter';
                $planetName = 'Jupiter';
                break;
            case Constants::SE_SATURN:
                $planetDir = $base . DIRECTORY_SEPARATOR . 'saturn';
                $planetName = 'Saturn';
                break;
            case Constants::SE_URANUS:
                $planetDir = $base . DIRECTORY_SEPARATOR . 'uranus';
                $planetName = 'Uranus';
                break;
            case Constants::SE_NEPTUNE:
                $planetDir = $base . DIRECTORY_SEPARATOR . 'neptune';
                $planetName = 'Neptune';
                break;
            case Constants::SE_PLUTO:
                // VSOP87 не включает Pluto (нет аналитического решения)
                return StrategyResult::err('VSOP87 does not support Pluto (use SWIEPH)', Constants::SE_ERR);
            default:
                return StrategyResult::err('Unsupported planet for VSOP87', Constants::SE_ERR);
        }

        // Все данные теперь доступны - проверка не нужна

        // Загружаем модель с кэшированием
        if (!isset(self::$modelCache[$ipl])) {
            $loader = new \Swisseph\Domain\Vsop87\VsopSegmentedLoader();
            self::$modelCache[$ipl] = $loader->loadPlanet($planetDir);
        }
        $model = self::$modelCache[$ipl];

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

        // VSOP87D возвращает гелиоцентрические ЭКЛИПТИЧЕСКИЕ координаты J2000
        // Swiss Ephemeris требует барицентрические ЭКВАТОРИАЛЬНЫЕ J2000
        // Порядок трансформации:
        // 1. Конвертируем VSOP87 helio ecliptic → helio equatorial (rotation)
        // 2. Добавляем SunBary (который уже в equatorial) → bary equatorial

        // Obliquity J2000.0 = 23.4392911° = 0.40909280422232897 rad
        $eps_j2000 = 0.40909280422232897;
        $seps = sin($eps_j2000);
        $ceps = cos($eps_j2000);

        // 1. Ecliptic → Equatorial rotation для гелиоцентрических координат VSOP87
        // Формула: x_eq = x_ecl, y_eq = y*cos(eps) - z*sin(eps), z_eq = y*sin(eps) + z*cos(eps)
        $xh_eq = $xh;
        $yh_eq = $yh * $ceps - $zh * $seps;
        $zh_eq = $yh * $seps + $zh * $ceps;

        // 2. Helio (equatorial) + SunBary (equatorial) → Bary (equatorial)
        $xpret = [
            $xh_eq + $sunb_pd->x[0],
            $yh_eq + $sunb_pd->x[1],
            $zh_eq + $sunb_pd->x[2],
            0.0,  // speeds computed below
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

        // Ecliptic → Equatorial rotation для t+dt и t-dt
        $xh_p_eq = $xh_p;
        $yh_p_eq = $yh_p * $ceps - $zh_p * $seps;
        $zh_p_eq = $yh_p * $seps + $zh_p * $ceps;

        $xh_m_eq = $xh_m;
        $yh_m_eq = $yh_m * $ceps - $zh_m * $seps;
        $zh_m_eq = $yh_m * $seps + $zh_m * $ceps;

        // Helio (equatorial) + SunBary (equatorial) → Bary (equatorial)
        $xb_p = $xh_p_eq + $xps_plus[0];
        $yb_p = $yh_p_eq + $xps_plus[1];
        $zb_p = $zh_p_eq + $xps_plus[2];

        $xb_m = $xh_m_eq + $xps_minus[0];
        $yb_m = $yh_m_eq + $xps_minus[1];
        $zb_m = $zh_m_eq + $xps_minus[2];

        // Central difference velocity (in equatorial J2000)
        $xpret[3] = ($xb_p - $xb_m) / (2.0 * $dt);
        $xpret[4] = ($yb_p - $yb_m) / (2.0 * $dt);
        $xpret[5] = ($zb_p - $zb_m) / (2.0 * $dt);

        // Пайплайн полного видимого результата
        $final = PlanetApparentPipeline::computeFinal($jd_tt, $ipl, $iflag, $xpret);
        return StrategyResult::okFinal($final);
    }
}
