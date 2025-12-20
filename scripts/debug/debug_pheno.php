<?php
require_once __DIR__ . '/vendor/autoload.php';

use Swisseph\Constants;

\swe_set_ephe_path(__DIR__ . '/../eph/ephe');

$jd_ut_new = 2451550.14; // New Moon on 2000-01-06 15:14 UT
$attr = [];
$serr = '';

$ret = swe_pheno_ut($jd_ut_new, Constants::SE_MOON, Constants::SEFLG_SWIEPH, $attr, $serr);

echo "Return code: $ret\n";
echo "Error: $serr\n";
echo "Attr array:\n";
print_r($attr);
echo "\nDiameter (attr[3]): {$attr[3]}\n";
