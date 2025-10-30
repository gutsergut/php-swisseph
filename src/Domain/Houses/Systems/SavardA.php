<?php

namespace Swisseph\Domain\Houses\Systems;

use Swisseph\Domain\Houses\HouseSystem;
use Swisseph\Houses;
use Swisseph\Math;

/**
 * Savard-A houses ('J') — точный порт куспов из swehouse.c (case 'J').
 *
 * Реализация следует описанию в CalcH/case 'J' (Savard's supposed Albategnius houses):
 * - строятся дуги xs1 = asin(sin(2*fi/3)/sin(fi)), xs2 = asin(sin(fi/3)/sin(fi))
 * - затем xh1 = atan(tan(xs1)/cos(fi)), xh2 = atan(tan(xs2)/cos(fi))
 * - высоты полюсов fh1 = asin(sin(fi)*sin(90-xs1)) и fh2 аналогично
 * - куспы 12,11,2,3 вычисляются как Asc1(th+90±xh*, fh*)
 * - оси 1,10 берутся как Asc и MC соответственно
 * - при полярных широтах возможен «разворот» осей, как в оригинале
 */
final class SavardA implements HouseSystem
{
    public function cusps(
        float $armc_rad,
        float $geolat_rad,
        float $eps_rad,
        float $asc_rad = NAN,
        float $mc_rad = NAN
    ): array {
        // Приведение к градусам для формул, совместимых с swehouse.c
        $th = Math::radToDeg(Math::normAngleRad($armc_rad));    // sidereal time (ARMC), deg
        $fi = Math::radToDeg($geolat_rad);                      // latitude, deg
        $ekl = Math::radToDeg($eps_rad);                        // obliquity, deg
        [$asc0, $mc0] = is_nan($asc_rad) || is_nan($mc_rad)
            ? Houses::ascMcFromArmc($armc_rad, $geolat_rad, $eps_rad)
            : [$asc_rad, $mc_rad];
        $asc_deg = Math::radToDeg($asc0);
        $mc_deg = Math::radToDeg($mc0);

        $sine = self::sind($ekl); // sin(eps)
        $cose = self::cosd($ekl); // cos(eps)

        // Вычисление xs1/xs2 по широте
        $sinfi = self::sind($fi);
        $cosfi = self::cosd($fi);
        if (abs($fi) < 1e-12) {
            // предельный случай экватора в точности как в C-коде
            $xs2 = self::asind(1.0 / 3.0);
            $xs1 = self::asind(2.0 / 3.0);
        } else {
            $xs2 = self::asind(self::sind($fi / 3.0) / $sinfi);
            $xs1 = self::asind(self::sind(2.0 * $fi / 3.0) / $sinfi);
        }

        // xh* = atan(tan(xs*) / cos(fi))
        if (abs($cosfi) < 1e-18) {
            $xh1 = ($fi > 0) ? 90.0 : 270.0;
            $xh2 = $xh1;
        } else {
            $xh1 = self::atand(self::tand($xs1) / $cosfi);
            $xh2 = self::atand(self::tand($xs2) / $cosfi);
        }

        // fh* = asin( sin(fi) * sin(90 - xs*) ) = asin( sin(fi) * cos(xs*) )
        $fh1 = self::asind($sinfi * self::cosd($xs1));
        $fh2 = self::asind($sinfi * self::cosd($xs2));

        // Куспы, как Asc1(th + 90 ± xh*, fh*)
        $cusp = array_fill(0, 13, 0.0);
        $cusp[1] = Math::degToRad(Math::normAngleDeg($asc_deg));
        $cusp[10] = Math::degToRad(Math::normAngleDeg($mc_deg));
        $c12 = self::asc1($th + 90.0 - $xh2, $fh2, $sine, $cose);
        $c11 = self::asc1($th + 90.0 - $xh1, $fh1, $sine, $cose);
        $c2  = self::asc1($th + 90.0 + $xh2, $fh2, $sine, $cose);
        $c3  = self::asc1($th + 90.0 + $xh1, $fh1, $sine, $cose);
        $cusp[12] = Math::degToRad($c12);
        $cusp[11] = Math::degToRad($c11);
        $cusp[2]  = Math::degToRad($c2);
        $cusp[3]  = Math::degToRad($c3);

        // Полярный разворот как в SWE: внутри полярного круга и если AC «позади» MC
        if (abs($fi) >= 90.0 - $ekl) {
            $acmc = self::difdeg2n($asc_deg, $mc_deg);
            if ($acmc < 0) {
                $asc_deg = Math::normAngleDeg($asc_deg + 180.0);
                $mc_deg = Math::normAngleDeg($mc_deg + 180.0);
                $cusp[1] = Math::degToRad($asc_deg);
                $cusp[10] = Math::degToRad($mc_deg);
                for ($i = 1; $i <= 12; $i++) {
                    if ($i >= 4 && $i < 10) {
                        continue;
                    }
                    if ($i === 1 || $i === 10) {
                        continue;
                    }
                    $cusp[$i] = Math::degToRad(Math::normAngleDeg(Math::radToDeg($cusp[$i]) + 180.0));
                }
            }
        }

        return $cusp;
    }

    // ---- Вспомогательные функции в градусах (порт из swehouse.c) ----
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
        return Math::radToDeg(asin(max(-1.0, min(1.0, $x))));
    }

    private static function atand(float $x): float
    {
        return Math::radToDeg(atan($x));
    }
    private static function norm(float $d): float
    {
        while ($d < 0) {
            $d += 360.0;
        }
        while ($d >= 360.0) {
            $d -= 360.0;
        }
        return $d;
    }
    private static function difdeg2n(float $a, float $b): float
    {
        $d = $a - $b;
        while ($d > 180.0) {
            $d -= 360.0;
        }
        while ($d <= -180.0) {
            $d += 360.0;
        }
        return $d;
    }

    // Полный порт Asc1/Asc2 (в градусах), совместимый с реализацией в Sunshine
    private static function asc1(float $x1, float $f, float $sine, float $cose): float
    {
        $x1 = self::norm($x1);
        $n = (int) floor($x1 / 90.0) + 1; // квартал 1..4
        if (abs(90.0 - $f) < 1e-12) {
            return 180.0; // северный полюс
        }
        if (abs(90.0 + $f) < 1e-12) {
            return 0.0;   // южный полюс
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
        // нормализация и фиксы округления
        $ass = self::norm($ass);
        foreach ([90.0, 180.0, 270.0, 360.0] as $fix) {
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
}
