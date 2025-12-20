<?php

require __DIR__ . '/../src/Julian.php';
require __DIR__ . '/../src/Constants.php';
require __DIR__ . '/../src/DeltaT.php';
require __DIR__ . '/../src/Sidereal.php';
require __DIR__ . '/../src/Math.php';
require __DIR__ . '/../src/Coordinates.php';
require __DIR__ . '/../src/Obliquity.php';

use Swisseph\Julian;
use Swisseph\DeltaT;
use Swisseph\Sidereal;
use Swisseph\Math;
use Swisseph\Coordinates;
use Swisseph\Obliquity;

$N = 20000; // уменьшено для наглядного прогона
$jd0 = 2451545.0;

$start = microtime(true);
$sum = 0.0;
for ($i = 0; $i < $N; $i++) {
    $jd = $jd0 + $i * 0.01;
    $dt = DeltaT::deltaTSecondsFromJd($jd);
    $gmst = Sidereal::gmstHoursFromJdUt($jd);
    $eps = Obliquity::meanObliquityRadFromJdTT($jd + $dt/86400.0);
    // небольшая математика, чтобы задействовать код
    $lon = Math::degToRad(fmod($i * 3.14159, 360.0));
    $lat = Math::degToRad(fmod($i * 0.12345, 180.0) - 90.0);
    [$ra, $dec, $r] = Coordinates::eclipticToEquatorialRad($lon, $lat, 1.0, $eps);
    $sum += $dt + $gmst + $ra + $dec + $r;
}
$elapsed = microtime(true) - $start;

printf("bench: N=%d elapsed=%.3fs checksum=%.6f\n", $N, $elapsed, $sum);
