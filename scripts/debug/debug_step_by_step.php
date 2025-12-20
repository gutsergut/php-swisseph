<?php
/**
 * Step-by-step debug script for comparing with C implementation
 * Run: php debug_step_by_step.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use Swisseph\Domain\Heliacal\HeliacalAscensional;
use Swisseph\Domain\Heliacal\HeliacalPhenomena;

// Constants - matching C Swiss Ephemeris and PHP port
define('SE_SUN', 0);
define('SE_MOON', 1);
define('SE_MERCURY', 2);
define('SE_VENUS', 3);
define('SE_MARS', 4);
define('SEFLG_SWIEPH', 2);
define('SEFLG_SPEED', 256);
define('ERR', -1);

// TCON table (from HeliacalAscensional::TCON) - reference epochs for conjunctions
// This matches the original C tcon[] array from swehel.c
$TCON_TABLE = [
    0.0, 0.0,              // [0,1] SE_ECL_NUT (unused)
    0.0, 0.0,              // [2,3] Sun (unused)
    2451550.0, 2451550.0,  // [4,5] Moon
    2451604.0, 2451670.0,  // [6,7] Mercury (ipl=2)
    2451980.0, 2452280.0,  // [8,9] Venus (ipl=3)  ← CORRECT!
    2451727.0, 2452074.0,  // [10,11] Mars (ipl=4)
    2451673.0, 2451877.0,  // [12,13] Jupiter (ipl=5)
    2451675.0, 2451868.0,  // [14,15] Saturn (ipl=6)
    2451581.0, 2451768.0,  // [16,17] Uranus (ipl=7)
    2451568.0, 2451753.0,  // [18,19] Neptune (ipl=8)
];

// Synodic periods - indexed by planet number (from HeliacalPhenomena::get_synodic_period)
$SYNODIC_PERIODS = [
    0,         // Sun [0]
    0,         // Moon [1]
    115.8775,  // Mercury [2]
    583.9214,  // Venus [3]
    779.9361,  // Mars [4]
    398.8840,  // Jupiter [5]
    378.0919,  // Saturn [6]
    369.6560,  // Uranus [7]
    367.4867,  // Neptune [8]
    366.7207,  // Pluto [9]
];

// Enable detailed logging
putenv('DEBUG_HELIACAL=1');

function find_conjunct_sun_debug(float $tjd_start, int $ipl, int $TypeEvent): void {
    global $TCON_TABLE, $SYNODIC_PERIODS;

    echo "\n=== FIND_CONJUNCT_SUN Debug (PHP) ===\n";
    echo "Input: tjd_start={$tjd_start}, ipl={$ipl} (Venus), TypeEvent={$TypeEvent}\n";

    $epheflag = SEFLG_SWIEPH;
    $daspect = 0.0;

    // Determine aspect
    if ($ipl >= SE_MARS && $TypeEvent >= 3) {
        $daspect = 180.0;
    }
    echo "Aspect: {$daspect} degrees (0=conjunction)\n";

    // Get reference epoch (from TCON table)
    $i = (int)((($TypeEvent - 1) / 2) + $ipl * 2);
    $tjd0 = $TCON_TABLE[$i];
    echo "Reference epoch (TCON[{$i}]): {$tjd0}\n";

    // Calculate synodic period
    $dsynperiod = $SYNODIC_PERIODS[$ipl];

    // Initial conjunction estimate
    $calc = ($tjd_start - $tjd0) / $dsynperiod;
    $floored = floor($calc + 1);
    $tjdcon = $tjd0 + $floored * $dsynperiod;

    echo "Initial conjunction estimate: tjd0 + floor(({$tjd_start} - {$tjd0}) / {$dsynperiod} + 1) * {$dsynperiod}\n";
    echo "  = {$tjd0} + floor({$calc} + 1) * {$dsynperiod}\n";
    echo "  = {$tjd0} + {$floored} * {$dsynperiod}\n";
    echo "  = {$tjdcon}\n";

    echo "\n--- Newton's Method Iterations ---\n";
    $ds = 100.0;
    $iteration = 0;
    $x = array_fill(0, 6, 0.0);
    $xs = array_fill(0, 6, 0.0);
    $serr = '';

    while ($ds > 0.5) {
        $iteration++;

        // Calculate planet position
        if (swe_calc($tjdcon, $ipl, $epheflag | SEFLG_SPEED, $x, $serr) === ERR) {
            echo "ERROR: swe_calc planet failed: {$serr}\n";
            return;
        }

        // Calculate Sun position
        if (swe_calc($tjdcon, SE_SUN, $epheflag | SEFLG_SPEED, $xs, $serr) === ERR) {
            echo "ERROR: swe_calc Sun failed: {$serr}\n";
            return;
        }

        // Calculate angular distance
        $raw_ds = $x[0] - $xs[0] - $daspect;
        $ds = swe_degnorm($raw_ds);
        if ($ds > 180.0) {
            $ds -= 360.0;
        }

        $speed_diff = $x[3] - $xs[3];
        $correction = $ds / $speed_diff;

        printf("Iter %d: tjd=%.8f\n", $iteration, $tjdcon);
        printf("  Planet: lon=%.6f, lat=%.6f, dist=%.6f AU, speed=%.6f deg/day\n",
               $x[0], $x[1], $x[2], $x[3]);
        printf("  Sun:    lon=%.6f, lat=%.6f, dist=%.6f AU, speed=%.6f deg/day\n",
               $xs[0], $xs[1], $xs[2], $xs[3]);
        printf("  Angular diff (raw): %.6f deg\n", $raw_ds);
        printf("  Angular diff (norm): %.6f deg (after swe_degnorm + wrap)\n", $ds);
        printf("  Speed diff: %.6f deg/day\n", $speed_diff);
        printf("  Correction: %.6f / %.6f = %.8f days\n", $ds, $speed_diff, $correction);

        $tjdcon -= $correction;
        printf("  New tjdcon: %.8f\n", $tjdcon);

        if (abs($ds) <= 0.5) {
            echo "  CONVERGED (ds <= 0.5)\n";
            break;
        }

        if ($iteration > 50) {
            echo "  WARNING: Too many iterations, stopping\n";
            break;
        }
    }

    echo "\n--- Superior/Inferior Conjunction Check ---\n";
    // Recalculate position at final conjunction
    if (swe_calc($tjdcon, $ipl, $epheflag, $x, $serr) === ERR) {
        echo "ERROR: Final swe_calc failed: {$serr}\n";
        return;
    }

    $planet_dist = $x[2];
    printf("Planet distance at conjunction: %.6f AU\n", $planet_dist);

    if ($ipl <= SE_VENUS && $TypeEvent <= 2 && $daspect == 0.0) {
        echo "Checking conjunction type for inner planet (TypeEvent={$TypeEvent})...\n";

        if ($planet_dist > 0.8) {
            echo "  >>> SUPERIOR conjunction detected (dist > 0.8 AU)\n";
            printf("  >>> Adjusting to INFERIOR: tjdcon -= %.2f / 2\n", $dsynperiod);
            $tjdcon -= $dsynperiod / 2.0;
            printf("  >>> New tjdcon: %.5f\n", $tjdcon);

            // Verify the new position
            if (swe_calc($tjdcon, $ipl, $epheflag, $x, $serr) === ERR) {
                echo "ERROR: Verification swe_calc failed: {$serr}\n";
                return;
            }
            printf("  >>> Verification: planet distance now %.6f AU\n", $x[2]);
        } else {
            echo "  >>> INFERIOR conjunction confirmed (dist < 0.8 AU)\n";
        }
    }

    printf("\n=== FINAL RESULT: tjdcon = %.8f ===\n", $tjdcon);
}

// Main execution
echo "========================================\n";
echo "  PHP Step-by-Step Heliacal Debug\n";
echo "========================================\n";

$tjd_start = 2451697.5;
$dgeo = [13.4, 52.5, 100.0];
$datm = [1013.25, 15.0, 40.0, 0.0];
$dobs = [36.0, 1.0, 0, 1.0, 0.0, 0.0];

echo "\nVenus Heliacal Rising (TypeEvent=1)\n";
echo "Start date: JD {$tjd_start} (2000-06-01)\n";
echo "Location: {$dgeo[0]}E, {$dgeo[1]}N, {$dgeo[2]}m\n";
echo "Expected result: JD 2452004.66233\n";

// Step 1: Show find_conjunct_sun in detail
find_conjunct_sun_debug($tjd_start, SE_VENUS, 1);

// Step 2: Run full heliacal_ut and see result
echo "\n\n=== Running full swe_heliacal_ut ===\n";
$dret = array_fill(0, 50, 0.0);
$serr = '';
$retval = swe_heliacal_ut($tjd_start, $dgeo, $datm, $dobs, "Venus", 1, SEFLG_SWIEPH, $dret, $serr);

if ($retval < 0) {
    echo "Result: FAILED (retval={$retval})\n";
    echo "Error: {$serr}\n";
    printf("Last dret[0]: %.8f\n", $dret[0]);
} else {
    echo "Result: SUCCESS\n";
    printf("Event JD: %.8f\n", $dret[0]);
    echo "Expected: 2452004.66233000\n";
    printf("Difference: %.8f days (%.2f seconds)\n",
           $dret[0] - 2452004.66233,
           ($dret[0] - 2452004.66233) * 86400);
}

echo "\n";
