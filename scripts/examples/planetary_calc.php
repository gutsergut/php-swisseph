<?php
/**
 * Тест планетарных вычислений
 */

require __DIR__ . '/../tests/bootstrap.php';

use Swisseph\Constants;

$jd = 2451545.0; // J2000.0

// Test all major planets
$planets = [
    Constants::SE_SUN => ['name' => 'Sun', 'min_dist' => 0.98, 'max_dist' => 1.02],
    Constants::SE_MOON => ['name' => 'Moon', 'min_dist' => 0.0024, 'max_dist' => 0.0028],
    Constants::SE_MERCURY => ['name' => 'Mercury', 'min_dist' => 0.3, 'max_dist' => 1.5],
    Constants::SE_VENUS => ['name' => 'Venus', 'min_dist' => 0.2, 'max_dist' => 1.8],
    Constants::SE_MARS => ['name' => 'Mars', 'min_dist' => 0.5, 'max_dist' => 2.8],
    Constants::SE_JUPITER => ['name' => 'Jupiter', 'min_dist' => 4.0, 'max_dist' => 6.5],
    Constants::SE_SATURN => ['name' => 'Saturn', 'min_dist' => 8.0, 'max_dist' => 11.0],
    Constants::SE_URANUS => ['name' => 'Uranus', 'min_dist' => 18.0, 'max_dist' => 21.0],
    Constants::SE_NEPTUNE => ['name' => 'Neptune', 'min_dist' => 29.0, 'max_dist' => 31.5],
];

foreach ($planets as $ipl => $data) {
    $xx = [];
    $serr = null;
    $ret = swe_calc($jd, $ipl, Constants::SEFLG_SWIEPH, $xx, $serr);

    if ($ret < 0) {
        fwrite(STDERR, "{$data['name']} calculation failed: $serr\n");
        exit(1);
    }

    // Check longitude range
    if ($xx[0] < 0.0 || $xx[0] >= 360.0) {
        fwrite(STDERR, "{$data['name']} longitude out of range: {$xx[0]}\n");
        exit(2);
    }

    // Check latitude range
    if ($xx[1] < -90.0 || $xx[1] > 90.0) {
        fwrite(STDERR, "{$data['name']} latitude out of range: {$xx[1]}\n");
        exit(3);
    }

    // Check distance range (AU for planets, Earth radii for Moon)
    if ($xx[2] < $data['min_dist'] || $xx[2] > $data['max_dist']) {
        fwrite(STDERR, "{$data['name']} distance suspicious: {$xx[2]} AU\n");
        exit(4);
    }
}

// Test with speed flag
$xx_speed = [];
$serr = null;
$ret = swe_calc($jd, Constants::SE_MARS, Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED,
                $xx_speed, $serr);

if ($ret < 0) {
    fwrite(STDERR, "Mars with speed failed: $serr\n");
    exit(5);
}

if (!isset($xx_speed[3], $xx_speed[4], $xx_speed[5])) {
    fwrite(STDERR, "Speed values missing\n");
    exit(6);
}

// Mars speed should be reasonable (0.3 - 0.8 deg/day typically)
if (abs($xx_speed[3]) < 0.1 || abs($xx_speed[3]) > 1.5) {
    fwrite(STDERR, "Mars speed suspicious: {$xx_speed[3]} deg/day\n");
    exit(7);
}

// Test equatorial coordinates
$xx_eq = [];
$ret = swe_calc($jd, Constants::SE_JUPITER,
                Constants::SEFLG_SWIEPH | Constants::SEFLG_EQUATORIAL, $xx_eq, $serr);

if ($ret < 0) {
    fwrite(STDERR, "Jupiter equatorial failed: $serr\n");
    exit(8);
}

// RA should be 0-360
if ($xx_eq[0] < 0.0 || $xx_eq[0] >= 360.0) {
    fwrite(STDERR, "RA out of range: {$xx_eq[0]}\n");
    exit(9);
}

// Dec should be -90 to 90
if ($xx_eq[1] < -90.0 || $xx_eq[1] > 90.0) {
    fwrite(STDERR, "Dec out of range: {$xx_eq[1]}\n");
    exit(10);
}

// Test swe_calc_ut variant
$xx_ut = [];
$jd_ut = 2451545.0;
$ret = swe_calc_ut($jd_ut, Constants::SE_VENUS, Constants::SEFLG_SWIEPH, $xx_ut, $serr);

if ($ret < 0) {
    fwrite(STDERR, "swe_calc_ut failed: $serr\n");
    exit(11);
}

if (!isset($xx_ut[0], $xx_ut[1], $xx_ut[2])) {
    fwrite(STDERR, "swe_calc_ut missing coordinates\n");
    exit(12);
}

echo "OK\n";
