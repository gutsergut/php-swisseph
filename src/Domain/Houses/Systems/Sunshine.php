<?php

namespace Swisseph\Domain\Houses\Systems;

use Swisseph\Domain\Houses\HouseSystem;
use Swisseph\Math;

/**
 * Sunshine houses (Treindl 'I' / Makransky 'i').
 * Временная реализация: безопасный fallback на Porphyry до внедрения
 * полного алгоритма (трисекция диурн./нокт. дуг Солнца и проекция).
 *
 * Примечание: Вариант выбирается на уровне фасада/реестра кодом 'I' или 'i'.
 */
final class Sunshine implements HouseSystem
{
    /**
     * Вариант алгоритма: 'I' (Treindl) или 'i' (Makransky)
     * Должен устанавливаться фасадом до вызова cusps().
     */
    private static string $variant = 'I';

    /**
     * Деклинация Солнца в градусах, нужна для Sunshine; выставляется фасадом.
     * Допустимый диапазон примерно [-24, +24] (с запасом).
     */
    private static ?float $sunDeclinationDeg = null;

    /** Установить вариант алгоритма ('I' или 'i'). */
    public static function setVariant(string $variant): void
    {
        self::$variant = ($variant === 'i') ? 'i' : 'I';
    }

    /** Установить деклинацию Солнца в градусах на текущий вызов. */
    public static function setSunDeclinationDeg(?float $decDeg): void
    {
        self::$sunDeclinationDeg = $decDeg;
    }

    public function cusps(
        float $armc_rad,
        float $geolat_rad,
        float $eps_rad,
        float $asc_rad = NAN,
        float $mc_rad = NAN
    ): array {
        // Реализация Sunshine I/i. Если нет валидной деклинации — мягкий откат на Porphyry.
        $dec = self::$sunDeclinationDeg;
        $hasValidDec = is_finite($dec ?? NAN) && $dec >= -30.0 && $dec <= 30.0;

        // Нормализуем входы и базовые оси
        if (!is_finite($asc_rad) || !is_finite($mc_rad)) {
            [$asc_rad, $mc_rad] = \Swisseph\Houses::ascMcFromArmc($armc_rad, $geolat_rad, $eps_rad);
        }
        $asc_deg = Math::normAngleDeg(Math::radToDeg($asc_rad));
        $mc_deg  = Math::normAngleDeg(Math::radToDeg($mc_rad));
        $armc_deg = Math::normAngleDeg(Math::radToDeg($armc_rad));
        $lat_deg  = Math::radToDeg($geolat_rad);
        $eps_deg  = Math::radToDeg($eps_rad);

        $cuspsDeg = array_fill(0, 13, 0.0);
        $cuspsDeg[1] = $asc_deg;
        $cuspsDeg[10] = $mc_deg;

        // Обработка полярного случая: если AC/MC «перевернуты», как в swehouse.c
        $acmc = self::difdeg2n($asc_deg, $mc_deg); // аналог swe_difdeg2n(ac, mc)
        $variant = self::$variant;
        if ($acmc < 0) {
            $asc_deg = self::norm($asc_deg + 180.0);
            $cuspsDeg[1] = $asc_deg;
            // Treindl: при «MC под горизонтом» переносим MC на север
            // (как в SWE, SUNSHINE_KEEP_MC_SOUTH=0)
            if ($variant === 'I') {
                $mc_deg = self::norm($mc_deg + 180.0);
                $cuspsDeg[10] = $mc_deg;
            }
        }
        // Противоположные оси
        $cuspsDeg[4] = self::norm($cuspsDeg[10] + 180.0);
        $cuspsDeg[7] = self::norm($cuspsDeg[1] + 180.0);

        // Если нет валидной деклинации — откат на Porphyry
        if (!$hasValidDec) {
            $porphyry = new Porphyry();
            $res = $porphyry->cusps(
                $armc_rad,
                $geolat_rad,
                $eps_rad,
                Math::degToRad($cuspsDeg[1]),
                Math::degToRad($cuspsDeg[10])
            );
            // Сброс контекста
            self::$sunDeclinationDeg = null;
            self::$variant = 'I';
            return $res;
        }

        // Вычисление Sunshine
        if ($variant === 'I') {
            $ok = $this->sunshineTreindl($armc_deg, $lat_deg, $eps_deg, $dec, $cuspsDeg);
            if (!$ok) {
                $porphyry = new Porphyry();
                $res = $porphyry->cusps(
                    $armc_rad,
                    $geolat_rad,
                    $eps_rad,
                    Math::degToRad($cuspsDeg[1]),
                    Math::degToRad($cuspsDeg[10])
                );
                self::$sunDeclinationDeg = null;
                self::$variant = 'I';
                return $res;
            }
        } else { // 'i' Makransky
            $ok = $this->sunshineMakransky($armc_deg, $lat_deg, $eps_deg, $dec, $cuspsDeg);
            if (!$ok) { // В Makransky SWE делает fallback на Porphyry при ошибках/полярном
                $porphyry = new Porphyry();
                $res = $porphyry->cusps(
                    $armc_rad,
                    $geolat_rad,
                    $eps_rad,
                    Math::degToRad($cuspsDeg[1]),
                    Math::degToRad($cuspsDeg[10])
                );
                self::$sunDeclinationDeg = null;
                self::$variant = 'I';
                return $res;
            }
        }

        // Переводим в радианы. Несчитанные куспы (5,6,8,9) достроит общий постпроцессор withOpposites().
        $out = array_fill(0, 13, 0.0);
        for ($i = 1; $i <= 12; $i++) {
            $out[$i] = Math::degToRad(self::norm($cuspsDeg[$i] ?? 0.0));
        }

        // Сброс контекста
        self::$sunDeclinationDeg = null;
        self::$variant = 'I';
        return $out;
    }

