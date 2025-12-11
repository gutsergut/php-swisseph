<?php

use PHPUnit\Framework\TestCase;
use Swisseph\Swe\Functions\HousesFunctions;
use Swisseph\Obliquity;
use Swisseph\DeltaT;
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
    // eps на эту дату (наклон эклиптики). Для согласованности с кодом берём средний наклон по TT.
    $jd_tt = $jd_ut + DeltaT::deltaTSecondsFromJd($jd_ut) / 86400.0;
    $eps_deg = Math::radToDeg(Obliquity::meanObliquityRadFromJdTT($jd_tt));
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

    public function testApcAxisPositions(): void
    {
        $geolat = 55.7558;   // Москва
        $geolon = 37.6173;
        $jd_ut = 2460680.5;  // 2025-10-01 00:00 UT

        $cusps = $ascmc = [];
        $rc = HousesFunctions::houses($jd_ut, $geolat, $geolon, 'Y', $cusps, $ascmc);
        $this->assertSame(0, $rc);
        $this->assertNotEmpty($ascmc);

        $jd_tt = $jd_ut + DeltaT::deltaTSecondsFromJd($jd_ut) / 86400.0;
        $eps_deg = Math::radToDeg(Obliquity::meanObliquityRadFromJdTT($jd_tt));
        $armc_deg = Math::radToDeg(Houses::armcFromSidereal($jd_ut, $geolon));

        $asc = $ascmc[0];
        $mc  = $ascmc[1];
        $desc = Math::normAngleDeg($asc + 180.0);
        $ic   = Math::normAngleDeg($mc + 180.0);

        $pAsc  = HousesFunctions::housePos($armc_deg, $geolat, $eps_deg, 'Y', [$asc, 0.0]);
        $pMc   = HousesFunctions::housePos($armc_deg, $geolat, $eps_deg, 'Y', [$mc, 0.0]);
        $pDesc = HousesFunctions::housePos($armc_deg, $geolat, $eps_deg, 'Y', [$desc, 0.0]);
        $pIc   = HousesFunctions::housePos($armc_deg, $geolat, $eps_deg, 'Y', [$ic, 0.0]);

    // Должно быть близко к номерам осей 1/10/7/4 (с допуском по Y):
    // алгоритм house_pos('Y') геометрически согласован, но может давать
    // небольшое отклонение на меридиане/анти-меридиане.
    $tol = 0.15; // ~4.5°
    $this->assertLessThan($tol, abs($pAsc - 1.0));
    $this->assertLessThan($tol, abs($pMc  - 10.0));
    $this->assertLessThan($tol, abs($pDesc - 7.0));
    $this->assertLessThan($tol, abs($pIc   - 4.0));
    }
}
