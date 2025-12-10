<?php

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;
use Swisseph\Domain\Houses\Registry as HouseRegistry;
use Swisseph\Domain\Houses\Systems\Sunshine as SunshineSystem;
use Swisseph\Domain\Houses\Support\AscMc as HousesAscMc;
use Swisseph\Domain\Houses\Support\CuspPostprocessor as CuspPost;
use Swisseph\ErrorCodes;
use Swisseph\DeltaT;
use Swisseph\Houses;
use Swisseph\Math;
use Swisseph\Domain\Houses\Systems\Gauquelin as GauquelinSectors;

/**
 * Реализация глобальных функций домов (swe_houses*, swe_house_pos, swe_house_name) как тонких фасадов.
 */
final class HousesFunctions
{
    /**
     * Реализация swe_houses_ex2 как метода класса.
     */
    public static function housesEx2(
        float $jd_ut,
        int $iflag,
        float $geolat,
        float $geolon,
        string $hsys,
        array &$cusp,
        array &$ascmc,
        ?array &$cusp_speed = null,
        ?array &$ascmc_speed = null,
        ?string &$serr = null
    ): int {
        $serr = null;
        // Особый случай: 'i' (Sunshine/Makransky) допускает строчную букву
        $hsys_norm = ($hsys === 'i') ? 'i' : strtoupper($hsys);
        $supported = [
            'A','E','D','N','F','L','G','Q','I','i','P','K','O','C','R','W','B','V','M','H','T','S','X','U','Y','J'
        ];
        if (!in_array($hsys_norm, $supported, true)) {
            $serr = ErrorCodes::compose(
                ErrorCodes::UNSUPPORTED,
                'Only A,E,D,N,F,L,G,Q,I,i,P,K,O,C,R,W,B,V,M,H,T,S,X,U,Y,J supported at this stage'
            );
            $cusp = array_fill(0, 13, 0.0);
            $ascmc = array_fill(0, 10, 0.0);
            return Constants::SE_ERR;
        }
        // Базовые параметры и Asc/MC
        [$armc, $asc, $mc, $eps] = HousesAscMc::fromJdUt($jd_ut, $geolon, $geolat);
        // Спец-ветка для Gauquelin до попытки лукапа в реестре
        if ($hsys_norm === 'G') {
            // Гоклен: вернём границы 36 секторов в cusp[1..36]; ascmc[0..1] как обычно
            $edges = GauquelinSectors::cusps36($armc, Math::degToRad($geolat), $eps, $asc, $mc);
            $cusp = array_fill(0, 37, 0.0);
            for ($i = 1; $i <= 36; $i++) {
                $cusp[$i] = Math::normAngleDeg(Math::radToDeg($edges[$i]));
            }
            $ascmc = array_fill(0, 10, 0.0);
            $ascmc[0] = Math::normAngleDeg(Math::radToDeg($asc));
            $ascmc[1] = Math::normAngleDeg(Math::radToDeg($mc));
            // ARMC в градусах (как в SWE)
            $ascmc[2] = Math::normAngleDeg(Math::radToDeg($armc));
            // Если просят скорости — заполним нулями корректной длины
            if (is_array($cusp_speed)) {
                $cusp_speed = array_fill(0, 37, 0.0);
            }
            if (is_array($ascmc_speed)) {
                $ascmc_speed = array_fill(0, 10, 0.0);
            }
            return 0;
        }
        // Делегируем через реестр стратегий
        $reg = new HouseRegistry();
        $sys = $reg->get($hsys_norm);
        if (!$sys) {
            $serr = ErrorCodes::compose(ErrorCodes::UNSUPPORTED, "Unsupported house system code");
            $cusp = array_fill(0, 13, 0.0);
            $ascmc = array_fill(0, 10, 0.0);
            return Constants::SE_ERR;
        }
        // Подготовим контекст для Sunshine: вариант ('I' или 'i') и деклинация Солнца в ascmc[9]
        if ($hsys_norm === 'I' || $hsys_norm === 'i') {
            SunshineSystem::setVariant($hsys_norm);
        }
        $cusps_rad = $sys->cusps($armc, Math::degToRad($geolat), $eps, $asc, $mc);
        if ($hsys_norm === 'P' || $hsys_norm === 'K') {
            $sum = 0.0;
            for ($i = 1; $i <= 12; $i++) {
                $sum += abs($cusps_rad[$i]);
            }
            if ($sum === 0.0) {
                $name = ($hsys_norm === 'P') ? 'Placidus' : 'Koch';
                $serr = ErrorCodes::compose(
                    ErrorCodes::OUT_OF_RANGE,
                    $name . ' not defined for this latitude'
                );
                $cusp = array_fill(0, 13, 0.0);
                $ascmc = array_fill(0, 10, 0.0);
                return Constants::SE_ERR;
            }
        }
        if ($hsys_norm === 'R') {
            $cusps_rad = CuspPost::forceAscMc($cusps_rad, $asc, $mc);
        }
        // Add opposite cusps only for systems that don't calculate them (swehouse.c:1987)
        // APC ('Y'), Gauquelin ('G'), and Sunshine ('I'/'i') calculate all 12 cusps directly
        if ($hsys_norm !== 'G' && $hsys_norm !== 'Y' && strtoupper($hsys_norm) !== 'I') {
            $cusps_rad = CuspPost::withOpposites($cusps_rad);
        }
        // Перевод в градусы
        $cusp = array_fill(0, 13, 0.0);
        for ($i = 1; $i <= 12; $i++) {
            $cusp[$i] = Math::normAngleDeg(Math::radToDeg($cusps_rad[$i]));
        }
        $ascmc = array_fill(0, 10, 0.0);
        $ascmc[0] = Math::normAngleDeg(Math::radToDeg($asc));
        $ascmc[1] = Math::normAngleDeg(Math::radToDeg($mc));
        // ARMC в градусах (как в SWE)
        $ascmc[2] = Math::normAngleDeg(Math::radToDeg($armc));
        // Для Sunshine систем — ascmc[9] = деклинация Солнца (приблизительная оценка)
        if ($hsys_norm === 'I' || $hsys_norm === 'i') {
            // Берём λ☉ из простого солярного алгоритма по TT и считаем dec = asin(sin(eps)*sin(λ☉)).
            $jd_tt = $jd_ut + DeltaT::deltaTSecondsFromJd($jd_ut) / 86400.0;
            [$lonSun, $latSun] = \Swisseph\Sun::eclipticLonLatDist($jd_tt);
            $sinDec = sin($eps) * sin($lonSun);
            $dec = asin($sinDec);
            $ascmc[9] = Math::radToDeg($dec);
            // Также передадим в стратегию Sunshine для использования внутри (на будущее)
            SunshineSystem::setSunDeclinationDeg($ascmc[9]);
        }
        // Если просят скорости — заполним нулями корректной длины
        if (is_array($cusp_speed)) {
            $cusp_speed = array_fill(0, 13, 0.0);
        }
        if (is_array($ascmc_speed)) {
            $ascmc_speed = array_fill(0, 10, 0.0);
        }
        return 0;
    }

