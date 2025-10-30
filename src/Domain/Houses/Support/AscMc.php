<?php

namespace Swisseph\Domain\Houses\Support;

use Swisseph\Houses;
use Swisseph\Math;
use Swisseph\Obliquity;
use Swisseph\DeltaT;

/**
 * Быстрый доступ к Asc/MC/ARMC/eps из JD(UT) и координат.
 */
final class AscMc
{
    /**
     * Вычислить eps и ARMC из JD(UT) и долготы, затем Asc/MC.
     * Возвращает [armc_rad, asc_rad, mc_rad, eps_rad].
     */
    public static function fromJdUt(float $jd_ut, float $geolon_deg, float $geolat_deg): array
    {
        $eps = Obliquity::meanObliquityRadFromJdTT($jd_ut + DeltaT::deltaTSecondsFromJd($jd_ut) / 86400.0);
        $armc = Houses::armcFromSidereal($jd_ut, $geolon_deg);
        [$asc, $mc] = Houses::ascMcFromArmc($armc, Math::degToRad($geolat_deg), $eps);
        return [$armc, $asc, $mc, $eps];
    }
}
