<?php
require __DIR__ . '/../vendor/autoload.php';

$jd = 2460676.6851851;
$y = 0; $m = 0; $d = 0; $h = 0;
swe_revjul($jd, 1, $y, $m, $d, $h);
echo "JD $jd = $d.$m.$y $h hours\n";

// Convert hour to HH:MM:SS
$hh = (int)$h;
$mm = (int)(($h - $hh) * 60);
$ss = (($h - $hh) * 60 - $mm) * 60;
echo "Time: " . sprintf("%02d:%02d:%05.2f", $hh, $mm, $ss) . "\n";
