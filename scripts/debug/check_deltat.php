<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

// JD for 2025-01-01 00:00 UT
$jd_ut = swe_julday(2025, 1, 1, 0.0, Constants::SE_GREG_CAL);

echo "JD: " . number_format($jd_ut, 6) . "\n";

$serr = '';
$dt = swe_deltat_ex($jd_ut, Constants::SEFLG_SWIEPH, $serr);

echo "DeltaT: " . $dt . " days\n";
echo "DeltaT: " . ($dt * 86400) . " seconds\n";

if ($serr) {
    echo "Error: $serr\n";
}

// For comparison, check C reference
echo "\nExpected DeltaT for 2025: ~69 seconds\n";
