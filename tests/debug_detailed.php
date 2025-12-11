<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$tjd_et = 2451545.0;

// Modify StarFunctions to add logging
class DebugStarFunctions {
    private static ?string $lastStarName = null;

    public static function fixstar2(
        string &$star,
        float $tjd,
        int $iflag,
        array &$xx,
        ?string &$serr = null
    ): int {
        echo "\n=== fixstar2 called ===\n";
        echo "Input star: '$star'\n";

        $serr = '';

        // Load
        $retc = \Swisseph\Swe\FixedStars\StarRegistry::loadAll($serr);
        if ($retc < 0) {
            echo "LOAD FAILED: $serr\n";
            return Constants::SE_ERR;
        }

        // Format
        $sstar = self::formatSearchName($star, $serr);
        echo "Formatted: '$sstar'\n";
        if ($sstar === null) {
            echo "FORMAT FAILED: $serr\n";
            return Constants::SE_ERR;
        }

        // Cache check
        echo "Cache key: '" . (self::$lastStarName ?? 'NULL') . "'\n";
        echo "Match: " . (self::$lastStarName === $sstar ? 'YES' : 'NO') . "\n";

        // Search
        $stardata = \Swisseph\Swe\FixedStars\StarRegistry::search($sstar, $serr);
        if ($stardata === null) {
            echo "SEARCH FAILED: $serr\n";
            return Constants::SE_ERR;
        }
        echo "Found: " . $stardata->starname . "\n";

        // Update cache
        self::$lastStarName = $sstar;
        echo "Cache updated to: '$sstar'\n";

        // Calculate
        $retc = \Swisseph\Swe\FixedStars\StarCalculator::calculate($stardata, $tjd, $iflag, $star, $xx, $serr);
        echo "Calculate result: $retc\n";
        echo "Output star: '$star'\n";

        return $iflag;
    }

    private static function formatSearchName(string $star, ?string &$serr): ?string
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

        if (empty($sstar)) {
            $serr = 'Star name is empty';
            return null;
        }

        return $sstar;
    }
}

// Test
$star1 = 'Sirius';
$xx1 = [];
$serr1 = '';
$ret1 = DebugStarFunctions::fixstar2($star1, $tjd_et, Constants::SEFLG_SWIEPH, $xx1, $serr1);
echo "Return 1: $ret1\n";
echo "Star after call 1: '$star1'\n";

$star2 = 'Sirius';
$xx2 = [];
$serr2 = '';
$ret2 = DebugStarFunctions::fixstar2($star2, $tjd_et, Constants::SEFLG_SWIEPH | Constants::SEFLG_EQUATORIAL, $xx2, $serr2);
echo "Return 2: $ret2\n";
echo "Star after call 2: '$star2'\n";
