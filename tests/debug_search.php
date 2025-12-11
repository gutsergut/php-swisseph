<?php
require __DIR__ . '/../vendor/autoload.php';

// Test formatSearchName
function formatSearchName(string $star): string
{
    $sstar = $star;
    $sstar = str_replace(' ', '', $sstar);

    $comma_pos = strpos($sstar, ',');
    if ($comma_pos !== false) {
        $before = substr($sstar, 0, $comma_pos);
        $after = substr($sstar, $comma_pos);
        $sstar = strtolower($before) . $after;
    } else {
        $sstar = strtolower($sstar);
    }

    return $sstar;
}

echo "Test 1: '" . formatSearchName('Sirius') . "'\n";
echo "Test 2: '" . formatSearchName('Sirius,alCMa') . "'\n";
echo "Test 3: '" . formatSearchName('sirius') . "'\n";
echo "Test 4: '" . formatSearchName('SIRIUS') . "'\n";

// Now test search
require __DIR__ . '/../src/functions.php';
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$serr = '';
\Swisseph\Swe\FixedStars\StarRegistry::loadAll($serr);

echo "\n=== Search tests ===\n";

$result1 = \Swisseph\Swe\FixedStars\StarRegistry::search('sirius', $serr);
echo "Search 'sirius': " . ($result1 ? "Found: " . $result1->starname : "Not found - $serr") . "\n";

$result2 = \Swisseph\Swe\FixedStars\StarRegistry::search('sirius,alCMa', $serr);
echo "Search 'sirius,alCMa': " . ($result2 ? "Found: " . $result2->starname : "Not found - $serr") . "\n";
