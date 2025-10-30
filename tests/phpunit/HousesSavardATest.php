<?php

use PHPUnit\Framework\TestCase;
use Swisseph\Swe\Functions\HousesFunctions;
use Swisseph\DeltaT;
use Swisseph\Obliquity;
use Swisseph\Houses;
use Swisseph\Math;

final class HousesSavardATest extends TestCase
{
    private function epsilonCusps(): float { return 1e-9; }

    public function testSavardACuspsBasicConsistency(): void
    {
        // Несколько локаций и дат
        $cases = [
            [48.8566, 2.3522, 2460680.5],   // Париж
            [55.7558, 37.6173, 2460680.9],  // Москва, др. время
            [-33.8688, 151.2093, 2460681.2] // Сидней
        ];
        foreach ($cases as [$geolat, $geolon, $jd_ut]) {
            $cJ = $aJ = [];
            $rcJ = HousesFunctions::houses($jd_ut, $geolat, $geolon, 'J', $cJ, $aJ);
            $this->assertSame(0, $rcJ);
            // 12 куспов и валидные оси
            $this->assertCount(13, $cJ);
            $this->assertIsArray($aJ);
            $this->assertEqualsWithDelta( Math::normAngleDeg($aJ[0] + 180.0), Math::normAngleDeg($cJ[7]), 1e-8);
            $this->assertEqualsWithDelta( Math::normAngleDeg($aJ[1] + 180.0), Math::normAngleDeg($cJ[4]), 1e-8);
            // Противоположные дома совпадают на 180°
            $pairs = [[1,7],[2,8],[3,9],[4,10],[5,11],[6,12]];
            foreach ($pairs as [$i,$j]) {
                $this->assertEqualsWithDelta(
                    Math::normAngleDeg($cJ[$i] + 180.0),
                    Math::normAngleDeg($cJ[$j]),
                    1e-8,
                    "Opposite cusp mismatch $i-$j"
                );
            }
            // Монотонность от Asc по окружности
            $unwrapped = [];
            $prev = $cJ[1];
            $unwrapped[1] = $prev;
            for ($k = 2; $k <= 12; $k++) {
                $x = $cJ[$k];
                while ($x < $prev) { $x += 360.0; }
                $unwrapped[$k] = $x;
                $prev = $x;
            }
            for ($k = 1; $k < 12; $k++) {
                $this->assertTrue($unwrapped[$k+1] > $unwrapped[$k], "Non-increasing at cusp $k");
            }
        }
    }

    public function testSavardAHousePosExactCuspsAndAxes(): void
    {
        $geolat = 40.7128; // Нью-Йорк
        $geolon = -74.0060;
        $jd_ut = 2460680.75;

        $cJ = $aJ = [];
        $rcJ = HousesFunctions::houses($jd_ut, $geolat, $geolon, 'J', $cJ, $aJ);
        $this->assertSame(0, $rcJ);
        $jd_tt = $jd_ut + DeltaT::deltaTSecondsFromJd($jd_ut) / 86400.0;
        $eps_deg = Math::radToDeg(Obliquity::meanObliquityRadFromJdTT($jd_tt));
        $armc_deg = Math::radToDeg(Houses::armcFromSidereal($jd_ut, $geolon));

        // Оси: точное попадание мапится в 1/10/7/4
        $this->assertEquals(1.0, HousesFunctions::housePos($armc_deg, $geolat, $eps_deg, 'J', [$aJ[0], 0.0]));
        $this->assertEquals(10.0, HousesFunctions::housePos($armc_deg, $geolat, $eps_deg, 'J', [$aJ[1], 0.0]));
        $this->assertEquals(7.0, HousesFunctions::housePos($armc_deg, $geolat, $eps_deg, 'J', [Math::normAngleDeg($aJ[0] + 180.0), 0.0]));
        $this->assertEquals(4.0, HousesFunctions::housePos($armc_deg, $geolat, $eps_deg, 'J', [Math::normAngleDeg($aJ[1] + 180.0), 0.0]));

        // Попадания на куспы: housePos должен вернуть ровно номер дома
        for ($i = 1; $i <= 12; $i++) {
            $pos = HousesFunctions::housePos($armc_deg, $geolat, $eps_deg, 'J', [$cJ[$i], 0.0]);
            $this->assertEquals((float)$i, $pos, "housePos on cusp $i should return $i");
        }
    }
}
