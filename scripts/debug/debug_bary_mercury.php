<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Swisseph\Constants;

\swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$jd = 2460000.5;

// С TRUEPOS
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_BARYCTR | Constants::SEFLG_TRUEPOS;
$xx = [];
$serr = '';
\swe_calc($jd, Constants::SE_MERCURY, $iflag, $xx, $serr);
printf("Mercury TRUEPOS BARY: lon=%.7f lat=%.7f dist=%.9f\n", $xx[0], $xx[1], $xx[2]);

// Без TRUEPOS
$iflag2 = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_BARYCTR;
$xx2 = [];
\swe_calc($jd, Constants::SE_MERCURY, $iflag2, $xx2, $serr);
printf("Mercury APPARENT BARY: lon=%.7f lat=%.7f dist=%.9f\n", $xx2[0], $xx2[1], $xx2[2]);

// Эталон swetest
echo "\n";
echo "Reference from swetest64.exe:\n";
echo "Mercury TRUEPOS BARY: lon=283.6475275 lat=-5.8240427\n";
echo "Mercury APPARENT BARY: lon=283.6398870 lat=-5.8235092\n";
