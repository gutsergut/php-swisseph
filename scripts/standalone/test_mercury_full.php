<?php
/**
 * Full pipeline test for Mercury
 */

require_once __DIR__ . '/../vendor/autoload.php';

\swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$tjd = 2460000.5;  // 25 Feb 2023 12:00 UT

echo "=== PHP Full Pipeline Test for Mercury ===\n\n";

// Heliocentric, J2000, true position, no nutation, XYZ ecliptic
$iflag = \Swisseph\Constants::SEFLG_HELCTR
       | \Swisseph\Constants::SEFLG_J2000
       | \Swisseph\Constants::SEFLG_TRUEPOS
       | \Swisseph\Constants::SEFLG_NONUT
       | \Swisseph\Constants::SEFLG_SPEED
       | \Swisseph\Constants::SEFLG_XYZ;

$xx = [];
$serr = '';

$ret = \swe_calc($tjd, \Swisseph\Constants::SE_MERCURY, $iflag, $xx, $serr);
if ($ret < 0) {
    echo "Error: $serr\n";
    exit(1);
}

echo "PHP swe_calc (helio, j2000, true, nonut, XYZ) - ecliptic cartesian:\n";
printf("  x = %.15f\n", $xx[0]);
printf("  y = %.15f\n", $xx[1]);
printf("  z = %.15f\n", $xx[2]);
$dist = sqrt($xx[0]**2 + $xx[1]**2 + $xx[2]**2);
printf("  dist = %.15f AU\n", $dist);

echo "\n=== Reference from C (swetest -j2460000 -p2 -fX -hel -true -j2000 -nonut) ===\n";
echo "  x = 0.090838610\n";
echo "  y = -0.444901479\n";
echo "  z = -0.044689620\n";

// Now equatorial
$iflag_eq = $iflag | \Swisseph\Constants::SEFLG_EQUATORIAL;
$ret = \swe_calc($tjd, \Swisseph\Constants::SE_MERCURY, $iflag_eq, $xx, $serr);

echo "\nPHP swe_calc with SEFLG_EQUATORIAL - equatorial cartesian J2000:\n";
printf("  x = %.15f\n", $xx[0]);
printf("  y = %.15f\n", $xx[1]);
printf("  z = %.15f\n", $xx[2]);
$dist = sqrt($xx[0]**2 + $xx[1]**2 + $xx[2]**2);
printf("  dist = %.15f AU\n", $dist);

echo "\n=== Reference from C (same + SEFLG_EQUATORIAL) ===\n";
echo "  x = 0.090838610\n";
echo "  y = -0.390412661\n";
echo "  z = -0.217973490\n";
