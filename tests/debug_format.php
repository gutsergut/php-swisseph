<?php
require __DIR__ . '/../vendor/autoload.php';

$star1 = 'Sirius';
$star2 = 'Sirius,alCMa';

// Trim whitespace
$sstar1 = trim($star1);
$sstar2 = trim($star2);

// Convert to lowercase
$sstar1 = strtolower($sstar1);
$sstar2 = strtolower($sstar2);

// Remove extra spaces
$sstar1 = preg_replace('/\s+/', ' ', $sstar1);
$sstar2 = preg_replace('/\s+/', ' ', $sstar2);

echo "Input 1: '$star1'\n";
echo "Formatted 1: '$sstar1'\n\n";

echo "Input 2: '$star2'\n";
echo "Formatted 2: '$sstar2'\n\n";

echo "Match: " . ($sstar1 === $sstar2 ? 'YES' : 'NO') . "\n";
