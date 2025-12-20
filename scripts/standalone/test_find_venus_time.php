<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/functions.php';

use Swisseph\Constants;

// Find suitable time for Venus visibility test
$dgeo = [13.4, 52.5, 100.0]; // Amsterdam
$datm = [1013.25, 15.0, 40.0, 0.0];
$dobs = [36.0, 1.0, 0, 1.0, 0.0, 0.0];

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');
swe_set_topo($dgeo[0], $dgeo[1], $dgeo[2]);

// Try evening twilight times around J2000
echo "Finding suitable Venus visibility time:\n\n";

$baseJd = 2451545.0; // J2000

for ($offset = 0; $offset < 10; $offset += 0.1) {
    $jd = $baseJd + $offset;
    $dret = array_fill(0, 10, 0.0);
    $serr = '';

    $retval = swe_vis_limit_mag($jd, $dgeo, $datm, $dobs, 'venus', Constants::SEFLG_SWIEPH, $dret, $serr);

    if ($retval >= 0) {
        $vlm = $dret[0];
        $objAlt = $dret[1];
        $sunAlt = $dret[3];

        // Looking for: Sun below horizon, Venus above horizon, positive VLM
        if ($sunAlt < 0 && $objAlt > 0 && $vlm > 0) {
            echo sprintf(
                "✓ JD %.1f: VLM=%.2f, Venus Alt=%.2f°, Sun Alt=%.2f°\n",
                $jd, $vlm, $objAlt, $sunAlt
            );

            if ($vlm >= 4.0 && $vlm <= 7.0) {
                echo "\n** IDEAL for naked eye test **\n";
                break;
            }
        }
    }
}

swe_close();