    /** Внутренние утилиты в градусах */
    private static function norm(float $deg): float
    {
        return Math::normAngleDeg($deg);
    }

    private static function sind(float $d): float
    {
        return sin(Math::degToRad($d));
    }

    private static function cosd(float $d): float
    {
        return cos(Math::degToRad($d));
    }

    private static function tand(float $d): float
    {
        return tan(Math::degToRad($d));
    }

    private static function asind(float $x): float
    {
        return Math::radToDeg(asin($x));
    }

    private static function acosd(float $x): float
    {
        return Math::radToDeg(acos($x));
    }

    private static function atand(float $x): float
    {
        return Math::radToDeg(atan($x));
    }

    private static function atan2d(float $y, float $x): float
    {
        return Math::radToDeg(atan2($y, $x));
    }

    private static function difdeg2n(float $a, float $b): float
    {
        // like swe_difdeg2n(a,b): smallest signed difference a-b into (-180,180]
        $d = $a - $b;
        while ($d > 180.0) {
            $d -= 360.0;
        }
        while ($d <= -180.0) {
            $d += 360.0;
        }
        return $d;
    }

    // Port asc1/asc2 from swehouse.c
    private static function asc1(float $x1, float $f, float $sine, float $cose): float
    {
        $x1 = self::norm($x1);
        $n = (int) floor($x1 / 90.0) + 1; // quadrant 1..4
        if (abs(90 - $f) < 1e-12) {
            return 180.0; // near north pole
        }
        if (abs(90 + $f) < 1e-12) {
            return 0.0;   // near south pole
        }
        if ($n === 1) {
            $ass = self::asc2($x1, $f, $sine, $cose);
        } elseif ($n === 2) {
            $ass = 180.0 - self::asc2(180.0 - $x1, -$f, $sine, $cose);
        } elseif ($n === 3) {
            $ass = 180.0 + self::asc2($x1 - 180.0, -$f, $sine, $cose);
        } else {
            $ass = 360.0 - self::asc2(360.0 - $x1, $f, $sine, $cose);
        }
        $ass = self::norm($ass);
        // rounding fixes
        foreach ([90.0,180.0,270.0,360.0] as $fix) {
            if (abs($ass - $fix) < 1e-12) {
                $ass = ($fix == 360.0) ? 0.0 : $fix;
                break;
            }
        }
        return $ass;
    }
    private static function asc2(float $x, float $f, float $sine, float $cose): float
    {
        // x in [0,90]
        $ass = - self::tand($f) * $sine + $cose * self::cosd($x);
        if (abs($ass) < 1e-18) {
            $ass = 0.0;
        }
        $sinx = self::sind($x);
        if (abs($sinx) < 1e-18) {
            $sinx = 0.0;
        }
        if ($sinx == 0.0) {
            $ass = ($ass < 0) ? -1e-18 : 1e-18;
        } elseif ($ass == 0.0) {
            $ass = ($sinx < 0) ? -90.0 : 90.0;
            return $ass;
        } else {
            $ass = self::atand($sinx / $ass);
        }
        if ($ass < 0) {
            $ass = 180.0 + $ass;
        }
        return $ass;
    }