    /**
     * Calculate house cusps with ephemeris flags.
     * Port of swe_houses_ex() from swehouse.c:178
     *
     * This is a wrapper around housesEx2() that omits the speed calculations.
     *
     * @param float $jd_ut Julian day in UT
     * @param int $iflag Ephemeris flags (e.g., SEFLG_SIDEREAL, SEFLG_NONUT, etc.)
     * @param float $geolat Geographic latitude in degrees
     * @param float $geolon Geographic longitude in degrees
     * @param string $hsys House system code (single letter)
     * @param array &$cusp Output array for house cusps [0..12] or [0..36] for Gauquelin
     * @param array &$ascmc Output array for additional points [0..9]:
     *                      [0]=Ascendant, [1]=MC, [2]=ARMC, [3]=Vertex,
     *                      [4]=Equatorial Asc, [5]=Co-Asc Koch, [6]=Co-Asc Munkasey,
     *                      [7]=Polar Asc, [8]=reserved, [9]=reserved
     * @return int SE_OK (0) or SE_ERR (-1)
     */
    public static function housesEx(
        float $jd_ut,
        int $iflag,
        float $geolat,
        float $geolon,
        string $hsys,
        array &$cusp,
        array &$ascmc
    ): int {
        $cusp_speed = null;
        $ascmc_speed = null;
        $serr = null;
        return self::housesEx2($jd_ut, $iflag, $geolat, $geolon, $hsys, $cusp, $ascmc, $cusp_speed, $ascmc_speed, $serr);
    }

