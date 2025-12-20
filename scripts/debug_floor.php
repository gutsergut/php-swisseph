<?php
$jd = -254800.0;
$s = $jd - 0.5;
echo "s = $s\n";
$etMn = floor($s);
echo "etMn (floor) = $etMn\n";
$etFr = $s - $etMn;
echo "etFr = $etFr\n";
$etMn += 0.5;
echo "etMn (+0.5) = $etMn\n";

$ss0 = -254895.5;
$ss2 = 64.0;

$diff = $etMn - $ss0;
echo "\ndiff = etMn - ss0 = $diff\n";
$div = $diff / $ss2;
echo "div = diff / ss2 = $div\n";
$intDiv = (int)$div;
echo "(int)div = $intDiv\n";
$nr = $intDiv + 2;
echo "nr = intDiv + 2 = $nr\n";
