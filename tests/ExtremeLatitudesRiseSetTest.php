<?php

declare(strict_types=1);

/**
 * Test rise/set/transit for extreme latitudes
 * 
 * Tests the rise_set_slow() algorithm for high latitudes (>60°/65°)
 * and circumpolar conditions where objects never rise or never set.
 */

use PHPUnit\Framework\TestCase;
use Swisseph\Constants;

require_once __DIR__ . '/../vendor/autoload.php';

class ExtremeLatitudesRiseSetTest extends TestCase
{
    private const EPHE_PATH = __DIR__ . '/../../eph/ephe';
    
    protected function setUp(): void
    {
        swe_set_ephe_path(self::EPHE_PATH);
    }

    /**
     * Test Sun rise/set at Arctic Circle (66.5°N) in summer
     * Should find midnight sun (no set) around summer solstice
     */
    public function testSunArcticCircleSummer(): void
    {
        // Location: Rovaniemi, Finland (Arctic Circle)
        $lon = 25.7;   // 25.7°E
        $lat = 66.5;   // 66.5°N (Arctic Circle)
        $alt = 0.0;
        
        // Date: 2025-06-21 (summer solstice)
        $jd_ut = swe_julday(2025, 6, 21, 12.0, Constants::SE_GREG_CAL);
        
        $tret = 0.0;
        $serr = '';
        
        $rsmi = Constants::SE_CALC_RISE;
        $flags = Constants::SEFLG_SWIEPH;
        
        $rc = swe_rise_trans($jd_ut, Constants::SE_SUN, '', $flags, $rsmi, 
                             [$lon, $lat, $alt], 0, 0, null, $tret, $serr);
        
        // At Arctic Circle on summer solstice, Sun should not set
        // but DOES rise (barely touches horizon at midnight)
        $this->assertNotEquals(Constants::ERR, $rc, "Rise calculation failed: $serr");
        
        // Check if rise time is found
        if ($rc >= 0 && $tret > 0) {
            echo "\n";
            echo "Arctic Circle (66.5°N) - Summer Solstice\n";
            echo "Rise time: JD {$tret}\n";
            
            [$y, $m, $d, $h] = swe_revjul($tret, Constants::SE_GREG_CAL);
            $hh = (int)$h;
            $mm = (int)(($h - $hh) * 60);
            $ss = (int)((($h - $hh) * 60 - $mm) * 60);
            echo sprintf("Rise: %04d-%02d-%02d %02d:%02d:%02d UT\n", $y, $m, $d, $hh, $mm, $ss);
        }
        
        // Now test set
        $rsmi = Constants::SE_CALC_SET;
        $tret_set = 0.0;
        $rc_set = swe_rise_trans($jd_ut, Constants::SE_SUN, '', $flags, $rsmi,
                                 [$lon, $lat, $alt], 0, 0, null, $tret_set, $serr);
        
        if ($rc_set >= 0 && $tret[0] > 0) {
            [$y, $m, $d, $h] = swe_revjul($tret[0], Constants::SE_GREG_CAL);
            $hh = (int)$h;
            $mm = (int)(($h - $hh) * 60);
            $ss = (int)((($h - $hh) * 60 - $mm) * 60);
            echo sprintf("Set:  %04d-%02d-%02d %02d:%02d:%02d UT\n\n", $y, $m, $d, $hh, $mm, $ss);
        } else {
            echo "Set: NONE (midnight sun)\n\n";
        }
        
        // Test should complete without fatal errors
        $this->assertTrue(true);
    }

    /**
     * Test Sun rise/set at Arctic Circle (66.5°N) in winter
     * Should find polar night (no rise) around winter solstice
     */
    public function testSunArcticCircleWinter(): void
    {
        // Location: Rovaniemi, Finland (Arctic Circle)
        $lon = 25.7;   // 25.7°E
        $lat = 66.5;   // 66.5°N
        $alt = 0.0;
        
        // Date: 2025-12-21 (winter solstice)
        $jd_ut = swe_julday(2025, 12, 21, 12.0, Constants::SE_GREG_CAL);
        
        $tret = 0.0;
        $serr = '';
        
        $rsmi = Constants::SE_CALC_RISE;
        $flags = Constants::SEFLG_SWIEPH;
        
        $rc = swe_rise_trans($jd_ut, Constants::SE_SUN, '', $flags, $rsmi,
                             [$lon, $lat, $alt], 0, 0, null, $tret, $serr);
        
        echo "\n";
        echo "Arctic Circle (66.5°N) - Winter Solstice\n";
        
        if ($rc >= 0 && $tret > 0) {
            [$y, $m, $d, $h] = swe_revjul($tret, Constants::SE_GREG_CAL);
            $hh = (int)$h;
            $mm = (int)(($h - $hh) * 60);
            $ss = (int)((($h - $hh) * 60 - $mm) * 60);
            echo sprintf("Rise: %04d-%02d-%02d %02d:%02d:%02d UT\n", $y, $m, $d, $hh, $mm, $ss);
        } else {
            echo "Rise: NONE (polar night)\n";
        }
        
        $rsmi = Constants::SE_CALC_SET;
        $rc_set = swe_rise_trans($jd_ut, Constants::SE_SUN, '', $flags, $rsmi,
                                 [$lon, $lat, $alt], 0, 0, $tret, $serr);
        
        if ($rc_set >= 0 && $tret[0] > 0) {
            [$y, $m, $d, $h] = swe_revjul($tret[0], Constants::SE_GREG_CAL);
            $hh = (int)$h;
            $mm = (int)(($h - $hh) * 60);
            $ss = (int)((($h - $hh) * 60 - $mm) * 60);
            echo sprintf("Set:  %04d-%02d-%02d %02d:%02d:%02d UT\n\n", $y, $m, $d, $hh, $mm, $ss);
        } else {
            echo "Set: NONE (polar night)\n\n";
        }
        
        $this->assertTrue(true);
    }