    /**
     * Обёртка для совместимости с swe_houses из С API.
     */
    public static function houses(
        float $jd_ut,
        float $geolat,
        float $geolon,
        string $hsys,
        array &$cusp,
        array &$ascmc
    ): int {
        $dummy1 = null;
        $dummy2 = null;
        $serr = null;
        return self::housesEx2($jd_ut, 0, $geolat, $geolon, $hsys, $cusp, $ascmc, $dummy1, $dummy2, $serr);
    }

    /**
     * Реализация swe_house_pos: вычисляет позицию в доме.
     * Вход: armc/eps/геоширота в градусах; xpin[0] — долгота объекта на эклиптике (в градусах).
     */
    public static function housePos(
        float $armc_deg,
        float $geolat_deg,
        float $eps_deg,
        string $hsys,
        array $xpin,
        ?string &$serr = null
    ): float {
        $serr = null;
        $hsys = ($hsys === 'i') ? 'i' : strtoupper($hsys);
        $armc = Math::degToRad(Math::normAngleDeg($armc_deg));
        $eps = Math::degToRad($eps_deg);
        [$asc, $mc] = Houses::ascMcFromArmc($armc, Math::degToRad($geolat_deg), $eps);
        if ($hsys === 'G') {
            // Позиция в секторе Гоклена: 1..36.9999, нумерация по часовой стрелке
            $edges = GauquelinSectors::cusps36($armc, Math::degToRad($geolat_deg), $eps, $asc, $mc);
            $obj_lon = Math::degToRad(Math::normAngleDeg($xpin[0] ?? 0.0));
            $obj_deg = Math::radToDeg($obj_lon);
            // Найдём, между какими границами (по часовой стрелке) находится точка
            // edges[1]..edges[36] — в радианах, переведём в градусы для удобства
            $ed = [];
            for ($i = 1; $i <= 36; $i++) {
                $ed[$i] = Math::normAngleDeg(Math::radToDeg($edges[$i]));
            }
            // Обход по часовой: сектор i — интервал [ed[i], ed[i+1]) по часовой стрелке; ed[37] = ed[1]
            $ed[37] = $ed[1];
            $clockwiseDist = function (float $from, float $to): float {
                $d = $from - $to;
                while ($d < 0) {
                    $d += 360.0;
                }
                while ($d >= 360.0) {
                    $d -= 360.0;
                }
                return $d;
            };
            for ($i = 1; $i <= 36; $i++) {
                $span = $clockwiseDist($ed[$i], $ed[$i + 1]);
                $off = $clockwiseDist($ed[$i], $obj_deg);
                if (($off >= 0 && $off < $span) || $off == 0.0) {
                    $frac = ($span > 0.0) ? ($off / $span) : 0.0;
                    return $i + $frac;
                }
            }
            return 36.9999999; // fallback
        }
        $reg = new HouseRegistry();
        $sys = $reg->get($hsys);
        if (!$sys) {
            $serr = ErrorCodes::compose(
                ErrorCodes::UNSUPPORTED,
                'Only A,E,D,N,F,L,G,Q,I,i,P,K,O,C,R,W,B,V,M,H,T,S,X,U,Y,J supported'
            );
            return 0.0;
        }
        $cusps = $sys->cusps($armc, Math::degToRad($geolat_deg), $eps, $asc, $mc);
        if ($hsys === 'K') {
            $sum = 0.0;
            for ($i = 1; $i <= 12; $i++) {
                $sum += abs($cusps[$i]);
            }
            if ($sum === 0.0) {
                $serr = ErrorCodes::compose(ErrorCodes::OUT_OF_RANGE, 'Koch not defined for this latitude');
                return 0.0;
            }
        }
        if ($hsys === 'R') {
            $cusps = CuspPost::forceAscMc($cusps, $asc, $mc);
        }
        // Add opposite cusps only for systems that don't calculate them (swehouse.c:1987)
        // APC ('Y'), Gauquelin ('G'), and Sunshine ('I'/'i') calculate all 12 cusps directly
        if ($hsys !== 'G' && $hsys !== 'Y' && strtoupper($hsys) !== 'I') {
            $cusps = CuspPost::withOpposites($cusps);
        }
        $obj_lon = Math::degToRad(Math::normAngleDeg($xpin[0] ?? 0.0));
        if ($hsys === 'E') {
            // Встроенный расчёт позиции для Equal: 30°/дом от Asc
            $d = Math::normAngleRad($obj_lon - $asc);
            $pos = $d / (Math::PI / 6.0) + 1.0;
            if ($pos > 12.0) {
                $pos -= 12.0;
            }
            return $pos;
        }
        if ($hsys === 'Y') {
            // APC ('Y') по SWE: алгоритм как Sunshine, но dsun = decl(Asc)
            // 0) Ранний возврат при точном попадании на кусп (и широта объекта = 0)
            $lon_in = Math::normAngleDeg($xpin[0] ?? 0.0);
            $lat_in = $xpin[1] ?? 0.0;
            if (abs($lat_in) < 1e-20) {
                for ($k = 1; $k <= 12; $k++) {
                    $ck = Math::normAngleDeg(Math::radToDeg($cusps[$k]));
                    $dd = $lon_in - $ck;
                    while ($dd > 180.0) {
                        $dd -= 360.0;
                    }
                    while ($dd <= -180.0) {
                        $dd += 360.0;
                    }
                    if (abs($dd) < 1e-9) {
                        return (float) $k;
                    }
                }
            }
            // Подготовка: координаты объекта в экваторе
            $obj_lon_deg = Math::normAngleDeg($xpin[0] ?? 0.0);
            $obj_lat_deg = $xpin[1] ?? 0.0;
            [$ra_rad, $de_rad] = \Swisseph\Coordinates::eclipticToEquatorialRad(
                Math::degToRad($obj_lon_deg),
                Math::degToRad($obj_lat_deg),
                1.0,
                $eps
            );
            $ra_deg = Math::radToDeg($ra_rad);
            $de_deg = Math::radToDeg($de_rad);
            // mdd/mdn в градусах с приведением к [-180,180)
            $mdd = Math::normAngleDeg($ra_deg - $armc_deg);
            $mdn = Math::normAngleDeg($mdd + 180.0);
            if ($mdd >= 180.0) {
                $mdd -= 360.0;
            }
            if ($mdn >= 180.0) {
                $mdn -= 360.0;
            }
            // dsun = decl(Asc)
            [$asc_ra, $asc_dec] = \Swisseph\Coordinates::eclipticToEquatorialRad(
                $asc,
                0.0,
                1.0,
                $eps
            );
            $dsun = Math::radToDeg($asc_dec);
            // Базовая позиция как в Regiomontanus (xp0)
            $SIN = fn(float $deg): float => sin(Math::degToRad($deg));
            $COS = fn(float $deg): float => cos(Math::degToRad($deg));
            $TAN = fn(float $deg): float => tan(Math::degToRad($deg));
            $ASIN = fn(float $x): float => Math::radToDeg(asin($x));
            $ACOS = fn(float $x): float => Math::radToDeg(acos($x));
            $ATAN = fn(float $x): float => Math::radToDeg(atan($x));
            $geolat = $geolat_deg;
            // Regiomontanus база
            $a = $TAN($geolat) * $TAN($de_deg) + $COS($mdd);
            $xp0 = Math::normAngleDeg($ATAN(-$a / $SIN($mdd)));
            if ($mdd < 0.0) {
                $xp0 += 180.0;
            }
            $xp0 = Math::normAngleDeg($xp0);
            // Над горизонтом?
            $sinad = $TAN($de_deg) * $TAN($geolat);
            $a2 = $sinad + $COS($mdd);
            $is_above = ($a2 >= 0.0);
            // Высота ARMC над горизонтом
            $harmc = 90.0 - abs($geolat);
            // Положение относительно полуденного меридиана
            $darmc = Math::normAngleDeg($xp0 - 270.0);
            $is_west = false;
            if ($darmc > 180.0) {
                $is_west = true;
                $darmc = 360.0 - $darmc;
            }
            // Полу-дуговые величины от dsun
            $sinad2 = $TAN($dsun) * $TAN($geolat);
            if ($sinad2 >= 1.0) {
                $ad = 90.0;
            } elseif ($sinad2 <= -1.0) {
                $ad = -90.0;
            } else {
                $ad = $ASIN($sinad2);
            }
            $sad = 90.0 + $ad; // дневная полу-дуга
            $san = 90.0 - $ad; // ночная полу-дуга
            // Особые случаи циркумполярности
            if ($sad == 0.0 && $is_above) {
                $xp0 = 270.0;
            } elseif ($san == 0.0 && !$is_above) {
                $xp0 = 90.0;
            } else {
                $sa = $sad;
                $ds = $dsun;
                $ddarmc = $darmc;
                $west = $is_west;
                if (!$is_above) {
                    $ds = -$ds;
                    $sa = $san;
                    $ddarmc = 180.0 - $ddarmc;
                    $west = !$west;
                }
                // Длина линии позиции между южной точкой и экватором
                $len = $ACOS($COS($harmc) * $COS($ddarmc));
                if ($len < 1e-12) {
                    $len = 1e-12;
                }
                // sin угла между линией позиции и экватором
                $sinpsi = $SIN($harmc) / $SIN($len);
                if ($sinpsi > 1.0) $sinpsi = 1.0;
                if ($sinpsi < -1.0) $sinpsi = -1.0;
                // меридианное расстояние пересечения линии позиции с солнечной полу-дугой
                $y = $SIN($ds) / $sinpsi;
                if ($y > 1.0) {
                    $yy = 90.0 - 1e-9;
                } elseif ($y < -1.0) {
                    $yy = -(90.0 - 1e-9);
                } else {
                    $yy = $ASIN($y);
                }
                $d = $ACOS($COS($yy) / $COS($ds));
                if ($ds < 0.0) {
                    $d = -$d;
                }
                if ($geolat < 0.0) {
                    $d = -$d;
                }
                $ddarmc += $d;
                if ($west) {
                    $xp0 = 270.0 - ($ddarmc / $sa) * 90.0;
                } else {
                    $xp0 = 270.0 + ($ddarmc / $sa) * 90.0;
                }
                if (!$is_above) {
                    $xp0 = Math::normAngleDeg($xp0 + 180.0);
                }
            }
            // Миллисекунда дуги внутрь дома
            $xp0 = Math::normAngleDeg($xp0 + (1.0 / 3600000.0));
            $pos = $xp0 / 30.0 + 1.0;
            if ($pos > 12.0) {
                $pos -= 12.0;
            }
            return $pos;
        }
        if ($hsys === 'J') {
            // Savard-A ('J') по SWE: prime vertical интерполяция
            // 1) Снаппинг к осям (эмулирует ранний выход swe_house_pos при точном попадании на кусп)
            // Также: если вход — ровно кусп дома и широта объекта = 0, вернуть номер дома
            $lon_in = Math::normAngleDeg($xpin[0] ?? 0.0);
            $lat_in = $xpin[1] ?? 0.0;
            if (abs($lat_in) < 1e-20) {
                for ($k = 1; $k <= 12; $k++) {
                    $ck = Math::normAngleDeg(Math::radToDeg($cusps[$k]));
                    $dd = $lon_in - $ck;
                    while ($dd > 180.0) {
                        $dd -= 360.0;
                    }
                    while ($dd <= -180.0) {
                        $dd += 360.0;
                    }
                    if (abs($dd) < 1e-9) {
                        return (float) $k;
                    }
                }
            }
            $epsAxis = Math::degToRad(1e-7);
            $wrapDist = function (float $a, float $b): float {
                $d = abs(Math::normAngleRad($a - $b));
                if ($d > Math::PI) {
                    $d = Math::TWO_PI - $d;
                }
                return $d;
            };
            $desc = Math::normAngleRad($asc + Math::PI);
            $ic   = Math::normAngleRad($mc  + Math::PI);
            if ($wrapDist($obj_lon, $asc) < $epsAxis) {
                return 1.0;
            }
            if ($wrapDist($obj_lon, $mc) < $epsAxis) {
                return 10.0;
            }
            if ($wrapDist($obj_lon, $desc) < $epsAxis) {
                return 7.0;
            }
            if ($wrapDist($obj_lon, $ic) < $epsAxis) {
                return 4.0;
            }

            // 2) hcusp на prime vertical
            $geolat = $geolat_deg;
            $sinfi = sin(Math::degToRad($geolat));
            if (abs($geolat) < 1e-12) {
                $xs2 = asin(1.0 / 3.0);
                $xs1 = asin(2.0 / 3.0);
            } else {
                $xs2 = asin(sin(Math::degToRad($geolat / 3.0)) / $sinfi);
                $xs1 = asin(sin(Math::degToRad(2.0 * $geolat / 3.0)) / $sinfi);
            }
            $xs2d = Math::radToDeg($xs2);
            $xs1d = Math::radToDeg($xs1);
            $hc = array_fill(0, 13, 0.0);
            $hc[1] = 0.0;
            $hc[2] = $xs2d;
            $hc[3] = $xs1d;
            $hc[4] = 90.0;
            $hc[5] = 180.0 - $xs1d;
            $hc[6] = 180.0 - $xs2d;
            $hc[7] = 180.0;
            $hc[8] = 180.0 + $xs2d;
            $hc[9] = 180.0 + $xs1d;
            $hc[10] = 270.0;
            $hc[11] = 360.0 - $xs1d;
            $hc[12] = 360.0 - $xs2d;

            // 3) Перевод объекта на prime vertical: a = norm( (RA−ARMC)−90 ) → rotate by −geolat
            $obj_lon_deg = Math::normAngleDeg($xpin[0] ?? 0.0);
            $obj_lat_deg = $xpin[1] ?? 0.0;
            [$ra, $dec] = \Swisseph\Coordinates::eclipticToEquatorialRad(
                Math::degToRad($obj_lon_deg),
                Math::degToRad($obj_lat_deg),
                1.0,
                $eps
            );
            $mdd = Math::normAngleDeg(Math::radToDeg($ra) - $armc_deg);
            if ($mdd >= 180.0) {
                $mdd -= 360.0;
            }
            $a_eq = Math::normAngleDeg($mdd - 90.0);
            [$pv_lon, $pv_lat] = \Swisseph\Coordinates::equatorialToEclipticRad(
                Math::degToRad($a_eq),
                0.0,
                1.0,
                Math::degToRad($geolat)
            );
            $a = Math::normAngleDeg(Math::radToDeg($pv_lon));

            // 4) Определение разметки (ретроградна или нет) и интерполяция внутри интервала
            $difdeg2n = function (float $x, float $y): float {
                $d = $x - $y;
                while ($d > 180.0) {
                    $d -= 360.0;
                }
                while ($d <= -180.0) {
                    $d += 360.0;
                }
                return $d;
            };
            $direct = ($difdeg2n($hc[6], $hc[1]) > 0);
            $i = 1;
            $c1 = 0.0;
            $c2 = 0.0;
            $d = 0.0;
            if ($direct) {
                $d = Math::normAngleDeg($a - $hc[1]);
                for ($i = 1; $i <= 12; $i++) {
                    $j = $i + 1;
                    $c2 = ($j > 12) ? 360.0 : Math::normAngleDeg($hc[$j] - $hc[1]);
                    if ($d < $c2) {
                        break;
                    }
                }
                $c1 = Math::normAngleDeg($hc[$i] - $hc[1]);
            } else {
                $d = Math::normAngleDeg($hc[1] - $a);
                for ($i = 1; $i <= 12; $i++) {
                    $j = $i + 1;
                    $c2 = ($j > 12) ? 360.0 : Math::normAngleDeg($hc[1] - $hc[$j]);
                    if ($d < $c2) {
                        break;
                    }
                }
                $c1 = Math::normAngleDeg($hc[1] - $hc[$i]);
            }
            $hsize = $c2 - $c1;
            if (abs($hsize) < 1e-18) {
                return (float) $i;
            }
            return $i + ($d - $c1) / $hsize;
        }
        return Houses::positionFromCusps($asc, $cusps, $obj_lon);
    }

