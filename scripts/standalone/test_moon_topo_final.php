<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// Test Moon topocentric coordinates after DeltaTFull integration
// Compare with C swetest64 reference values

echo "Testing Moon Topocentric Coordinates after DeltaTFull integration\n";
echo str_repeat("=", 80) . "\n\n";

// Set ephemeris path
$ephe_path = __DIR__ . '/../../eph/ephe';
swe_set_ephe_path($ephe_path);
echo "Ephemeris path set to: $ephe_path\n\n";

// Observer location: Dallas, TX (lon=-96.8, lat=32.8, alt=0)
$lon = -96.8;
$lat = 32.8;
$alt = 0.0;

// Date: 2024-04-08 06:18 UT (around time of solar eclipse)
// Compute JD UT precisely to avoid 0.5-day offset mistakes
$tjd_ut = swe_julday(2024, 4, 8, 6.0 + 18.0/60.0, Constants::SE_GREG_CAL);

echo "Date: JD $tjd_ut (2024-04-08 06:18 UT)\n";
printf("Observer: lon=%.1f°, lat=%.1f°, alt=%.1f m\n\n", $lon, $lat, $alt);

// Set topocentric location
swe_set_topo($lon, $lat, $alt);
echo "Topocentric location set\n\n";

// Calculate Moon coordinates with topocentric flag
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_TOPOCTR | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_SPEED;
$ipl = Constants::SE_MOON;

$xx = [];
$serr = null;
$retval = swe_calc_ut($tjd_ut, $ipl, $iflag, $xx, $serr);

if ($retval >= 0 && count($xx) > 0) {
    echo "Moon Topocentric Equatorial Coordinates:\n";
    printf("  RA (Right Ascension):  %12.8f° = ", $xx[0]);    // Convert to h m s
    $ra_hours = $xx[0] / 15.0;
    $ra_h = floor($ra_hours);
    $ra_m = floor(($ra_hours - $ra_h) * 60);
    $ra_s = (($ra_hours - $ra_h) * 60 - $ra_m) * 60;
    printf("%02dh %02dm %06.3fs\n", $ra_h, $ra_m, $ra_s);

    printf("  Dec (Declination):      %12.8f°\n", $xx[1]);
    printf("  Distance:               %12.8f AU\n", $xx[2]);

    echo "\nExpected from C swetest64 (reference):\n";
    echo "  RA:   0h 44m 22.6312s ≈ 11.094296°\n";
    echo "  Dec: +3° 45' 49.9800\" ≈ +3.763883°\n";

    // C reference converted to degrees:
    // RA = (0 + 44/60 + 22.6312/3600) * 15 = 11.094296°
    // Dec = 3 + 45/60 + 49.9800/3600 = 3.763883°
    $expected_ra = 11.094296;
    $expected_dec = 3.763883;
    $error_ra_deg = abs($xx[0] - $expected_ra);
    $error_ra_arcsec = $error_ra_deg * 3600.0;

    $error_dec_deg = abs($xx[1] - $expected_dec);
    $error_dec_arcsec = $error_dec_deg * 3600.0;

    echo "\nErrors vs C reference:\n";
    printf("  ΔRA:  %12.8f° = %10.4f arcsec\n", $error_ra_deg, $error_ra_arcsec);
    printf("  ΔDec: %12.8f° = %10.4f arcsec\n", $error_dec_deg, $error_dec_arcsec);

    echo "\nAccuracy assessment:\n";
    if ($error_ra_arcsec < 1.0 && $error_dec_arcsec < 1.0) {
        echo "  ✅ EXCELLENT: Error < 1 arcsec (sub-arcsecond accuracy)\n";
    } elseif ($error_ra_arcsec < 10.0 && $error_dec_arcsec < 10.0) {
        echo "  ✅ GOOD: Error < 10 arcsec\n";
    } elseif ($error_ra_arcsec < 60.0 && $error_dec_arcsec < 60.0) {
        echo "  ⚠️  ACCEPTABLE: Error < 1 arcmin\n";
    } else {
        echo "  ❌ POOR: Error > 1 arcmin\n";
    }

} else {
    echo "Error calculating Moon position: $serr\n";
}

echo "\n";
echo str_repeat("=", 80) . "\n";
echo "Note: DeltaTFull now uses Bessel 4th-order interpolation from C tables\n";
echo "Expected improvement: from ~26 arcsec error to <1 arcsec error\n";
