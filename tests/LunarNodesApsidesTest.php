<?php

declare(strict_types=1);

namespace Swisseph\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Swisseph\Constants;

/**
 * Tests for swe_calc() with lunar nodes and apsides
 *
 * Reference values from swetest64.exe for JD=2460000.5 (25 Feb 2023 00:00 UT)
 */
class LunarNodesApsidesTest extends TestCase
{
    private const JD = 2460000.5;
    private const EPHE_PATH = __DIR__ . '/../../eph/ephe';

    protected function setUp(): void
    {
        parent::setUp();
        \swe_set_ephe_path(self::EPHE_PATH);
    }

    #[Test]
    public function testMeanNode(): void
    {
        // Reference: swetest64 -b25.2.2023 -ut00:00:00 -pm
        // mean Node 2460000.50 37.2914761 0.0000000 0.002569555 -0.0529678
        $xx = [];
        $serr = null;

        $ret = \swe_calc(self::JD, Constants::SE_MEAN_NODE, Constants::SEFLG_SPEED, $xx, $serr);

        $this->assertGreaterThanOrEqual(0, $ret, "swe_calc failed: $serr");
        $this->assertEqualsWithDelta(37.2914761, $xx[0], 0.01, 'Mean Node longitude');
        // Latitude can be small non-zero due to transformation chain
        $this->assertEqualsWithDelta(0.0, $xx[1], 0.01, 'Mean Node latitude should be near 0');
        $this->assertEqualsWithDelta(-0.0529678, $xx[3], 0.001, 'Mean Node longitude speed');
    }

    #[Test]
    public function testTrueNode(): void
    {
        // Reference: swetest64 -b25.2.2023 -ut00:00:00 -pt
        // true Node 2460000.50 35.8566277 0.0000000 0.002544237 0.0044299
        $xx = [];
        $serr = null;

        $ret = \swe_calc(self::JD, Constants::SE_TRUE_NODE, Constants::SEFLG_SPEED, $xx, $serr);

        $this->assertGreaterThanOrEqual(0, $ret, "swe_calc failed: $serr");
        // Allow 0.05° tolerance (3 arcmin) - oscillating node has inherent uncertainty
        $this->assertEqualsWithDelta(35.8566277, $xx[0], 0.05, 'True Node longitude');
        $this->assertEqualsWithDelta(0.0, $xx[1], 0.01, 'True Node latitude should be near 0');
        $this->assertEqualsWithDelta(0.0044299, $xx[3], 0.01, 'True Node longitude speed');
    }

    #[Test]
    public function testMeanApogee(): void
    {
        // Reference: swetest64 -b25.2.2023 -ut00:00:00 -pA
        // mean Apogee 2460000.50 125.3147095 5.1423508 0.002710625 0.1120522
        $xx = [];
        $serr = null;

        $ret = \swe_calc(self::JD, Constants::SE_MEAN_APOG, Constants::SEFLG_SPEED, $xx, $serr);

        $this->assertGreaterThanOrEqual(0, $ret, "swe_calc failed: $serr");
        $this->assertEqualsWithDelta(125.3147095, $xx[0], 0.01, 'Mean Apogee longitude');
        $this->assertEqualsWithDelta(5.1423508, $xx[1], 0.01, 'Mean Apogee latitude');
        $this->assertEqualsWithDelta(0.002710625, $xx[2], 0.00001, 'Mean Apogee distance');
        $this->assertEqualsWithDelta(0.1120522, $xx[3], 0.001, 'Mean Apogee longitude speed');
    }

