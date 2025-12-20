<?php
/**
 * Compare PHP with C reference for multiple dates (1900, 2000, 2100)
 */
require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path('C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/ephe');

// C reference values
$c_reference = [
    '1900-01-01' => [
        'jd' => 2415021.0,
        'moon_j2000' => ['lon' => 281.008484726169002, 'lat' => 1.731745747576851, 'dist' => 0.002450522300925],
        'moon_app' => ['lon' => 279.616711987857173, 'lat' => 1.744292638579004, 'dist' => 0.002450518108891],
        'sun_app' => ['lon' => 280.663311344495014, 'lat' => 0.000072364154844, 'dist' => 0.983264446622965],
        'mercury_app' => ['lon' => 259.639379900290294, 'lat' => 1.055908770474864, 'dist' => 1.150561309034167],
    ],
    '2000-01-01.5 (J2000)' => [
        'jd' => 2451545.0,
        'moon_j2000' => ['lon' => 223.318926841255433, 'lat' => 5.170869334505696, 'dist' => 0.002690202992216],
        'moon_app' => ['lon' => 223.314870314333888, 'lat' => 5.170872114975060, 'dist' => 0.002689975430990],
        'sun_app' => ['lon' => 280.368165581233825, 'lat' => 0.000227416018853, 'dist' => 0.983327630883831],
        'mercury_app' => ['lon' => 271.888127334333660, 'lat' => -0.994756584137136, 'dist' => 1.415466037216012],
    ],
    '2100-01-01' => [
        'jd' => 2488070.0,
        'moon_j2000' => ['lon' => 163.014452023969596, 'lat' => 0.476665733565053, 'dist' => 0.002481241884686],
        'moon_app' => ['lon' => 164.412461244214654, 'lat' => 0.479310871677316, 'dist' => 0.002481018135324],
        'sun_app' => ['lon' => 281.112786481389321, 'lat' => 0.000058815769974, 'dist' => 0.983351353536920],
        'mercury_app' => ['lon' => 288.816288567338120, 'lat' => -2.123485128483540, 'dist' => 1.381545687954180],
    ],
];

echo "=== PHP vs C Reference Comparison (1900, 2000, 2100) ===\n\n";

$max_errors = [
    'Moon J2000 lon' => 0,
    'Moon J2000 lat' => 0,
    'Moon Apparent lon' => 0,
    'Moon Apparent lat' => 0,
    'Sun Apparent lon' => 0,
    'Sun Apparent lat' => 0,
    'Mercury Apparent lon' => 0,
    'Mercury Apparent lat' => 0,
];

foreach ($c_reference as $label => $ref) {
    echo "=== $label (JD={$ref['jd']}) ===\n\n";

    // Moon J2000
    $flags = Constants::SEFLG_SWIEPH | Constants::SEFLG_J2000 | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT;
    $xx = [];
    $serr = '';
    swe_calc($ref['jd'], Constants::SE_MOON, $flags, $xx, $serr);

    $diff_lon = ($xx[0] - $ref['moon_j2000']['lon']) * 3600;
    $diff_lat = ($xx[1] - $ref['moon_j2000']['lat']) * 3600;

    echo "Moon J2000+TRUEPOS+NONUT:\n";
    echo sprintf("   PHP lon  = %.15f, C lon  = %.15f, diff = %+.6f\"\n", $xx[0], $ref['moon_j2000']['lon'], $diff_lon);
    echo sprintf("   PHP lat  = %.15f, C lat  = %.15f, diff = %+.6f\"\n\n", $xx[1], $ref['moon_j2000']['lat'], $diff_lat);

    $max_errors['Moon J2000 lon'] = max($max_errors['Moon J2000 lon'], abs($diff_lon));
    $max_errors['Moon J2000 lat'] = max($max_errors['Moon J2000 lat'], abs($diff_lat));

    // Moon Apparent
    $flags = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;
    $xx = [];
    swe_calc($ref['jd'], Constants::SE_MOON, $flags, $xx, $serr);

    $diff_lon = ($xx[0] - $ref['moon_app']['lon']) * 3600;
    $diff_lat = ($xx[1] - $ref['moon_app']['lat']) * 3600;

    echo "Moon Apparent:\n";
    echo sprintf("   PHP lon  = %.15f, C lon  = %.15f, diff = %+.6f\"\n", $xx[0], $ref['moon_app']['lon'], $diff_lon);
    echo sprintf("   PHP lat  = %.15f, C lat  = %.15f, diff = %+.6f\"\n\n", $xx[1], $ref['moon_app']['lat'], $diff_lat);

    $max_errors['Moon Apparent lon'] = max($max_errors['Moon Apparent lon'], abs($diff_lon));
    $max_errors['Moon Apparent lat'] = max($max_errors['Moon Apparent lat'], abs($diff_lat));

    // Sun Apparent
    $xx = [];
    swe_calc($ref['jd'], Constants::SE_SUN, $flags, $xx, $serr);

    $diff_lon = ($xx[0] - $ref['sun_app']['lon']) * 3600;
    $diff_lat = ($xx[1] - $ref['sun_app']['lat']) * 3600;

    echo "Sun Apparent:\n";
    echo sprintf("   PHP lon  = %.15f, C lon  = %.15f, diff = %+.6f\"\n", $xx[0], $ref['sun_app']['lon'], $diff_lon);
    echo sprintf("   PHP lat  = %.15f, C lat  = %.15f, diff = %+.6f\"\n\n", $xx[1], $ref['sun_app']['lat'], $diff_lat);

    $max_errors['Sun Apparent lon'] = max($max_errors['Sun Apparent lon'], abs($diff_lon));
    $max_errors['Sun Apparent lat'] = max($max_errors['Sun Apparent lat'], abs($diff_lat));

    // Mercury Apparent
    $xx = [];
    swe_calc($ref['jd'], Constants::SE_MERCURY, $flags, $xx, $serr);

    $diff_lon = ($xx[0] - $ref['mercury_app']['lon']) * 3600;
    $diff_lat = ($xx[1] - $ref['mercury_app']['lat']) * 3600;

    echo "Mercury Apparent:\n";
    echo sprintf("   PHP lon  = %.15f, C lon  = %.15f, diff = %+.6f\"\n", $xx[0], $ref['mercury_app']['lon'], $diff_lon);
    echo sprintf("   PHP lat  = %.15f, C lat  = %.15f, diff = %+.6f\"\n\n", $xx[1], $ref['mercury_app']['lat'], $diff_lat);

    $max_errors['Mercury Apparent lon'] = max($max_errors['Mercury Apparent lon'], abs($diff_lon));
    $max_errors['Mercury Apparent lat'] = max($max_errors['Mercury Apparent lat'], abs($diff_lat));

    // Reset state
    swe_close();
}

echo "=== SUMMARY: Maximum Errors (arcsec) ===\n\n";
foreach ($max_errors as $key => $err) {
    $status = $err < 1.0 ? 'OK' : ($err < 10.0 ? 'WARN' : 'FAIL');
    echo sprintf("   %-25s: %+.6f\" [%s]\n", $key, $err, $status);
}
