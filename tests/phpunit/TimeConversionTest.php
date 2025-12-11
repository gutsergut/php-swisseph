<?php

use PHPUnit\Framework\TestCase;

/**
 * Test suite for time conversion functions (LMT ↔ LAT)
 */
final class TimeConversionTest extends TestCase
{
    public function testLmtToLatBasic(): void
    {
        // Test for Greenwich (lon=0): LMT = UT, so LAT = UT + E
        $geolon = 0.0;
        $tjd_lmt = 2451545.0; // J2000.0 (2000-01-01 12:00 UT)
        $tjd_lat = 0.0;

        $result = swe_lmt_to_lat($tjd_lmt, $geolon, $tjd_lat);

        $this->assertSame(\Swisseph\Constants::SE_OK, $result);
        $this->assertIsFloat($tjd_lat);

        // LAT should be slightly different from LMT (equation of time)
        // At J2000.0, equation of time is small but non-zero
        $diff = abs($tjd_lat - $tjd_lmt);
        $this->assertLessThan(0.02, $diff); // Less than ~30 minutes
    }

    public function testLatToLmtBasic(): void
    {
        // Test reverse conversion
        $geolon = 0.0;
        $tjd_lat = 2451545.0;
        $tjd_lmt = 0.0;

        $result = swe_lat_to_lmt($tjd_lat, $geolon, $tjd_lmt);

        $this->assertSame(\Swisseph\Constants::SE_OK, $result);
        $this->assertIsFloat($tjd_lmt);

        // LMT should be slightly different from LAT
        $diff = abs($tjd_lmt - $tjd_lat);
        $this->assertLessThan(0.02, $diff);
    }

    public function testLmtLatRoundtrip(): void
    {
        // Test that LMT -> LAT -> LMT gives approximately same result
        $geolon = 0.0;
        $tjd_lmt_original = 2451545.0;
        $tjd_lat = 0.0;
        $tjd_lmt_final = 0.0;

        // LMT -> LAT
        swe_lmt_to_lat($tjd_lmt_original, $geolon, $tjd_lat);

        // LAT -> LMT
        swe_lat_to_lmt($tjd_lat, $geolon, $tjd_lmt_final);

        // Should be very close (within seconds)
        $this->assertEqualsWithDelta($tjd_lmt_original, $tjd_lmt_final, 0.0001); // ~8 seconds
    }

    public function testLmtToLatWithLongitude(): void
    {
        // Test with non-zero longitude (e.g., Moscow ~37.6°E)
        $geolon = 37.6;
        $tjd_lmt = 2451545.0;
        $tjd_lat = 0.0;

        $result = swe_lmt_to_lat($tjd_lmt, $geolon, $tjd_lat);

        $this->assertSame(\Swisseph\Constants::SE_OK, $result);
        $this->assertIsFloat($tjd_lat);

        // LAT should differ from LMT by equation of time
        // (longitude affects UT conversion, not LAT-LMT difference)
        $diff = abs($tjd_lat - $tjd_lmt);
        $this->assertLessThan(0.02, $diff);
    }

    public function testLatToLmtWithNegativeLongitude(): void
    {
        // Test with negative longitude (West, e.g., New York ~-74°)
        $geolon = -74.0;
        $tjd_lat = 2451545.0;
        $tjd_lmt = 0.0;

        $result = swe_lat_to_lmt($tjd_lat, $geolon, $tjd_lmt);

        $this->assertSame(\Swisseph\Constants::SE_OK, $result);
        $this->assertIsFloat($tjd_lmt);
    }

    public function testEquationOfTimeEffect(): void
    {
        // Test at dates where equation of time is significant

        // Around Feb 11: E ≈ -14 minutes (negative, so LAT < LMT, sun is slow)
        $tjd_lmt = swe_julday(2000, 2, 11, 12.0, \Swisseph\Constants::SE_GREG_CAL);
        $tjd_lat = 0.0;
        swe_lmt_to_lat($tjd_lmt, 0.0, $tjd_lat);

        // LAT should be behind (smaller) because E is negative
        $this->assertLessThan($tjd_lmt, $tjd_lat);

        // Around Nov 3: E ≈ +16 minutes (positive, so LAT > LMT, sun is fast)
        $tjd_lmt2 = swe_julday(2000, 11, 3, 12.0, \Swisseph\Constants::SE_GREG_CAL);
        $tjd_lat2 = 0.0;
        swe_lmt_to_lat($tjd_lmt2, 0.0, $tjd_lat2);

        // LAT should be ahead (larger) because E is positive
        $this->assertGreaterThan($tjd_lmt2, $tjd_lat2);
    }
}
