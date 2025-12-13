<?php
/**
 * Debug script to check what positions we get from ephemeris files
 */

require_once __DIR__ . '/../vendor/autoload.php';
use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;

\swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$jd = 2460000.0;  // 25 Feb 2023 00:00 UT

// Calculate Mercury to populate state
$xx = [];
$serr = '';
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;
\swe_calc($jd, Constants::SE_MERCURY, $iflag, $xx, $serr);

$swed = SwedState::getInstance();

echo "=== Raw positions from ephemeris (J2000 XYZ barycentric) ===\n\n";

// SEI_SUNBARY
$psbdp = $swed->pldat[SwephConstants::SEI_SUNBARY] ?? null;
if ($psbdp) {
    $r = sqrt($psbdp->x[0]**2 + $psbdp->x[1]**2 + $psbdp->x[2]**2);
    printf("SEI_SUNBARY: x=%.15f, y=%.15f, z=%.15f, dist=%.9f AU\n",
        $psbdp->x[0], $psbdp->x[1], $psbdp->x[2], $r);
}

// SEI_EARTH
$pedp = $swed->pldat[SwephConstants::SEI_EARTH] ?? null;
if ($pedp) {
    $r = sqrt($pedp->x[0]**2 + $pedp->x[1]**2 + $pedp->x[2]**2);
    printf("SEI_EARTH:   x=%.15f, y=%.15f, z=%.15f, dist=%.9f AU\n",
        $pedp->x[0], $pedp->x[1], $pedp->x[2], $r);
}

// SEI_EMB
$pembdp = $swed->pldat[SwephConstants::SEI_EMB] ?? null;
if ($pembdp) {
    $r = sqrt($pembdp->x[0]**2 + $pembdp->x[1]**2 + $pembdp->x[2]**2);
    printf("SEI_EMB:     x=%.15f, y=%.15f, z=%.15f, dist=%.9f AU\n",
        $pembdp->x[0], $pembdp->x[1], $pembdp->x[2], $r);
}

// Mercury (ipli = 2)
$pmdp = $swed->pldat[SwephConstants::SEI_MERCURY] ?? null;
if ($pmdp) {
    $r = sqrt($pmdp->x[0]**2 + $pmdp->x[1]**2 + $pmdp->x[2]**2);
    printf("SEI_MERCURY: x=%.15f, y=%.15f, z=%.15f, dist=%.9f AU\n",
        $pmdp->x[0], $pmdp->x[1], $pmdp->x[2], $r);
    printf("  iflg = 0x%X (HELIO=%d)\n", $pmdp->iflg, ($pmdp->iflg & SwephConstants::SEI_FLG_HELIO) ? 1 : 0);
}

// Now compute geo and bary
echo "\n=== Final computed positions ===\n\n";

$xx_geo = [];
$iflag_geo = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;
\swe_calc($jd, Constants::SE_MERCURY, $iflag_geo, $xx_geo, $serr);
printf("Mercury GEOCENTRIC: lon=%.7f, lat=%.7f, dist=%.9f AU\n", $xx_geo[0], $xx_geo[1], $xx_geo[2]);

$xx_bary = [];
$iflag_bary = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_BARYCTR;
\swe_calc($jd, Constants::SE_MERCURY, $iflag_bary, $xx_bary, $serr);
printf("Mercury BARYCENTRIC: lon=%.7f, lat=%.7f, dist=%.9f AU\n", $xx_bary[0], $xx_bary[1], $xx_bary[2]);

$xx_helio = [];
$iflag_helio = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_HELCTR;
\swe_calc($jd, Constants::SE_MERCURY, $iflag_helio, $xx_helio, $serr);
printf("Mercury HELIOCENTRIC: lon=%.7f, lat=%.7f, dist=%.9f AU\n", $xx_helio[0], $xx_helio[1], $xx_helio[2]);

echo "\n=== Reference from swetest64.exe ===\n";
echo "Mercury GEOCENTRIC: lon=336.7318606, lat=-2.3188099\n";
echo "Mercury BARYCENTRIC: lon=283.6398870, lat=-5.8235092\n";
echo "Mercury HELIOCENTRIC: lon=282.1825199, lat=-5.7241009\n";
