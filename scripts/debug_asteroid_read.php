<?php
/**
 * Debug script to trace asteroid data loading
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;
use Swisseph\SwephFile\SwephReader;

$ephePath = __DIR__ . '/../../eph/ephe';
\Swisseph\State::setEphePath($ephePath);

$swed = SwedState::getInstance();
$swed->ephepath = $ephePath;

$jd = 2460000.5;

echo "Testing asteroid file loading\n";
echo str_repeat("=", 60) . "\n\n";

// Try to open the file for SEI_CHIRON
$ifno = SwephConstants::SEI_FILE_MAIN_AST;
$fname = "seas_18.se1";

$serr = null;
$result = SwephReader::openAndReadHeader($ifno, $fname, $ephePath, $serr);

if (!$result) {
    echo "Failed to open file: $serr\n";
    exit(1);
}

echo "File opened successfully.\n\n";

// Check what was loaded into pldat
echo "Planet data in SwedState after loading:\n";
echo str_repeat("-", 80) . "\n";

$planets = [
    12 => 'SEI_CHIRON',
    13 => 'SEI_PHOLUS',
    14 => 'SEI_CERES',
    15 => 'SEI_PALLAS',
    16 => 'SEI_JUNO',
    17 => 'SEI_VESTA',
];

foreach ($planets as $ipli => $name) {
    $pdp = &$swed->pldat[$ipli];
    echo sprintf("%s (slot %d): lndx0=%d, iflg=0x%02X, ncoe=%d, dseg=%.1f, ibdy=%d\n",
        $name, $ipli, $pdp->lndx0 ?? 0, $pdp->iflg ?? 0, $pdp->ncoe ?? 0, $pdp->dseg ?? 0.0, $pdp->ibdy ?? -1);
}

echo "\nFile data:\n";
$fdp = &$swed->fidat[$ifno];
echo "  npl = {$fdp->npl}\n";
echo "  ipl[] = [" . implode(', ', array_slice($fdp->ipl, 0, $fdp->npl)) . "]\n";
echo "  tfstart = {$fdp->tfstart}\n";
echo "  tfend = {$fdp->tfend}\n";

echo "\nDone.\n";