    public static function houseName(string $hsys): string
    {
        $key = ($hsys === 'i') ? 'i' : strtoupper($hsys);
        return match ($key) {
            'A' => 'Equal',
            'E' => 'Equal (Asc-based 30°)',
            'D' => 'Equal (MC-based 30°)',
            'N' => 'equal/1=Aries',
            'P' => 'Placidus',
            'K' => 'Koch',
            'O' => 'Porphyry',
            'C' => 'Campanus',
            'R' => 'Regiomontanus',
            'W' => 'Whole Sign',
            'B' => 'Alcabitius',
            'V' => 'Vehlow (Equal shifted -15°)',
            'M' => 'Morinus',
            'H' => 'Horizontal',
            'T' => 'Topocentric (Polich-Page)',
            'S' => 'Sripati (Porphyry midpoints)',
            'X' => 'axial rotation system/Meridian houses',
            'U' => 'Krusinski-Pisa-Goelzer',
            'F' => 'Carter "Poli-Equatorial"',
            'L' => 'Pullen SD (sinusoidal delta)',
            'Q' => 'Pullen SR (sinusoidal ratio)',
            'G' => 'Gauquelin sectors (36, clockwise)',
            'I' => 'Sunshine',
            'i' => 'Sunshine/alt. (Makransky)',
            'Y' => 'APC houses',
            'J' => 'Savard-A',
            default => 'Unsupported',
        };
    }
}