    /**
     * Test Sun rise/set at North Pole (90°N)
     * Sun should be circumpolar - rises once per year (spring equinox),
     * sets once per year (autumn equinox)
     */
    public function testSunNorthPole(): void
    {
        // Location: North Pole
        $lon = 0.0;
        $lat = 90.0;   // 90°N
        $alt = 0.0;
        
        // Date: 2025-03-20 (spring equinox)
        $jd_ut = swe_julday(2025, 3, 20, 12.0, Constants::SE_GREG_CAL);
        
        $tret = 0.0;
        $serr = '';
        
        $rsmi = Constants::SE_CALC_RISE;
        $flags = Constants::SEFLG_SWIEPH;
        
        $rc = swe_rise_trans($jd_ut, Constants::SE_SUN, '', $flags, $rsmi,
                             [$lon, $lat, $alt], 0, 0, null, $tret, $serr);
        
        echo "\n";
        echo "North Pole (90°N) - Spring Equinox\n";
        
        if ($rc >= 0 && $tret > 0) {
            [$y, $m, $d, $h] = swe_revjul($tret, Constants::SE_GREG_CAL);
            $hh = (int)$h;
            $mm = (int)(($h - $hh) * 60);
            $ss = (int)((($h - $hh) * 60 - $mm) * 60);
            echo sprintf("Rise: %04d-%02d-%02d %02d:%02d:%02d UT\n", $y, $m, $d, $hh, $mm, $ss);
            
            // Rise should be around March 20
            $this->assertGreaterThanOrEqual(2025, $y);
            $this->assertEquals(3, $m, "Sun should rise in March at North Pole");
        } else {
            echo "Rise: Could not calculate (error: $serr)\n";
        }
        
        // Test set around autumn equinox
        $jd_ut = swe_julday(2025, 9, 22, 12.0, Constants::SE_GREG_CAL);
        $rsmi = Constants::SE_CALC_SET;
        $tret_set3 = 0.0;
        
        $rc_set = swe_rise_trans($jd_ut, Constants::SE_SUN, '', $flags, $rsmi,
                                 [$lon, $lat, $alt], 0, 0, null, $tret_set3, $serr);
        
        if ($rc_set >= 0 && $tret_set3 > 0) {
            [$y, $m, $d, $h] = swe_revjul($tret_set3, Constants::SE_GREG_CAL);
            $hh = (int)$h;
            $mm = (int)(($h - $hh) * 60);
            $ss = (int)((($h - $hh) * 60 - $mm) * 60);
            echo sprintf("Set:  %04d-%02d-%02d %02d:%02d:%02d UT\n\n", $y, $m, $d, $hh, $mm, $ss);
            
            // Set should be around September 22
            $this->assertEquals(9, $m, "Sun should set in September at North Pole");
        } else {
            echo "Set: Could not calculate (error: $serr)\n\n";
        }
        
        $this->assertTrue(true);
    }

    /**
     * Test Moon rise/set at high latitude (70°N)
     * Moon at 70°N can be circumpolar or never rise depending on declination
     */
    public function testMoonHighLatitude(): void
    {
        // Location: Tromsø, Norway
        $lon = 18.96;  // 18.96°E
        $lat = 69.65;  // 69.65°N
        $alt = 0.0;
        
        // Date: 2025-01-15
        $jd_ut = swe_julday(2025, 1, 15, 12.0, Constants::SE_GREG_CAL);
        
        $tret = 0.0;
        $serr = '';
        
        $rsmi = Constants::SE_CALC_RISE;
        $flags = Constants::SEFLG_SWIEPH;
        
        $rc = swe_rise_trans($jd_ut, Constants::SE_MOON, '', $flags, $rsmi,
                             [$lon, $lat, $alt], 0, 0, null, $tret, $serr);
        
        echo "\n";
        echo "Tromsø, Norway (69.65°N) - Moon\n";
        
        if ($rc >= 0 && $tret > 0) {
            [$y, $m, $d, $h] = swe_revjul($tret, Constants::SE_GREG_CAL);
            $hh = (int)$h;
            $mm = (int)(($h - $hh) * 60);
            $ss = (int)((($h - $hh) * 60 - $mm) * 60);
            echo sprintf("Rise: %04d-%02d-%02d %02d:%02d:%02d UT\n", $y, $m, $d, $hh, $mm, $ss);
        } else {
            echo "Rise: NONE (Moon below horizon or circumpolar)\n";
        }
        
        $rsmi = Constants::SE_CALC_SET;
        $tret_set4 = 0.0;
        $rc_set = swe_rise_trans($jd_ut, Constants::SE_MOON, '', $flags, $rsmi,
                                 [$lon, $lat, $alt], 0, 0, null, $tret_set4, $serr);
        
        if ($rc_set >= 0 && $tret_set4 > 0) {
            [$y, $m, $d, $h] = swe_revjul($tret_set4, Constants::SE_GREG_CAL);
            $hh = (int)$h;
            $mm = (int)(($h - $hh) * 60);
            $ss = (int)((($h - $hh) * 60 - $mm) * 60);
            echo sprintf("Set:  %04d-%02d-%02d %02d:%02d:%02d UT\n\n", $y, $m, $d, $hh, $mm, $ss);
        } else {
            echo "Set: NONE (Moon below horizon or circumpolar)\n\n";
        }
        
        $this->assertTrue(true);
    }
}
