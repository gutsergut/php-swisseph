<?php

declare(strict_types=1);

namespace Swisseph\Swe\Functions;

/**
 * Centisecond utility functions
 *
 * Port from swephlib.c:3785-3930
 *
 * Centisec (centiseconds) = int32, represents angles and times
 * 1 degree = 360000 centisec (3600 seconds * 100)
 * Used for high-precision angle calculations in Swiss Ephemeris
 */
final class CentisecFunctions
{
    // Constants from sweodef.h:272-290
    private const DEG360 = 360 * 360000;  // 360° in centisec = 129600000
    private const DEG180 = 180 * 360000;  // 180° in centisec = 64800000
    private const DEG30 = 30 * 360000;    // 30° (one sign) in centisec = 10800000
    private const TWOPI = 6.28318530717959;  // 2π

    /**
     * Normalize centisec into interval [0..360°[
     * Port from swephlib.c:3785-3793
     *
     * @param int $p Angle in centisec
     * @return int Normalized angle in [0, DEG360[
     */
    public static function csnorm(int $p): int
    {
        if ($p < 0) {
            do {
                $p += self::DEG360;
            } while ($p < 0);
        } elseif ($p >= self::DEG360) {
            do {
                $p -= self::DEG360;
            } while ($p >= self::DEG360);
        }
        return $p;
    }

    /**
     * Distance in centisec p1 - p2, normalized to [0..360[
     * Port from swephlib.c:3799-3802
     *
     * @param int $p1 First angle in centisec
     * @param int $p2 Second angle in centisec
     * @return int Normalized difference in [0, DEG360[
     */
    public static function difcsn(int $p1, int $p2): int
    {
        return self::csnorm($p1 - $p2);
    }

    /**
     * Distance in degrees p1 - p2, normalized to [0..360[
     * Port from swephlib.c:3804-3807
     *
     * @param float $p1 First angle in degrees
     * @param float $p2 Second angle in degrees
     * @return float Normalized difference in [0, 360[
     */
    public static function difdegn(float $p1, float $p2): float
    {
        return \swe_degnorm($p1 - $p2);
    }

    /**
     * Distance in centisec p1 - p2, normalized to [-180..180[
     * Port from swephlib.c:3813-3819
     *
     * @param int $p1 First angle in centisec
     * @param int $p2 Second angle in centisec
     * @return int Normalized difference in [-DEG180, DEG180[
     */
    public static function difcs2n(int $p1, int $p2): int
    {
        $dif = self::csnorm($p1 - $p2);
        if ($dif >= self::DEG180) {
            return $dif - self::DEG360;
        }
        return $dif;
    }

    /**
     * Distance in degrees p1 - p2, normalized to [-180..180[
     * Port from swephlib.c:3821-3826
     *
     * @param float $p1 First angle in degrees
     * @param float $p2 Second angle in degrees
     * @return float Normalized difference in [-180, 180[
     */
    public static function difdeg2n(float $p1, float $p2): float
    {
        $dif = \swe_degnorm($p1 - $p2);
        if ($dif >= 180.0) {
            return $dif - 360.0;
        }
        return $dif;
    }

    /**
     * Distance in radians p1 - p2, normalized to [-π..π[
     * Port from swephlib.c:3828-3833
     *
     * @param float $p1 First angle in radians
     * @param float $p2 Second angle in radians
     * @return float Normalized difference in [-π, π[
     */
    public static function difrad2n(float $p1, float $p2): float
    {
        $dif = \swe_radnorm($p1 - $p2);
        if ($dif >= self::TWOPI / 2) {
            return $dif - self::TWOPI;
        }
        return $dif;
    }

    /**
     * Round centisec to seconds, but at 29°59'59" always round down
     * Port from swephlib.c:3839-3847
     *
     * Special case: Avoids rounding last second of zodiac sign up to next sign
     *
     * @param int $x Angle in centisec
     * @return int Rounded to full seconds (nearest 100 centisec)
     */
    public static function csroundsec(int $x): int
    {
        $t = intdiv($x + 50, 100) * 100;  // Round to seconds

        // If rounded up to next sign (at exactly 30° boundary)
        if ($t > $x && $t % self::DEG30 === 0) {
            // Round last second of sign downwards
            $t = intdiv($x, 100) * 100;
        }

        return $t;
    }

    /**
     * Convert centisec time to HH:MM:SS string
     * Port from swephlib.c:3874-3899
     *
     * @param int $t Time in centisec (24h = 8640000 centisec)
     * @param int $sep Separator character (e.g. 58 for ':')
     * @param bool $suppressZero If true, omit ":00" seconds
     * @return string Formatted time "HH:MM:SS" or "HH:MM"
     */
    public static function cs2timestr(int $t, int $sep = 58, bool $suppressZero = false): string
    {
        $sepChar = chr($sep);

        // Round to seconds and limit to 24h
        $t = intdiv($t + 50, 100) % (24 * 3600);

        $s = $t % 60;
        $m = intdiv($t, 60) % 60;
        $h = intdiv($t, 3600) % 100;

        $hh = str_pad((string)$h, 2, '0', STR_PAD_LEFT);
        $mm = str_pad((string)$m, 2, '0', STR_PAD_LEFT);

        if ($s === 0 && $suppressZero) {
            return "{$hh}{$sepChar}{$mm}";
        }

        $ss = str_pad((string)$s, 2, '0', STR_PAD_LEFT);
        return "{$hh}{$sepChar}{$mm}{$sepChar}{$ss}";
    }

    /**
     * Convert centisec angle to DDDEmm'ss" format (longitude/latitude)
     * Port from swephlib.c:3901-3920
     *
     * @param int $t Angle in centisec
     * @param string $pchar Positive direction char (e.g. 'E', 'N')
     * @param string $mchar Negative direction char (e.g. 'W', 'S')
     * @return string Formatted angle "DDDEmm'ss" or "DDDWmm'ss"
     */
    public static function cs2lonlatstr(int $t, string $pchar, string $mchar): string
    {
        $dirChar = $pchar;
        if ($t < 0) {
            $dirChar = $mchar;
            $t = -$t;
        }

        // Round to seconds
        $t = intdiv($t + 50, 100);

        $s = $t % 60;
        $m = intdiv($t, 60) % 60;
        $h = intdiv($t, 3600) % 1000;  // Up to 999°

        // Build string
        $result = '';

        // Degrees (suppress leading zeros)
        if ($h > 99) {
            $result .= (string)intdiv($h, 100);
        }
        if ($h > 9) {
            $result .= (string)(intdiv($h % 100, 10));
        }
        $result .= (string)($h % 10);

        $result .= $dirChar;
        $result .= str_pad((string)$m, 2, '0', STR_PAD_LEFT);
        $result .= "'";

        // Suppress seconds if zero
        if ($s === 0) {
            return $result;
        }

        $result .= str_pad((string)$s, 2, '0', STR_PAD_LEFT);
        $result .= '"';

        return $result;
    }

    /**
     * Convert centisec angle to DD°mm'ss format (zodiac degree)
     * Port from swephlib.c:3922-3930
     *
     * Truncates to [0..30[ degrees (one zodiac sign)
     *
     * @param int $t Angle in centisec
     * @return string Formatted angle "DD°mm'ss"
     */
    public static function cs2degstr(int $t): string
    {
        // Truncate to seconds and limit to 30° (one sign)
        $t = intdiv($t, 100) % (30 * 3600);

        $s = $t % 60;
        $m = intdiv($t, 60) % 60;
        $h = intdiv($t, 3600) % 100;  // Only 0..99°

        return sprintf("%2d°%02d'%02d\"", $h, $m, $s);
    }
}
