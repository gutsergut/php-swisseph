<?php

require_once __DIR__ . '/../tests/bootstrap.php';

use Swisseph\Constants;

/**
 * Parity test: compare swe_nod_aps with swetest output
 * Tests mean nodes and apsides for planets
 */

$swetest = "c:\\Users\\serge\\OneDrive\\Documents\\Fractal\\Projects\\Component\\Swisseph\\с-swisseph\\swisseph\\windows\\programs\\swetest64.exe";

echo "=== Parity Test: swe_nod_aps vs swetest ===\n\n";

// Test dates
$testCases = [
    ['jd' => 2451545.0, 'label' => 'J2000.0'],
    ['jd' => 2460600.5, 'label' => '2024-10-27'],
];

// Planets to test
$planets = [
    ['ipl' => Constants::SE_MOON, 'name' => 'Moon', 'code' => '1'],
    ['ipl' => Constants::SE_EARTH, 'name' => 'Earth', 'code' => '3'],
    ['ipl' => Constants::SE_MARS, 'name' => 'Mars', 'code' => '4'],
    ['ipl' => Constants::SE_JUPITER, 'name' => 'Jupiter', 'code' => '5'],
];

foreach ($testCases as $test) {
    $jd = $test['jd'];
    $label = $test['label'];

    echo "Date: {$label} (JD {$jd})\n";
    echo str_repeat('=', 80) . "\n\n";

    foreach ($planets as $planet) {
        $ipl = $planet['ipl'];
        $name = $planet['name'];
        $pcode = $planet['code'];

        echo "{$name}:\n";

        // Get swetest values for nodes
        $cmd = "echo \"{$jd}\" | \"{$swetest}\" -bj -p{$pcode} -fn -head 2>nul";
        $output = shell_exec($cmd);
        $output = trim(preg_replace('/warning:.*$/m', '', $output));

        if (preg_match('/(\d+\.\d+)\s+(\d+\.\d+)/', $output, $matches)) {
            $sweAscNode = (float)$matches[1];
            $sweDescNode = (float)$matches[2];

            // Get PHP values
            $xnasc = [];
            $xndsc = [];
            $xperi = [];
            $xaphe = [];
            $serr = null;

            $ret = swe_nod_aps(
                $jd,
                $ipl,
                0, // no speed
                Constants::SE_NODBIT_MEAN,
                $xnasc,
                $xndsc,
                $xperi,
                $xaphe,
                $serr
            );

            if ($ret >= 0) {
                $phpAscNode = $xnasc[0];
                $phpDescNode = $xndsc[0];

                $diffAsc = abs($sweAscNode - $phpAscNode);
                $diffDesc = abs($sweDescNode - $phpDescNode);

                // Handle wrap-around
                if ($diffAsc > 180) $diffAsc = 360 - $diffAsc;
                if ($diffDesc > 180) $diffDesc = 360 - $diffDesc;

                printf("  Ascending Node:   swetest=%10.6f°  PHP=%10.6f°  diff=%8.4f° (%6.2f\")\n",
                    $sweAscNode, $phpAscNode, $diffAsc, $diffAsc * 3600);
                printf("  Descending Node:  swetest=%10.6f°  PHP=%10.6f°  diff=%8.4f° (%6.2f\")\n",
                    $sweDescNode, $phpDescNode, $diffDesc, $diffDesc * 3600);
            } else {
                echo "  ERROR: $serr\n";
            }
        } else {
            echo "  ERROR: Could not parse swetest output: $output\n";
        }

        // Get swetest values for perihelion/aphelion
        $cmd = "echo \"{$jd}\" | \"{$swetest}\" -bj -p{$pcode} -ff -head 2>nul";
        $output = shell_exec($cmd);
        $output = trim(preg_replace('/warning:.*$/m', '', $output));

        if (preg_match('/(\d+\.\d+)\s+(\d+\.\d+)\s+(\d+\.\d+)/', $output, $matches)) {
            $swePeri = (float)$matches[1];
            $sweAphe = (float)$matches[2];
            $sweFocus = (float)$matches[3];

            // Get PHP values (already calculated above)
            if ($ret >= 0) {
                $phpPeri = $xperi[0];
                $phpAphe = $xaphe[0];

                $diffPeri = abs($swePeri - $phpPeri);
                $diffAphe = abs($sweAphe - $phpAphe);

                // Handle wrap-around
                if ($diffPeri > 180) $diffPeri = 360 - $diffPeri;
                if ($diffAphe > 180) $diffAphe = 360 - $diffAphe;

                printf("  Perihelion:       swetest=%10.6f°  PHP=%10.6f°  diff=%8.4f° (%6.2f\")\n",
                    $swePeri, $phpPeri, $diffPeri, $diffPeri * 3600);
                printf("  Aphelion:         swetest=%10.6f°  PHP=%10.6f°  diff=%8.4f° (%6.2f\")\n",
                    $sweAphe, $phpAphe, $diffAphe, $diffAphe * 3600);
            }
        }

        echo "\n";
    }

    echo "\n";
}

echo "=== Test completed ===\n";
