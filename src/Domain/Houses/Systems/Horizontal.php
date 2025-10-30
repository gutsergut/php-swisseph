<?php

namespace Swisseph\Domain\Houses\Systems;

use Swisseph\Domain\Houses\HouseSystem;
use Swisseph\Houses;
use Swisseph\Math;

/**
 * Система домов: Horizontal — вертикальные круги через зенит с равными шагами по азимуту,
 * начиная с востока (Asc) через каждые 30°.
 * Реализация аналогична подходу в Campanus, но трактуется как «горизонтальные дома».
 */
final class Horizontal implements HouseSystem
{
    public function cusps(float $armc_rad, float $geolat_rad, float $eps_rad, float $asc_rad = NAN, float $mc_rad = NAN): array
    {
        // Горизонтальная система координат
        $lst = Math::normAngleRad($armc_rad);
        $phi = $geolat_rad;
        $cz = cos($phi); $sz = sin($phi);
        $cl = cos($lst); $sl = sin($lst);
        // Вектор зенита в экваториальной СК
        $zh = [$cz * $cl, $cz * $sl, $sz];
        // Единичный вектор к северному полюсу мира
        $k = [0.0, 0.0, 1.0];
        // Горизонтальный север (nh) — проекция северного полюса на горизонт
        $dot_kz = $k[2] * $zh[2];
        $nh = [$k[0] - $dot_kz * $zh[0], $k[1] - $dot_kz * $zh[1], $k[2] - $dot_kz * $zh[2]];
        $norm = sqrt($nh[0]*$nh[0] + $nh[1]*$nh[1] + $nh[2]*$nh[2]);
        if ($norm < 1e-12) {
            $nh = [ -$sl, $cl, 0.0 ];
            $norm = 1.0;
        }
        $nh = [$nh[0]/$norm, $nh[1]/$norm, $nh[2]/$norm];
        // Горизонтальный восток (eh) = zh × nh
        $eh = [
            $zh[1]*$nh[2] - $zh[2]*$nh[1],
            $zh[2]*$nh[0] - $zh[0]*$nh[2],
            $zh[0]*$nh[1] - $zh[1]*$nh[0]
        ];
        $se = sin($eps_rad); $ce = cos($eps_rad);
        $n_ecl = [0.0, -$se, $ce]; // нормаль к эклиптике в экваториальной СК

        // Вычисление Asc/MC для выбора нужной ветви долгот
        [$asc, $mc] = Houses::ascMcFromArmc($armc_rad, $geolat_rad, $eps_rad);
        $asc_deg = Math::normAngleDeg(Math::radToDeg($asc));
        $mc_deg  = Math::normAngleDeg(Math::radToDeg($mc));
        $desc_deg = Math::normAngleDeg($asc_deg + 180.0);

        // Вспомогательные функции
        $toDeg = fn($r) => Math::normAngleDeg(Math::radToDeg($r));
        $toRad = fn($d) => Math::degToRad(Math::normAngleDeg($d));
        $fwdDeg = function (float $to, float $from): float {
            $d = $to - $from;
            while ($d < 0) { $d += 360.0; }
            while ($d >= 360.0) { $d -= 360.0; }
            return $d;
        };

        $cusp = array_fill(0, 13, 0.0);
        $cusp[1] = Math::normAngleRad($asc);
        $cusp[10] = Math::normAngleRad($mc);

        // Азимуты для кустов: начиная с востока (90°), шаг 30°
        $thetas = [
            1 => 90.0,
            12 => 60.0,
            11 => 30.0,
            10 => 0.0,
            9 => 330.0,
            8 => 300.0,
            7 => 270.0,
            6 => 240.0,
            5 => 210.0,
            4 => 180.0,
            3 => 150.0,
            2 => 120.0,
        ];
        foreach ($thetas as $idx => $theta_deg) {
            if ($idx === 1 || $idx === 10) { continue; }
            $th = Math::degToRad($theta_deg);
            // Направление на горизонте с данным азимутом
            $d = [
                cos($th) * $nh[0] + sin($th) * $eh[0],
                cos($th) * $nh[1] + sin($th) * $eh[1],
                cos($th) * $nh[2] + sin($th) * $eh[2],
            ];
            // Нормаль вертикальной плоскости: n = d × zh
            $n = [
                $d[1]*$zh[2] - $d[2]*$zh[1],
                $d[2]*$zh[0] - $d[0]*$zh[2],
                $d[0]*$zh[1] - $d[1]*$zh[0],
            ];
            // Линия пересечения вертикальной плоскости с эклиптикой: l = n × n_ecl
            $l = [
                $n[1]*$n_ecl[2] - $n[2]*$n_ecl[1],
                $n[2]*$n_ecl[0] - $n[0]*$n_ecl[2],
                $n[0]*$n_ecl[1] - $n[1]*$n_ecl[0],
            ];
            // Эклиптическая долгота направления линии l
            $x = $l[0];
            $y =  $l[1]*$ce + $l[2]*$se;
            $lon = atan2($y, $x);
            if ($lon < 0) { $lon += Math::TWO_PI; }
            $lon_deg = Math::radToDeg($lon);

            // Выбор корректной ветви (чтобы дом лежал в нужном квадранте)
            $lon_op_deg = Math::normAngleDeg($lon_deg + 180.0);
            if (in_array($idx, [12,11], true)) {
                $span = $fwdDeg($mc_deg, $asc_deg);
                $d1 = $fwdDeg($lon_deg, $asc_deg);
                $use = ($d1 <= $span) ? $lon_deg : $lon_op_deg;
            } elseif (in_array($idx, [9,8], true)) {
                $span = $fwdDeg($desc_deg, $mc_deg);
                $d1 = $fwdDeg($lon_deg, $mc_deg);
                $use = ($d1 <= $span) ? $lon_deg : $lon_op_deg;
            } elseif (in_array($idx, [6,5], true)) {
                $span = $fwdDeg(Math::normAngleDeg($asc_deg + 180.0 + 180.0), $desc_deg); // IC от desc
                $d1 = $fwdDeg($lon_deg, $desc_deg);
                $use = ($d1 <= $span) ? $lon_deg : $lon_op_deg;
            } else {
                $ic_deg = Math::normAngleDeg($mc_deg + 180.0);
                $span = $fwdDeg($asc_deg, $ic_deg);
                $d1 = $fwdDeg($lon_deg, $ic_deg);
                $use = ($d1 <= $span) ? $lon_deg : $lon_op_deg;
            }
            $cusp[$idx] = $toRad($use);
        }

        // Противоположные куспы
        $cusp[7] = Math::normAngleRad($cusp[1] + Math::PI);
        $cusp[4] = Math::normAngleRad($cusp[10] + Math::PI);
        $cusp[8] = Math::normAngleRad($cusp[2] + Math::PI);
        $cusp[9] = Math::normAngleRad($cusp[3] + Math::PI);
        $cusp[5] = Math::normAngleRad($cusp[11] + Math::PI);
        $cusp[6] = Math::normAngleRad($cusp[12] + Math::PI);
        return $cusp;
    }
}
