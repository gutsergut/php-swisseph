<?php

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;
use Swisseph\ErrorCodes;
use Swisseph\Horizontal;
use Swisseph\Math;

final class RiseSetFunctions
{
    public static function riseTrans(
        float $jd_ut,
        int $ipl,
        ?string $starname,
        int $epheflag,
        int $rsmi,
        array $geopos,
        float $atpress,
        float $attemp,
        ?float $horhgt,
        ?float &$tret = null,
        ?string &$serr = null
    ): int {
        $tret = 0.0;
        $serr = null;
        // Validate input
        if (!is_array($geopos) || count($geopos) < 2) {
            $serr = ErrorCodes::compose(ErrorCodes::INVALID_ARG, 'geopos must be [lon_deg, lat_deg, alt_m]');
            return Constants::SE_ERR;
        }
        $lon_deg = (float)$geopos[0];
        $lat_deg = (float)$geopos[1];
        $alt_m   = (float)($geopos[2] ?? 0.0);

        // Currently support Sun and Moon
        $isSun = ($ipl === Constants::SE_SUN);
        $isMoon = ($ipl === Constants::SE_MOON);
        if (!$isSun && !$isMoon) {
            $serr = ErrorCodes::compose(ErrorCodes::UNSUPPORTED, 'rise/set supported only for Sun and Moon at this stage');
            return Constants::SE_ERR;
        }

        // Determine event type
        $wantRise = (bool)($rsmi & Constants::SE_CALC_RISE);
        $wantSet  = (bool)($rsmi & Constants::SE_CALC_SET);
        $wantTrUp = (bool)($rsmi & Constants::SE_CALC_MTRANSIT);
        $wantTrLo = (bool)($rsmi & Constants::SE_CALC_ITRANSIT);
        $wantCount = ($wantRise?1:0)+($wantSet?1:0)+($wantTrUp?1:0)+($wantTrLo?1:0);
        if ($wantCount !== 1) {
            $serr = ErrorCodes::compose(ErrorCodes::INVALID_ARG, 'Specify exactly one of RISE/SET/MTRANSIT/ITRANSIT');
            return Constants::SE_ERR;
        }

        // Effective standard altitude: Sun ~ -0.833°, Moon ~ -0.3° (depends on distance). Allow override via horhgt.
        if ($horhgt !== null) {
            $h0_deg = $horhgt;
        } else {
            if ($isSun) {
                $h0_deg = -0.833;
            } else {
                // Dynamic semidiameter for Moon based on current distance at jd_ut
                $xxm = []; $e = null;
                $rcm = \swe_calc_ut($jd_ut, Constants::SE_MOON, Constants::SEFLG_EQUATORIAL | Constants::SEFLG_RADIANS, $xxm, $e);
                $dist_au0 = ($rcm >= 0) ? ($xxm[2] ?? 0.00257) : 0.00257;
                $AU_KM = 149597870.7; $Rmoon_km = 1737.4;
                $sd_rad = asin(min(1.0, $Rmoon_km / max($dist_au0 * $AU_KM, 1e-3)));
                $sd_deg = Math::radToDeg($sd_rad);
                $ref_deg = 0.5667; // standard refraction near horizon (~34')
                $h0_deg = -$ref_deg + $sd_deg; // apparent upper limb
            }
        }
        $h0_rad = Math::degToRad($h0_deg);
        $lat_rad = Math::degToRad($lat_deg);

        // Helper to compute altitude (radians) at a given UT
        $altAt = function (float $jd_ut_time) use ($epheflag, $lat_rad, $lon_deg, $isSun, $isMoon, $alt_m): float {
            $xx = []; $err = null; $ra = 0.0; $dec = 0.0;
            if ($isSun) {
                $rc = \swe_calc_ut($jd_ut_time, Constants::SE_SUN, Constants::SEFLG_EQUATORIAL | Constants::SEFLG_RADIANS, $xx, $err);
                if ($rc < 0) { return NAN; }
                $ra = $xx[0]; $dec = $xx[1];
            } else {
                // Moon: compute geocentric RA/Dec and apply topocentric correction (horizontal parallax)
                $rc = \swe_calc_ut($jd_ut_time, Constants::SE_MOON, Constants::SEFLG_EQUATORIAL | Constants::SEFLG_RADIANS, $xx, $err);
                if ($rc < 0) { return NAN; }
                $ra = $xx[0]; $dec = $xx[1];
                $dist_au = $xx[2] ?? 0.00257; // AU
                // Local parameters
                $lst = Horizontal::lstRad($jd_ut_time, $lon_deg);
                $H = Math::normAngleRad($lst - $ra);
                // Observer geodetic
                $phi = $lat_rad;
                $alt_km = $alt_m / 1000.0;
                $Re_km = 6378.137; // Earth equatorial radius
                $f = 1.0 / 298.257;
                $u = atan((1.0 - $f) * tan($phi));
                $rho_sin = sin($u) + ($alt_km / $Re_km) * sin($phi);
                $rho_cos = cos($u) + ($alt_km / $Re_km) * cos($phi);
                // Horizontal parallax (equatorial):
                $AU_KM = 149597870.7;
                $pi = asin(min(1.0, $Re_km / max($dist_au * $AU_KM, 1e-3)));
                // Parallax in RA:
                $sin_pi = sin($pi);
                $cos_dec = cos($dec); $sin_dec = sin($dec);
                $num = -$rho_cos * $sin_pi * sin($H);
                $den = $cos_dec - $rho_cos * $sin_pi * cos($H);
                $dalpha = atan2($num, $den);
                $ra = Math::normAngleRad($ra + $dalpha);
                // Topocentric Dec:
                $dec = atan2(($sin_dec - $rho_sin * $sin_pi) * cos($dalpha), $den);
            }
            $lst = Horizontal::lstRad($jd_ut_time, $lon_deg);
            [, $alt] = Horizontal::equatorialToHorizontal($ra, $dec, $lat_rad, $lst);
            return $alt;
        };

        // For transits: solve for hour angle H = 0 (upper) or H = pi (lower) -> RA = LST (+/- pi)
        if ($wantTrUp || $wantTrLo) {
            // We'll search within the 24h window [jd_ut, jd_ut+1)
            $targetOffset = $wantTrUp ? 0.0 : Math::PI; // H = 0 or H = pi
            $f = function (float $jd_ut_time) use ($lon_deg, $lat_rad, $alt_m, $ipl, $targetOffset): float {
                $xx = []; $err = null;
                $rc = \swe_calc_ut($jd_ut_time, $ipl, Constants::SEFLG_EQUATORIAL | Constants::SEFLG_RADIANS, $xx, $err);
                if ($rc < 0) { return 1e9; }
                $ra = $xx[0];
                if ($ipl === Constants::SE_MOON) {
                    // Apply topocentric parallax to RA for Moon
                    $dec = $xx[1];
                    $dist_au = $xx[2] ?? 0.00257; // AU
                    $lst = Horizontal::lstRad($jd_ut_time, $lon_deg);
                    $H = Math::normAngleRad($lst - $ra);
                    $phi = $lat_rad;
                    $alt_km = $alt_m / 1000.0;
                    $Re_km = 6378.137; $f = 1.0 / 298.257;
                    $u = atan((1.0 - $f) * tan($phi));
                    $rho_sin = sin($u) + ($alt_km / $Re_km) * sin($phi);
                    $rho_cos = cos($u) + ($alt_km / $Re_km) * cos($phi);
                    $AU_KM = 149597870.7;
                    $pi = asin(min(1.0, $Re_km / max($dist_au * $AU_KM, 1e-3)));
                    $sin_pi = sin($pi);
                    $cos_dec = cos($dec);
                    $num = -$rho_cos * $sin_pi * sin($H);
                    $den = $cos_dec - $rho_cos * $sin_pi * cos($H);
                    $dalpha = atan2($num, $den);
                    $ra = Math::normAngleRad($ra + $dalpha);
                }
                $lst = Horizontal::lstRad($jd_ut_time, $lon_deg);
                // f = normalize(LST - RA - targetOffset) mapped to [-pi, pi]
                $diff = Math::angleDiffRad($lst - $targetOffset, $ra);
                return $diff;
            };
            // Bisection around rough guess.
            // Чтобы разделить верхний и нижний транзиты, сдвинем окно поиска для нижнего примерно на 12 часов.
            $t0 = $jd_ut + ($wantTrLo ? 0.5 : 0.0);
            $t1 = $t0 + 1.0;
            $step = 2.0/24.0;
            $prev_t = $t0; $prev_f = $f($prev_t);
            $found = false; $a = $t0; $b = $t1;
            for ($t = $t0 + $step; $t <= $t1; $t += $step) {
                $ft = $f($t);
                if (!is_nan($prev_f) && $prev_f * $ft <= 0) { $a = $prev_t; $b = $t; $found = true; break; }
                $prev_t = $t; $prev_f = $ft;
            }
            if (!$found) {
                $serr = ErrorCodes::compose(ErrorCodes::NOT_FOUND, 'No transit found in [jd, jd+1)');
                return Constants::SE_ERR;
            }
            // Bisection refine
            for ($i=0; $i<30; $i++) {
                $m = 0.5*($a+$b); $fm = $f($m);
                $fa = $f($a);
                if ($fa * $fm <= 0) { $b = $m; } else { $a = $m; }
            }
            $tret = 0.5*($a+$b);
            // Приводим результат в исходные сутки [jd_ut, jd_ut+1)
            while ($tret < $jd_ut) { $tret += 1.0; }
            while ($tret >= $jd_ut + 1.0) { $tret -= 1.0; }
            return 0;
        }

        // For rise/set: solve alt(t) = h0 with direction filter
        $g = function (float $t) use ($altAt, $h0_rad): float { return $altAt($t) - $h0_rad; };
        // Быстрая проверка полярных случаев: оценим min/max высоты за сутки шагом 1h
        $t0 = $jd_ut; $t1 = $jd_ut + 1.0; $step = 1.0/24.0; // 1h steps
        $minAlt =  1e9; $maxAlt = -1e9;
        for ($t = $t0; $t <= $t1; $t += $step) {
            $a = $altAt($t);
            if (is_nan($a)) { continue; }
            if ($a < $minAlt) $minAlt = $a;
            if ($a > $maxAlt) $maxAlt = $a;
        }
        if ($maxAlt < $h0_rad) {
            $serr = ErrorCodes::compose(ErrorCodes::NOT_FOUND, 'Object stays below horizon (polar night)');
            return Constants::SE_ERR;
        }
        if ($minAlt > $h0_rad) {
            $serr = ErrorCodes::compose(ErrorCodes::NOT_FOUND, 'Object stays above horizon (midnight sun)');
            return Constants::SE_ERR;
        }
        // Scan through the day to find sign change with right direction
        $prev_t = $t0; $prev_g = $g($prev_t);
        $found = false; $a = $t0; $b = $t1;
        for ($t = $t0 + $step; $t <= $t1; $t += $step) {
            $gt = $g($t);
            if (!is_nan($prev_g) && $prev_g * $gt <= 0) {
                // Check direction: rising if prev<=0 and curr>=0; setting if prev>=0 and curr<=0
                $isRisingCross = ($prev_g <= 0 && $gt >= 0);
                $isSettingCross = ($prev_g >= 0 && $gt <= 0);
                if (($wantRise && $isRisingCross) || ($wantSet && $isSettingCross)) {
                    $a = $prev_t; $b = $t; $found = true; break;
                }
            }
            $prev_t = $t; $prev_g = $gt;
        }
        if (!$found) {
            $serr = ErrorCodes::compose(ErrorCodes::NOT_FOUND, 'No rise/set found in [jd, jd+1)');
            return Constants::SE_ERR;
        }
        // Refine root by bisection
        for ($i=0; $i<30; $i++) {
            $m = 0.5*($a+$b); $gm = $g($m);
            $ga = $g($a);
            if ($ga * $gm <= 0) { $b = $m; } else { $a = $m; }
        }
        $tret = 0.5*($a+$b);
        return 0;
    }

    public static function riseTransTrueHor(
        float $jd_ut,
        int $ipl,
        ?string $starname,
        int $epheflag,
        int $rsmi,
        array $geopos,
        float $atpress,
        float $attemp,
        ?float $horhgt,
        ?float &$tret = null,
        ?string &$serr = null
    ): int {
        $hh = $horhgt;
        if ($hh === 0.0) {
            $hh = 0.0;
        } elseif ($hh === null) {
            $hh = 0.0;
        }
        return self::riseTrans($jd_ut, $ipl, $starname, $epheflag, $rsmi, $geopos, $atpress, $attemp, $hh, $tret, $serr);
    }
}
