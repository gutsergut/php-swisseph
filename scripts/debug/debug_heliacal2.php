<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/functions.php';

use Swisseph\Constants;
use Swisseph\Domain\Heliacal\HeliacalFunctions;

echo "=== Heliacal Debug ===\n\n";

$dret = array_fill(0, 10, 0.0);
$serr = '';

// Direct call to internal function for more debugging
try {
    $result = HeliacalFunctions::swe_heliacal_ut(
        2451545.0,
        [13.4, 52.5, 100.0],
        [1013.25, 15.0, 50.0, 40.0],
        [36.0, 1.0, 0, 1.0, 0.0, 0.0],
        'venus',
        1,
        Constants::SEFLG_SWIEPH,
        $dret,
        $serr
    );

    echo "Result: " . $result . "\n";
    echo "Error: '" . $serr . "'\n";
    echo "dret[0]: " . $dret[0] . "\n";

} catch (\Throwable $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
