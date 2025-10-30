<?php

namespace Swisseph;

final class Utc
{
    /**
     * Convert UTC calendar components to JD(UT) and JD(TT).
     * Returns array [jd_ut, jd_tt]
     */
    public static function utcToJd(int $y, int $m, int $d, int $hour, int $min, float $sec, int $gregflag): array
    {
        $ut = $hour + $min / 60.0 + $sec / 3600.0;
        $jd_ut = Julian::toJulianDay($y, $m, $d, $ut, $gregflag);
        $dt = DeltaT::deltaTSecondsFromJd($jd_ut);
        $jd_tt = $jd_ut + $dt / 86400.0;
        return [$jd_ut, $jd_tt];
    }

    /**
     * Convert JD(UT) to UTC components.
     * Returns array [y, m, d, hour, min, sec]
     */
    public static function jdToUtc(float $jd_ut, int $gregflag): array
    {
        $d = Julian::fromJulianDay($jd_ut, $gregflag);
        $y = $d['y'];
        $m = $d['m'];
        $day = $d['d'];
        $ut = $d['ut'];
        $hour = (int) floor($ut + 1e-12);
        $min_float = ($ut - $hour) * 60.0;
        $min = (int) floor($min_float + 1e-12);
        $sec = ($min_float - $min) * 60.0;
        // Normalize rounding, avoid 60.0 sec
        if ($sec >= 59.9999995) {
            $sec = 0.0;
            $min += 1;
        }
        if ($min >= 60) {
            $min = 0;
            $hour += 1;
        }
        if ($hour >= 24) {
            $hour = 0;
            $day += 1; /* simplistic: not handling month overflow explicitly */
        }
        return [$y, $m, (int)$day, $hour, $min, $sec];
    }
}