    // Sunshine init: fill xh[] offsets for house positions along Sun semi-arcs
    private static function sunshineInit(float $lat, float $dec, array &$xh): bool
    {
        // ascensional difference: sin ad = tan dec tan lat
        $arg = self::tand($dec) * self::tand($lat);
        if ($arg >= 1.0) {
            $ad = 90.0 - 1e-9; // VERY_SMALL
        } elseif ($arg <= -1.0) {
            $ad = -90.0 + 1e-9;
        } else {
            $ad = self::asind($arg);
        }
        $nsa = 90.0 - $ad; // nocturnal semi-arc
        $dsa = 90.0 + $ad; // diurnal semi-arc
        $xh[2] = -2 * $nsa / 3.0;
        $xh[3] = -1 * $nsa / 3.0;
        $xh[5] = 1 * $nsa / 3.0;
        $xh[6] = 2 * $nsa / 3.0;
        $xh[8] = -2 * $dsa / 3.0;
        $xh[9] = -1 * $dsa / 3.0;
        $xh[11] = 1 * $dsa / 3.0;
        $xh[12] = 2 * $dsa / 3.0;
        if (abs($arg) >= 1.0) {
            return false; // circumpolar Sun -> error
        }
        return true;
    }

    // Makransky solution
    private function sunshineMakransky(float $ramc, float $lat, float $ecl, float $dec, array &$cusp): bool
    {
        $xh = array_fill(0, 13, 0.0);
        if (!self::sunshineInit($lat, $dec, $xh)) {
            return false;
        }
        $sinlat = self::sind($lat);
        $coslat = self::cosd($lat);
        $tanlat = self::tand($lat);
        $tandec = self::tand($dec);
        $sinecl = self::sind($ecl);
        for ($ih = 1; $ih <= 12; $ih++) {
            if ((($ih - 1) % 3) === 0) {
                // skip 1,4,7,10 already set
                continue;
            }
            $md = abs($xh[$ih]);
            $rah = ($ih <= 6)
                ? self::norm($ramc + 180.0 + $xh[$ih])
                : self::norm($ramc + $xh[$ih]);
            if ($lat < 0) {
                $rah = self::norm(180.0 + $rah); // Makransky southern handling
            }
            if (abs($md - 90.0) < 1e-12) {
                $zd = 90.0 - self::atand($sinlat * $tandec);
            } else {
                if ($md < 90.0) {
                    $a = self::atand($coslat * self::tand($md));
                } else {
                    $cl = ($coslat == 0.0) ? 1e-18 : $coslat;
                    $a = self::atand(self::tand($md - 90.0) / $cl);
                }
                $b = self::atand($tanlat * self::cosd($md));
                $c = ($ih <= 6) ? ($b + $dec) : ($b - $dec);
                $f = self::atand($sinlat * self::sind($md) * self::tand($c));
                $zd = $a + $f;
            }
            $pole = self::asind(self::sind($zd) * $sinlat);
            $q = self::asind($tandec * self::tand($pole));
            if ($ih <= 3 || $ih >= 11) {
                $w = self::norm($rah - $q);
            } else {
                $w = self::norm($rah + $q);
            }
            $cu = 0.0;
            $eps = $ecl; // alias
            if (abs($w - 90.0) < 1e-12) {
                $r = self::atand($sinecl * self::tand($pole));
                $cu = ($ih <= 3 || $ih >= 11) ? (90.0 + $r) : (90.0 - $r);
            } elseif (abs($w - 270.0) < 1e-12) {
                $r = self::atand($sinecl * self::tand($pole));
                $cu = ($ih <= 3 || $ih >= 11) ? (270.0 - $r) : (270.0 + $r);
            } else {
                $m = self::atand(abs(self::tand($pole) / self::cosd($w)));
                if ($ih <= 3 || $ih >= 11) {
                    $z = ($w > 90.0 && $w < 270.0) ? ($m - $eps) : ($m + $eps);
                } else {
                    $z = ($w > 90.0 && $w < 270.0) ? ($m + $eps) : ($m - $eps);
                }
                if (abs($z - 90.0) < 1e-12) {
                    $cu = ($w < 180.0) ? 90.0 : 270.0;
                } else {
                    $r = self::atand(abs(self::cosd($m) * self::tand($w) / self::cosd($z)));
                    if ($w < 90.0) {
                        $cu = $r;
                    } elseif ($w > 90.0 && $w < 180.0) {
                        $cu = 180.0 - $r;
                    } elseif ($w > 180.0 && $w < 270.0) {
                        $cu = 180.0 + $r;
                    } else {
                        $cu = 360.0 - $r;
                    }
                }
                if ($z > 90.0) {
                    if ($w < 90.0) {
                        $cu = 180.0 - $r;
                    } elseif ($w > 90.0 && $w < 180.0) {
                        $cu = +$r;
                    } elseif ($w > 180.0 && $w < 270.0) {
                        $cu = 360.0 - $r;
                    } else {
                        $cu = 180.0 + $r;
                    }
                }
                if ($lat < 0) {
                    $cu = self::norm($cu + 180.0);
                }
            }
            $cusp[$ih] = self::norm($cu);
        }
        return true;
    }

