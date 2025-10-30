<?php

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

$jd = 2451545.0; // J2000
$ipl = Constants::SE_JUPITER;

echo "=== Comparing swe_nod_aps: PHP vs C (swetest) ===\n\n";
echo "Date: JD $jd (J2000.0)\n";
echo "Planet: Jupiter (ipl=$ipl)\n\n";

// Call PHP version
$xnasc = [];
$xndsc = [];
$xperi = [];
$xaphe = [];
$serr = null;

$ret = swe_nod_aps(
    $jd,
    $ipl,
    0, // iflag
    Constants::SE_NODBIT_MEAN,
    $xnasc,
    $xndsc,
    $xperi,
    $xaphe,
    $serr
);

echo "PHP Results:\n";
echo "  Ascending Node:  {$xnasc[0]}°\n";
echo "  Descending Node: {$xndsc[0]}°\n";
echo "  Perihelion:      {$xperi[0]}°\n";
echo "  Aphelion:        {$xaphe[0]}°\n\n";

// Call C version through swetest
$swetest = "C:\\Users\\serge\\OneDrive\\Documents\\Fractal\\Projects\\Component\\Swisseph\\с-swisseph\\swisseph\\windows\\programs\\swetest64.exe";
$cmd = "echo \"$jd\" | \"$swetest\" -bj -p5 -fn -head 2>nul";
$output = shell_exec($cmd);
$output = trim(preg_replace('/warning:.*$/m', '', $output));

if (preg_match('/(\d+\.\d+)\s+(\d+\.\d+)/', $output, $matches)) {
    $cAscNode = (float)$matches[1];
    $cDescNode = (float)$matches[2];

    echo "C (swetest) Results:\n";
    echo "  Ascending Node:  {$cAscNode}°\n";
    echo "  Descending Node: {$cDescNode}°\n\n";

    echo "Differences:\n";
    $diffAsc = abs($xnasc[0] - $cAscNode);
    $diffDesc = abs($xndsc[0] - $cDescNode);

    echo "  Ascending Node:  {$diffAsc}° (" . ($diffAsc * 3600) . "\")\n";
    echo "  Descending Node: {$diffDesc}° (" . ($diffDesc * 3600) . "\")\n";
}

// Now let's check the raw orbital elements
echo "\n=== Debugging: Raw Orbital Elements ===\n\n";

use Swisseph\Domain\NodesApsides\PlanetaryElements;

$t = ($jd - 2451545.0) / 36525.0;
$iplx = PlanetaryElements::IPL_TO_ELEM[$ipl];

$node_raw = PlanetaryElements::evalPoly(PlanetaryElements::EL_NODE[$iplx], $t);
$peri_raw = PlanetaryElements::evalPoly(PlanetaryElements::EL_PERI[$iplx], $t);
$incl = PlanetaryElements::evalPoly(PlanetaryElements::EL_INCL[$iplx], $t);

echo "T = $t (centuries from J2000)\n";
echo "iplx = $iplx\n\n";
echo "Raw from tables:\n";
echo "  Node (raw):      " . $node_raw . "°\n";
echo "  Perihelion (raw): " . $peri_raw . "°\n";
echo "  Inclination:     " . $incl . "°\n\n";

echo "After MeanCalculator processing:\n";
echo "  Node (final):    {$xnasc[0]}°\n";
echo "  Diff from raw:   " . abs($xnasc[0] - $node_raw) . "°\n";