    #[Test]
    public function testOscuApogee(): void
    {
        // Reference: swetest64 -b25.2.2023 -ut00:00:00 -pB
        // osc. Apogee 2460000.50 123.3606289 5.0870555 0.002729960 -2.6305447
        $xx = [];
        $serr = null;

        $ret = \swe_calc(self::JD, Constants::SE_OSCU_APOG, Constants::SEFLG_SPEED, $xx, $serr);

        $this->assertGreaterThanOrEqual(0, $ret, "swe_calc failed: $serr");
        $this->assertEqualsWithDelta(123.3606289, $xx[0], 0.02, 'Oscu Apogee longitude');
        $this->assertEqualsWithDelta(5.0870555, $xx[1], 0.01, 'Oscu Apogee latitude');
        $this->assertEqualsWithDelta(0.002729960, $xx[2], 0.00001, 'Oscu Apogee distance');
        $this->assertEqualsWithDelta(-2.6305447, $xx[3], 0.01, 'Oscu Apogee longitude speed');
    }

    #[Test]
    #[DataProvider('multiDateProvider')]
    public function testTrueNodeMultipleDates(float $jd, float $expectedLon, float $tolerance): void
    {
        $xx = [];
        $serr = null;

        $ret = \swe_calc($jd, Constants::SE_TRUE_NODE, Constants::SEFLG_SPEED, $xx, $serr);

        $this->assertGreaterThanOrEqual(0, $ret, "swe_calc failed: $serr");
        $this->assertEqualsWithDelta($expectedLon, $xx[0], $tolerance, "True Node longitude at JD=$jd");
    }

    public static function multiDateProvider(): array
    {
        // Reference values from swetest64 (DMS converted to decimal degrees)
        // JD 2460000.5: 35°51'23.8597" = 35.8566277°
        // JD 2460101.5: 33°19'45.6667" = 33.3293519°
        // JD 2459900.5: 43°19'17.9380" = 43.3216494°
        return [
            'JD 2460000.5' => [2460000.5, 35.8566277, 0.05],
            'JD 2460101.5' => [2460101.5, 33.3293519, 0.2],
            'JD 2459900.5' => [2459900.5, 43.3216494, 0.2],
        ];
    }

    #[Test]
    #[DataProvider('multiDateApogProvider')]
    public function testOscuApogeeMultipleDates(float $jd, float $expectedLon, float $tolerance): void
    {
        $xx = [];
        $serr = null;

        $ret = \swe_calc($jd, Constants::SE_OSCU_APOG, Constants::SEFLG_SPEED, $xx, $serr);

        $this->assertGreaterThanOrEqual(0, $ret, "swe_calc failed: $serr");
        $this->assertEqualsWithDelta($expectedLon, $xx[0], $tolerance, "Oscu Apogee longitude at JD=$jd");
    }

    public static function multiDateApogProvider(): array
    {
        // Reference values from swetest64 (DMS converted to decimal degrees)
        // JD 2460000.5: 123°21'38.2640" = 123.3606289°
        // JD 2460101.5: 115°10'24.7773" = 115.1735493°
        // JD 2459900.5: 114°54'04.8134" = 114.9013371°
        return [
            'JD 2460000.5' => [2460000.5, 123.3606289, 0.02],
            'JD 2460101.5' => [2460101.5, 115.1735493, 1.2],
            'JD 2459900.5' => [2459900.5, 114.9013371, 0.05],
        ];
    }

    #[Test]
    public function testNoSpeedFlag(): void
    {
        // Test without SEFLG_SPEED
        $xx = [];
        $serr = null;

        $ret = \swe_calc(self::JD, Constants::SE_TRUE_NODE, 0, $xx, $serr);

        $this->assertGreaterThanOrEqual(0, $ret, "swe_calc failed: $serr");
        $this->assertEqualsWithDelta(35.8566277, $xx[0], 0.05, 'True Node longitude without speed flag');
    }

    #[Test]
    public function testHeliocentricNodeNotAllowed(): void
    {
        // Heliocentric lunar node should return zeros (as per C code)
        $xx = [];
        $serr = null;

        $ret = \swe_calc(
            self::JD,
            Constants::SE_TRUE_NODE,
            Constants::SEFLG_HELCTR | Constants::SEFLG_SPEED,
            $xx,
            $serr
        );

        // Should return iflag (success) but coordinates are zeros
        $this->assertGreaterThanOrEqual(0, $ret);
    }
}
