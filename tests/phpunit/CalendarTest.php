<?php

use PHPUnit\Framework\TestCase;

/**
 * Test suite for calendar utility functions (swe_date_conversion, swe_day_of_week)
 */
final class CalendarTest extends TestCase
{
    public function testDateConversionValid(): void
    {
        $tjd = 0.0;

        // Valid Gregorian date: 2000-01-01 12:00 UT
        $result = swe_date_conversion(2000, 1, 1, 12.0, 'g', $tjd);
        $this->assertSame(\Swisseph\Constants::SE_OK, $result);
        $this->assertEqualsWithDelta(2451545.0, $tjd, 0.001); // J2000.0

        // Valid Julian date: same day in Julian calendar
        $result = swe_date_conversion(2000, 1, 1, 0.0, 'j', $tjd);
        $this->assertSame(\Swisseph\Constants::SE_OK, $result);
        $this->assertGreaterThan(0, $tjd);

        // Leap year Feb 29
        $result = swe_date_conversion(2000, 2, 29, 0.0, 'g', $tjd);
        $this->assertSame(\Swisseph\Constants::SE_OK, $result);

        // UTC midnight
        $result = swe_date_conversion(2025, 10, 27, 0.0, 'g', $tjd);
        $this->assertSame(\Swisseph\Constants::SE_OK, $result);
    }

    public function testDateConversionInvalidMonth(): void
    {
        $tjd = 0.0;

        // Invalid month 0
        $result = swe_date_conversion(2000, 0, 1, 12.0, 'g', $tjd);
        $this->assertSame(\Swisseph\Constants::SE_ERR, $result);

        // Invalid month 13
        $result = swe_date_conversion(2000, 13, 1, 12.0, 'g', $tjd);
        $this->assertSame(\Swisseph\Constants::SE_ERR, $result);
    }

    public function testDateConversionInvalidDay(): void
    {
        $tjd = 0.0;

        // Day 0
        $result = swe_date_conversion(2000, 1, 0, 12.0, 'g', $tjd);
        $this->assertSame(\Swisseph\Constants::SE_ERR, $result);

        // Feb 30 (invalid)
        $result = swe_date_conversion(2000, 2, 30, 12.0, 'g', $tjd);
        $this->assertSame(\Swisseph\Constants::SE_ERR, $result);

        // Feb 29 in non-leap year
        $result = swe_date_conversion(2001, 2, 29, 12.0, 'g', $tjd);
        $this->assertSame(\Swisseph\Constants::SE_ERR, $result);

        // Day 32 in January
        $result = swe_date_conversion(2000, 1, 32, 12.0, 'g', $tjd);
        $this->assertSame(\Swisseph\Constants::SE_ERR, $result);
    }

    public function testDateConversionInvalidTime(): void
    {
        $tjd = 0.0;

        // Negative time
        $result = swe_date_conversion(2000, 1, 1, -1.0, 'g', $tjd);
        $this->assertSame(\Swisseph\Constants::SE_ERR, $result);

        // Time >= 24.0
        $result = swe_date_conversion(2000, 1, 1, 24.0, 'g', $tjd);
        $this->assertSame(\Swisseph\Constants::SE_ERR, $result);
    }

    public function testDateConversionInvalidCalendar(): void
    {
        $tjd = 0.0;

        // Invalid calendar type 'x'
        $result = swe_date_conversion(2000, 1, 1, 12.0, 'x', $tjd);
        $this->assertSame(\Swisseph\Constants::SE_ERR, $result);
    }

    public function testDayOfWeek(): void
    {
        // Known dates

        // 2000-01-01 (Saturday) - JD 2451544.5
        $jd = swe_julday(2000, 1, 1, 0.0, \Swisseph\Constants::SE_GREG_CAL);
        $dow = swe_day_of_week($jd);
        $this->assertSame(5, $dow); // Saturday = 5 (Mon=0, Tue=1, ..., Sat=5, Sun=6)

        // 2000-01-03 (Monday) - JD 2451546.5
        $jd = swe_julday(2000, 1, 3, 0.0, \Swisseph\Constants::SE_GREG_CAL);
        $dow = swe_day_of_week($jd);
        $this->assertSame(0, $dow); // Monday = 0

        // 2025-10-27 (Monday)
        $jd = swe_julday(2025, 10, 27, 0.0, \Swisseph\Constants::SE_GREG_CAL);
        $dow = swe_day_of_week($jd);
        $this->assertSame(0, $dow); // Monday = 0

        // 2025-10-26 (Sunday)
        $jd = swe_julday(2025, 10, 26, 0.0, \Swisseph\Constants::SE_GREG_CAL);
        $dow = swe_day_of_week($jd);
        $this->assertSame(6, $dow); // Sunday = 6
    }

    public function testDayOfWeekCycle(): void
    {
        // Test that day of week cycles correctly
        $jd = swe_julday(2000, 1, 3, 0.0, \Swisseph\Constants::SE_GREG_CAL); // Monday

        for ($i = 0; $i < 14; $i++) {
            $dow = swe_day_of_week($jd + $i);
            $expected = $i % 7;
            $this->assertSame($expected, $dow, "Day $i should be " . $expected);
        }
    }
}
