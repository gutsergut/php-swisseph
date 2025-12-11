<?php

use PHPUnit\Framework\TestCase;
use Swisseph\Swe\Functions\HousesFunctions;
use Swisseph\Obliquity;
use Swisseph\Houses;
use Swisseph\Math;

final class HousesApcHousePosTest extends TestCase
{
    // Простая проверка стабильности house_pos для APC ('Y') на нескольких точках
    public function testApcHousePosBasic(): void
    {
        $geolat = 48.8566;   // Париж
        $geolon = 2.3522;
        $jd_ut = 2460680.5;  // произвольная дата (2025-10-01 00:00 UT)

        // Получаем ARMC/Asc/MC/eps через фасад: houses() уже считает всё нужное
        $cusps = $ascmc = [];
        $rc = HousesFunctions::houses($jd_ut, $geolat, $geolon, 'Y', $cusps, $ascmc);
        $this->assertSame(0, $rc, 'houses() should succeed for APC');
        $this->assertNotEmpty($ascmc);
        // eps на эту дату (истинний наклон эклиптики)
        $eps_deg = Obliquity::trueObliquityDeg($jd_ut + 0 /* UT ok for test */);
        // ARMC восстановим из Asc/MC и широты — в тесте достаточно аппроксимации через вспомогательную функцию
        // Либо напрямую через Houses::armcFromSidereal
        $armc = Houses::armcFromSidereal($jd_ut, $geolon);
        $armc_deg = Math::radToDeg($armc);

        // Возьмём несколько долгот объектов: Asc, MC+30°, Desc-10°
        $samples = [
            $ascmc[0],
            Math::normAngleDeg($ascmc[1] + 30),
            Math::normAngleDeg($ascmc[0] + 180 - 10),
        ];

        foreach ($samples as $lon) {
            $pos = HousesFunctions::housePos($armc_deg, $geolat, $eps_deg, 'Y', [$lon, 0.0]);
            // Позиция должна быть в (0,12]
            $this->assertTrue($pos > 0.0 && $pos <= 12.0, 'APC house_pos should be within (0,12]');
        }
    }
}
