<?php

namespace Swisseph;

/**
 * Math helpers for angle operations and conversions.
 */
final class Math
{
    public const PI = 3.141592653589793238462643383279502884;
    public const TWO_PI = 6.283185307179586476925286766559005768;
    public const DEG_TO_RAD = 0.017453292519943295769236907684886127;  // PI / 180
    public const RAD_TO_DEG = 57.295779513082320876798154814105170332; // 180 / PI

    public static function degToRad(float $deg): float
    {
        return $deg * self::DEG_TO_RAD;
    }

    public static function radToDeg(float $rad): float
    {
        return $rad * self::RAD_TO_DEG;
    }

    /**
     * Normalize any angle in degrees to [0, 360).
     */
    public static function normAngleDeg(float $deg): float
    {
        $x = fmod($deg, 360.0);
        if ($x < 0) {
            $x += 360.0;
        }
        // avoid -0.0
        return $x === -0.0 ? 0.0 : $x;
    }

    /**
     * Alias for normAngleDeg() - matches C function swe_degnorm().
     */
    public static function degnorm(float $deg): float
    {
        return self::normAngleDeg($deg);
    }

    /**
     * Normalize any angle in radians to [0, 2π).
     */
    public static function normAngleRad(float $rad): float
    {
        $x = fmod($rad, self::TWO_PI);
        if ($x < 0) {
            $x += self::TWO_PI;
        }
        return $x === -0.0 ? 0.0 : $x;
    }

    /**
     * Wrap value x into [min, max) range.
     */
    public static function wrap(float $x, float $min, float $max): float
    {
        $w = $max - $min;
        if ($w == 0.0) {
            return $min;
        } // degenerate
        $y = fmod($x - $min, $w);
        if ($y < 0) {
            $y += $w;
        }
        return $min + $y;
    }

    /**
     * Smallest signed angular difference in radians, wrapped to [-pi, pi].
     */
    public static function angleDiffRad(float $to, float $from): float
    {
        $d = $to - $from;
        if ($d > self::PI) {
            $d -= self::TWO_PI;
        } elseif ($d < -self::PI) {
            $d += self::TWO_PI;
        }
        return $d;
    }

    /**
     * Smallest signed angular difference in degrees, wrapped to [-180, 180].
     */
    public static function angleDiffDeg(float $to, float $from): float
    {
        $d = $to - $from;
        if ($d > 180.0) {
            $d -= 360.0;
        } elseif ($d < -180.0) {
            $d += 360.0;
        }
        return $d;
    }

    /**
     * Midpoint between two angles in degrees (shortest arc).
     */
    public static function degMidpoint(float $x1, float $x0): float
    {
        $x0 = self::normAngleDeg($x0);
        $x1 = self::normAngleDeg($x1);
        $diff = self::angleDiffDeg($x1, $x0);
        return self::normAngleDeg($x0 + $diff / 2.0);
    }

    /**
     * Midpoint between two angles in radians (shortest arc).
     */
    public static function radMidpoint(float $x1, float $x0): float
    {
        $x0 = self::normAngleRad($x0);
        $x1 = self::normAngleRad($x1);
        $diff = self::angleDiffRad($x1, $x0);
        return self::normAngleRad($x0 + $diff / 2.0);
    }

    /**
     * Split decimal degrees into components.
     * @param float $ddeg Decimal degrees
     * @param int $roundflag Rounding flag (SE_SPLIT_DEG_*)
     * @param int &$ideg Degrees (0-360 or 0-30)
     * @param int &$imin Minutes (0-59)
     * @param int &$isec Seconds (0-59)
     * @param float &$dsecfr Fractional seconds
     * @param int &$isgn Sign (+1 or -1)
     */
    public static function splitDeg(
        float $ddeg,
        int $roundflag,
        int &$ideg,
        int &$imin,
        int &$isec,
        float &$dsecfr,
        int &$isgn
    ): void {
        // SE_SPLIT_DEG_* constants defined in Constants.php
        $isgn = $ddeg < 0.0 ? -1 : 1;
        $x = abs($ddeg);

        // Round flags:
        // SE_SPLIT_DEG_ROUND_SEC = 1, SE_SPLIT_DEG_ROUND_MIN = 2, SE_SPLIT_DEG_ROUND_DEG = 4
        // SE_SPLIT_DEG_ZODIACAL = 8, SE_SPLIT_DEG_KEEP_SIGN = 16, SE_SPLIT_DEG_KEEP_DEG = 32

        $zodiacal = ($roundflag & 8) !== 0; // SE_SPLIT_DEG_ZODIACAL
        $keepDeg = ($roundflag & 32) !== 0;  // SE_SPLIT_DEG_KEEP_DEG

        if ($zodiacal && !$keepDeg) {
            $x = fmod($x, 30.0);
        }

        $d = floor($x);
        $r = ($x - $d) * 60.0;
        $m = floor($r);
        $r = ($r - $m) * 60.0;
        $s = floor($r);
        $f = $r - $s;

        // Apply rounding
        if (($roundflag & 1) !== 0) { // SE_SPLIT_DEG_ROUND_SEC
            $s = round($s + $f);
            $f = 0.0;
            if ($s >= 60) {
                $s = 0;
                $m++;
            }
        }
        if (($roundflag & 2) !== 0) { // SE_SPLIT_DEG_ROUND_MIN
            $m = round($m + $s / 60.0);
            $s = 0;
            $f = 0.0;
            if ($m >= 60) {
                $m = 0;
                $d++;
            }
        }
        if (($roundflag & 4) !== 0) { // SE_SPLIT_DEG_ROUND_DEG
            $d = round($d + $m / 60.0);
            $m = 0;
            $s = 0;
            $f = 0.0;
        }

        $ideg = (int)$d;
        $imin = (int)$m;
        $isec = (int)$s;
        $dsecfr = $f;
    }

    /**
     * Normalize angle to range [0, 2π)
     * Port of swi_mod2PI() from Swiss Ephemeris
     *
     * @param float $x Angle in radians
     * @return float Normalized angle in [0, 2π)
     */
    public static function mod2PI(float $x): float
    {
        $y = fmod($x, self::TWO_PI);
        if ($y < 0) {
            $y += self::TWO_PI;
        }
        return $y;
    }
}