    // Treindl solution
    private function sunshineTreindl(float $ramc, float $lat, float $ecl, float $dec, array &$cusp): bool
    {
        $xh = array_fill(0, 13, 0.0);
        // init always true here; in SWE Treindl path does not early-fail on circumpolar
        self::sunshineInit($lat, $dec, $xh);
        $sinlat = self::sind($lat);
        $coslat = self::cosd($lat);
        $cosdec = self::cosd($dec);
        $tandec = self::tand($dec);
        $sinecl = self::sind($ecl);
        $cosecl = self::cosd($ecl);
        // find out if MC under horizon
        $mcdec = self::atand(self::sind($ramc) * self::tand($ecl));
        $mc_under = abs($lat - $mcdec) > 90.0;
        // SUNSHINE_KEEP_MC_SOUTH = 0 in SWE => if MC under horizon, invert offsets on diurnal arcs (Treindl only)
        if ($mc_under) {
            for ($i = 2; $i <= 12; $i++) {
                $xh[$i] = -$xh[$i];
            }
        }
        for ($ih = 1; $ih <= 12; $ih++) {
            if ((($ih - 1) % 3) === 0) {
                // skip 1,4,7,10
                continue;
            }
            $xhs = 2.0 * self::asind($cosdec * self::sind($xh[$ih] / 2.0));
            $cosa = $tandec * self::tand($xhs / 2.0);
            // clamp domain for acos
            if ($cosa > 1.0) {
                $cosa = 1.0;
            } elseif ($cosa < -1.0) {
                $cosa = -1.0;
            }
            $alph = self::acosd($cosa);
            if ($ih > 7) {
                $alpha2 = 180.0 - $alph;
                $b = 90.0 - $lat + $dec;
            } else {
                $alpha2 = $alph;
                $b = 90.0 - $lat - $dec;
            }
            $cosc = self::cosd($xhs) * self::cosd($b)
                + self::sind($xhs) * self::sind($b) * self::cosd($alpha2);
            if ($cosc > 1.0) {
                $cosc = 1.0;
            } elseif ($cosc < -1.0) {
                $cosc = -1.0;
            }
            $c = self::acosd($cosc);
            if ($c < 1e-6) {
                // near-degenerate; keep going
            }
            $sinzd = self::sind($xhs) * self::sind($alpha2) / max(self::sind($c), 1e-18);
            if ($sinzd > 1.0) {
                $sinzd = 1.0;
            } elseif ($sinzd < -1.0) {
                $sinzd = -1.0;
            }
            $zd = self::asind($sinzd);
            $rax = self::atand($coslat * self::tand($zd));
            $pole = self::asind($sinzd * $sinlat);
            if ($ih <= 6) {
                $pole = -$pole;
                $a = self::norm($rax + $ramc + 180.0);
            } else {
                $a = self::norm($ramc + $rax);
            }
            $hc = self::asc1($a, $pole, $sinecl, $cosecl);
            $cusp[$ih] = self::norm($hc);
        }
        if ($mc_under) {
            for ($ih = 2; $ih <= 12; $ih++) {
                if ((($ih - 1) % 3) === 0) {
                    continue;
                }
                $cusp[$ih] = self::norm(($cusp[$ih] ?? 0.0) + 180.0);
            }
        }
        return true;
    }
}
