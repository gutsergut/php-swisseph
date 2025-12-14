<?php
/**
 * Find exact moment when Venus speed gets corrupted
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/functions.php';

swe_set_ephe_path(__DIR__ . '/../eph/ephe');

define('SE_SUN', 0);
define('SE_SATURN', 6);
define('SE_URANUS', 7);
define('SE_VENUS', 3);
define('SEFLG_SPEED', 256);
define('SEFLG_EQUATORIAL', 2048);
define('SEFLG_RADIANS', 32768);

$jd = 2451545.0;
$serr = null;

function checkVenus($label) {
    global $jd, $serr;
    $xx = [];
    swe_calc($jd, SE_VENUS, SEFLG_SPEED, $xx, $serr);
    $ok = abs($xx[3]) >= 0.2 && abs($xx[3]) <= 3.5;
    echo "[$label] Venus speed: {$xx[3]} " . ($ok ? "OK" : "BAD!") . "\n";
    return $ok;
}

echo "=== Finding Venus corruption point ===\n\n";

checkVenus("Initial");

// Saturn Default
$xx = [];
swe_calc($jd, SE_SATURN, 0, $xx, $serr);
checkVenus("After Saturn flags=0");

// Saturn Equatorial
$xx = [];
swe_calc($jd, SE_SATURN, SEFLG_RADIANS | SEFLG_EQUATORIAL, $xx, $serr);
checkVenus("After Saturn RADIANS|EQUATORIAL");

// Saturn Speed
$xx = [];
swe_calc($jd, SE_SATURN, SEFLG_SPEED, $xx, $serr);
checkVenus("After Saturn SEFLG_SPEED");

// Sun Default
$xx = [];
swe_calc($jd, SE_SUN, 0, $xx, $serr);
checkVenus("After Sun flags=0");

// Sun Equatorial
$xx = [];
swe_calc($jd, SE_SUN, SEFLG_RADIANS | SEFLG_EQUATORIAL, $xx, $serr);
checkVenus("After Sun RADIANS|EQUATORIAL");

// Uranus Default
$xx = [];
swe_calc($jd, SE_URANUS, 0, $xx, $serr);
checkVenus("After Uranus flags=0");

// Uranus Equatorial
$xx = [];
swe_calc($jd, SE_URANUS, SEFLG_RADIANS | SEFLG_EQUATORIAL, $xx, $serr);
checkVenus("After Uranus RADIANS|EQUATORIAL");

// Uranus Speed
$xx = [];
swe_calc($jd, SE_URANUS, SEFLG_SPEED, $xx, $serr);
checkVenus("After Uranus SEFLG_SPEED");
