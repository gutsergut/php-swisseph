<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

// Berlin coordinates
$geopos = [13.41, 52.52, 0.0];
$ipl = Constants::SE_MOON;
$rsmi = Constants::SE_CALC_RISE | Constants::SE_BIT_DISC_CENTER;

echo "Testing rise_set_fast conditions:\n";
echo "Planet: SE_MOON (" . Constants::SE_MOON . ")\n";
echo "Latitude: " . $geopos[1] . "°\n";
echo "rsmi: " . $rsmi . "\n\n";

// Check each condition
$do_fixstar = false;
echo "1. do_fixstar = false: " . ($do_fixstar ? "NO" : "YES") . "\n";

$has_rise_set = ($rsmi & (Constants::SE_CALC_RISE | Constants::SE_CALC_SET));
echo "2. rsmi & (SE_CALC_RISE | SE_CALC_SET): " . ($has_rise_set ? "YES" : "NO") . "\n";

$force_slow = ($rsmi & Constants::SE_BIT_FORCE_SLOW_METHOD);
echo "3. NOT SE_BIT_FORCE_SLOW_METHOD: " . ($force_slow ? "NO" : "YES") . "\n";

$twilight = ($rsmi & (Constants::SE_BIT_CIVIL_TWILIGHT | Constants::SE_BIT_NAUTIC_TWILIGHT | Constants::SE_BIT_ASTRO_TWILIGHT));
echo "4. NOT twilight flags: " . ($twilight ? "NO" : "YES") . "\n";

$planet_range = ($ipl >= Constants::SE_SUN && $ipl <= Constants::SE_TRUE_NODE);
echo "5. Planet in range [SE_SUN..SE_TRUE_NODE]: " . ($planet_range ? "YES (" . Constants::SE_SUN . ".." . Constants::SE_TRUE_NODE . ")" : "NO") . "\n";

$lat_ok = (abs($geopos[1]) <= 60.0 || ($ipl === Constants::SE_SUN && abs($geopos[1]) <= 65.0));
echo "6. Latitude OK (<=60° for Moon, <=65° for Sun): " . ($lat_ok ? "YES" : "NO") . "\n";

$all_ok = $do_fixstar === false && $has_rise_set && !$force_slow && !$twilight && $planet_range && $lat_ok;
echo "\nAll conditions met: " . ($all_ok ? "YES - should use rise_set_fast()" : "NO - will use riseTransTrueHor()") . "\n";
