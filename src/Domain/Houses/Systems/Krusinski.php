<?php

namespace Swisseph\Domain\Houses\Systems;

use Swisseph\Coordinates;
use Swisseph\Domain\Houses\HouseSystem;
use Swisseph\Math;

/**
 * Система домов: Krusinski–Pisa–Goelzer ('U').
 * Идея: взять большой круг, проходящий через Asc и Zenith (в экваториальной системе),
 * разделить на 12 равных дуг (кусп 1 на Asc, кусп 10 на Zenith), затем для каждой точки
 * на этом круге провести меридианный круг (через полюса) и взять пересечение с эклиптикой.
 */
final class Krusinski implements HouseSystem
{
    /**
     * Возвращает куспы в радианах.
     */
    public function cusps(
        float $armc_rad,
        float $geolat_rad,
        float $eps_rad,
        float $asc_rad = NAN,
        float $mc_rad = NAN
    ): array {
        // Входные точки в экваториальных координатах
        // Asc: известна эклиптическая долгота asc_rad, широта 0
        if (!is_finite($asc_rad)) {
            return array_fill(0, 13, 0.0);
        }
        [$asc_ra, $asc_dec] = Coordinates::eclipticToEquatorialRad($asc_rad, 0.0, 1.0, $eps_rad);
        // Zenith: ra = ARMC, dec = географическая широта
        $zen_ra = Math::normAngleRad($armc_rad);
        $zen_dec = $geolat_rad;

        // Единичные векторы в экваториальной декартовой СК
        $v1 = self::sphToVec($asc_ra, $asc_dec);
        $v2 = self::sphToVec($zen_ra, $zen_dec);

        // Нормаль плоскости большого круга
        $n = self::normalize(self::cross($v1, $v2));
        // Если точки почти коллинеарны, отступаем к Equal от Asc
        if (self::norm($n) < 1e-12) {
            $eq = new Equal();
            return $eq->cusps($armc_rad, $geolat_rad, $eps_rad, $asc_rad, $mc_rad);
        }
        // Базис на круге: u направлен на Asc, w = n × u
        $u = self::normalize($v1);
        $w = self::normalize(self::cross($n, $u));

        // Подбор ориентации так, чтобы точка t=9*30° (~270°) была ближе к Zenith
        $step = Math::PI / 6.0; // 30°
        $t_candidate = 9 * $step;
        $p_fwd = self::onCircle($u, $w, +$t_candidate);
        $p_bwd = self::onCircle($u, $w, -$t_candidate);
        $sgn = (self::angleBetween($p_fwd, $v2) <= self::angleBetween($p_bwd, $v2)) ? +1.0 : -1.0;

        $cusps = array_fill(0, 13, 0.0);
        for ($i = 1; $i <= 12; $i++) {
            $t = $sgn * ($i - 1) * $step;
            $p = self::onCircle($u, $w, $t);
            // Экваториальные сферические координаты
            [$ra, $dec] = self::vecToSph($p);
            // Проекция меридианным кругом на эклиптику: найти λ по RA=α
            // tan(α) = cos(ε) * tan(λ) => λ = atan2(sinα / cosε, cosα)
            $alpha = $ra;
            $cos_eps = cos($eps_rad);
            $lon = atan2(sin($alpha) / $cos_eps, cos($alpha));
            if ($lon < 0) {
                $lon += Math::TWO_PI;
            }
            $cusps[$i] = $lon;
        }
        return $cusps;
    }

    private static function sphToVec(float $ra, float $dec): array
    {
        $cd = cos($dec);
        return [
            $cd * cos($ra),
            $cd * sin($ra),
            sin($dec),
        ];
    }

    private static function vecToSph(array $v): array
    {
        $x = $v[0]; $y = $v[1]; $z = $v[2];
        $ra = atan2($y, $x);
        if ($ra < 0) { $ra += Math::TWO_PI; }
        $rxy = sqrt($x*$x + $y*$y);
        $dec = atan2($z, max($rxy, 1e-18));
        return [$ra, $dec];
    }

    private static function cross(array $a, array $b): array
    {
        return [
            $a[1]*$b[2] - $a[2]*$b[1],
            $a[2]*$b[0] - $a[0]*$b[2],
            $a[0]*$b[1] - $a[1]*$b[0],
        ];
    }

    private static function norm(array $v): float
    {
        return sqrt($v[0]*$v[0] + $v[1]*$v[1] + $v[2]*$v[2]);
    }

    private static function normalize(array $v): array
    {
        $n = self::norm($v);
        if ($n < 1e-18) { return [0.0, 0.0, 0.0]; }
        return [$v[0]/$n, $v[1]/$n, $v[2]/$n];
    }

    private static function onCircle(array $u, array $w, float $t): array
    {
        return self::normalize([
            cos($t) * $u[0] + sin($t) * $w[0],
            cos($t) * $u[1] + sin($t) * $w[1],
            cos($t) * $u[2] + sin($t) * $w[2],
        ]);
    }

    private static function angleBetween(array $a, array $b): float
    {
        $dot = $a[0]*$b[0] + $a[1]*$b[1] + $a[2]*$b[2];
        $na = self::norm($a); $nb = self::norm($b);
        $c = max(-1.0, min(1.0, ($na*$nb > 0) ? $dot/($na*$nb) : 1.0));
        return acos($c);
    }
}
