<?php
/**
 * Debug: Read raw JPL DE406e header and test pleph()
 */

$ephDir = 'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/data/ephemerides/jpl/';
$ephFile = 'de406e.eph';

require_once 'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/php-swisseph/vendor/autoload.php';

use Swisseph\Swe\Jpl\JplEphemeris;
use Swisseph\Swe\Jpl\JplConstants;

$jpl = JplEphemeris::getInstance();
$ss = [];
$serr = null;
$result = $jpl->open($ss, $ephFile, $ephDir, $serr);

if ($result !== 0) {
    echo "Open failed: $serr\n";
    exit(1);
}

echo "Open succeeded!\n";
echo "DE Number: " . $jpl->getDenum() . "\n";
echo "AU: " . $jpl->getAu() . " km\n";
echo "EMRAT: " . $jpl->getEmrat() . "\n";

echo "\nStart JD: {$ss[0]}\n";
echo "End JD: {$ss[1]}\n";
echo "Step: {$ss[2]} days\n";

// Now test pleph for Earth (body 3)
echo "\n\n=== Testing pleph() for Earth at JD 2460016.5 ===\n";
$jd = 2460016.5;
$pv = [];
$result = $jpl->pleph($jd, JplConstants::J_EARTH, JplConstants::J_SBARY, $pv, $serr);

if ($result !== JplConstants::OK) {
    echo "pleph() failed: $serr\n";
} else {
    echo "pleph() succeeded!\n";
    echo "Earth barycentric position (AU):\n";
    printf("  X = %.15f\n", $pv[0]);
    printf("  Y = %.15f\n", $pv[1]);
    printf("  Z = %.15f\n", $pv[2]);
    echo "Earth barycentric velocity (AU/day):\n";
    printf("  VX = %.15f\n", $pv[3]);
    printf("  VY = %.15f\n", $pv[4]);
    printf("  VZ = %.15f\n", $pv[5]);
}

// Test Sun position
echo "\n=== Testing pleph() for Sun at JD 2460016.5 ===\n";
$pv = [];
$result = $jpl->pleph($jd, JplConstants::J_SUN, JplConstants::J_SBARY, $pv, $serr);

if ($result !== JplConstants::OK) {
    echo "pleph() failed: $serr\n";
} else {
    echo "Sun barycentric position (AU):\n";
    printf("  X = %.15f\n", $pv[0]);
    printf("  Y = %.15f\n", $pv[1]);
    printf("  Z = %.15f\n", $pv[2]);
}

// Test EMB position
echo "\n=== Testing pleph() for EMB at JD 2460016.5 ===\n";
$pv = [];
$result = $jpl->pleph($jd, JplConstants::J_EMB, JplConstants::J_SBARY, $pv, $serr);

if ($result !== JplConstants::OK) {
    echo "pleph() failed: $serr\n";
} else {
    echo "EMB barycentric position (AU):\n";
    printf("  X = %.15f\n", $pv[0]);
    printf("  Y = %.15f\n", $pv[1]);
    printf("  Z = %.15f\n", $pv[2]);
}

// Test Mercury position
echo "\n=== Testing pleph() for Mercury at JD 2460016.5 ===\n";
$pv = [];
$result = $jpl->pleph($jd, JplConstants::J_MERCURY, JplConstants::J_SBARY, $pv, $serr);

if ($result !== JplConstants::OK) {
    echo "pleph() failed: $serr\n";
} else {
    echo "Mercury barycentric position (AU):\n";
    printf("  X = %.15f\n", $pv[0]);
    printf("  Y = %.15f\n", $pv[1]);
    printf("  Z = %.15f\n", $pv[2]);
    echo "swetest reference: X=0.344141688, Y=-0.118605054, Z=-0.099762103\n";
}

// Test Mercury at J2000 epoch
echo "\n=== Testing pleph() for Mercury at JD 2451545.0 (J2000) ===\n";
$jd2000 = 2451545.0;
$pv = [];
$result = $jpl->pleph($jd2000, JplConstants::J_MERCURY, JplConstants::J_SBARY, $pv, $serr);

if ($result !== JplConstants::OK) {
    echo "pleph() failed: $serr\n";
} else {
    echo "Mercury barycentric position (AU):\n";
    printf("  X = %.15f\n", $pv[0]);
    printf("  Y = %.15f\n", $pv[1]);
    printf("  Z = %.15f\n", $pv[2]);
    echo "swetest reference: X=-0.137318604, Y=-0.403224457, Z=-0.201384061\n";
}

// Test Mercury at JD 2455000
echo "\n=== Testing pleph() for Mercury at JD 2455000.0 ===\n";
$jdMid = 2455000.0;
$pv = [];
$result = $jpl->pleph($jdMid, JplConstants::J_MERCURY, JplConstants::J_SBARY, $pv, $serr);

if ($result !== JplConstants::OK) {
    echo "pleph() failed: $serr\n";
} else {
    echo "Mercury barycentric position (AU):\n";
    printf("  X = %.15f\n", $pv[0]);
    printf("  Y = %.15f\n", $pv[1]);
    printf("  Z = %.15f\n", $pv[2]);
    echo "swetest reference: X=0.332877088, Y=-0.168299762, Z=-0.125127436\n";
}

// Test Mercury near start epoch
echo "\n=== Testing pleph() for Mercury at JD -254800.0 (near start) ===\n";
$jdStart = -254800.0;
$pv = [];
$result = $jpl->pleph($jdStart, JplConstants::J_MERCURY, JplConstants::J_SBARY, $pv, $serr);

if ($result !== JplConstants::OK) {
    echo "pleph() failed: $serr\n";
} else {
    echo "Mercury barycentric position (AU):\n";
    printf("  X = %.15f\n", $pv[0]);
    printf("  Y = %.15f\n", $pv[1]);
    printf("  Z = %.15f\n", $pv[2]);
    echo "swetest reference: X=-0.131106457, Y=-0.286326629, Z=-0.172416803\n";
}
