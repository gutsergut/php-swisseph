<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Swisseph\Constants;

require_once __DIR__ . '/bootstrap.php';

final class ParityAyanamshaGuardedTest extends TestCase
{
    private static function swetestPath(): ?string
    {
        $default = 'C:\\Users\\serge\\OneDrive\\Documents\\Fractal\\Projects\\Component\\Swisseph\\с-swisseph\\swisseph\\windows\\programs\\swetest64.exe';
        $custom = getenv('SWETEST_PATH');
        if (!$custom || $custom === false) { $custom = $default; }
        if (is_file($custom)) return $custom;
        $cmd = stripos(PHP_OS, 'WIN') === 0 ? 'where swetest' : 'which swetest';
        @exec($cmd, $out, $ret);
        return ($ret === 0 && !empty($out)) ? trim($out[0]) : null;
    }

    private static function epheDir(): ?string
    {
        $default = 'C:\\Users\\serge\\OneDrive\\Documents\\Fractal\\Projects\\Component\\Swisseph\\с-swisseph\\swisseph\\ephe';
        $dir = getenv('SWEPH_EPHE_DIR');
        if (!$dir || $dir === false) { $dir = $default; }
        return is_dir($dir) ? $dir : null;
    }

    private static function jdToDateString(float $jd_ut): string
    {
        $Z = (int)floor($jd_ut + 0.5);
        $F = ($jd_ut + 0.5) - $Z;
        if ($Z < 2299161) {
            $A = $Z;
        } else {
            $alpha = (int)floor(($Z - 1867216.25) / 36524.25);
            $A = $Z + 1 + $alpha - (int)floor($alpha / 4);
        }
        $B = $A + 1524;
        $C = (int)floor(($B - 122.1) / 365.25);
        $D = (int)floor(365.25 * $C);
        $E = (int)floor(($B - $D) / 30.6001);
        $day = $B - $D - (int)floor(30.6001 * $E) + $F;
        $month = ($E < 14) ? $E - 1 : $E - 13;
        $year = ($month > 2) ? $C - 4716 : $C - 4715;
        $dint = (int)floor($day);
        return sprintf('%d.%d.%d', $dint, $month, $year);
    }

    private static function getSwetestAyanamsa(string $swetest, float $jd_ut, int $sidmode): ?float
    {
        $dateStr = self::jdToDateString($jd_ut);
        $edir = self::epheDir();
        $cmd = sprintf('"%s" -b%s -ut -p -ay%d -fPl -head%s',
            $swetest, $dateStr, $sidmode, $edir ? ' -edir"'.$edir.'"' : ''
        );
        @exec($cmd . ' 2>&1', $out, $ret);
        if ($ret !== 0 || empty($out)) return null;
        foreach ($out as $line) {
            $t = trim($line);
            if (preg_match('/^ayanamsa\s+([\-0-9\.]+)/i', $t, $m)) {
                return (float)$m[1];
            }
        }
        return null;
    }

    public function testAyanamshaParityWithSwetest(): void
    {
        if (getenv('RUN_SWETEST_PARITY') !== '1') {
            $this->markTestSkipped('Set RUN_SWETEST_PARITY=1 to enable external swetest parity checks');
        }
        $swetest = self::swetestPath();
        if (!$swetest) {
            $this->markTestSkipped('swetest is not available in this environment');
        }

        $testDates = [
            ['jd_ut' => 2451544.5, 'label' => 'J2000.0'],
            ['jd_ut' => 2433282.5, 'label' => 'B1950'],
            ['jd_ut' => 2460680.5, 'label' => '2025-10-26'],
        ];

        $testModes = [
            Constants::SE_SIDM_FAGAN_BRADLEY => 'Fagan/Bradley',
            Constants::SE_SIDM_LAHIRI => 'Lahiri',
            Constants::SE_SIDM_DELUCE => 'De Luce',
            Constants::SE_SIDM_RAMAN => 'Raman',
            Constants::SE_SIDM_KRISHNAMURTI => 'Krishnamurti',
            Constants::SE_SIDM_J2000 => 'J2000',
            Constants::SE_SIDM_J1900 => 'J1900',
            Constants::SE_SIDM_B1950 => 'B1950',
            Constants::SE_SIDM_LAHIRI_1940 => 'Lahiri 1940',
        ];

        foreach ($testDates as $testDate) {
            foreach ($testModes as $mode => $name) {
                // Get PHP value
                swe_set_sid_mode($mode, 0, 0);
                $phpValue = null;
                $serr = null;
                swe_get_ayanamsa_ex($testDate['jd_ut'], 0, $phpValue, $serr);
                $this->assertNotNull($phpValue, "PHP ayanamsa for mode $mode ({$name}) at {$testDate['label']} should not be null");

                // Get swetest value
                $swValue = self::getSwetestAyanamsa($swetest, $testDate['jd_ut'], $mode);
                if ($swValue === null) {
                    // swetest may not support all modes or may fail; skip
                    continue;
                }

                // Compare (tolerance: 5 arcsec = ~0.00139°)
                $diffDeg = abs($phpValue - $swValue);
                $diffArcsec = $diffDeg * 3600.0;
                $this->assertLessThanOrEqual(5.0, $diffArcsec,
                    sprintf("Ayanamsa parity failed for mode %d (%s) at %s: PHP=%.6f°, swetest=%.6f°, diff=%.2f\"",
                        $mode, $name, $testDate['label'], $phpValue, $swValue, $diffArcsec)
                );
            }
        }
    }
}
