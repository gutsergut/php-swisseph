<?php
// Count C table sizes
$file = 'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/с-swisseph/swisseph/swemptab.h';
$c = file_get_contents($file);

$tables = ['mertabl', 'mertabb', 'mertabr', 'merargs'];

foreach ($tables as $name) {
    $pattern = '/static\s+(?:double|signed\s+char)\s+' . preg_quote($name) . '\s*\[\s*\]\s*=\s*\{([\s\S]*?)\n\};/m';
    if (preg_match($pattern, $c, $m)) {
        preg_match_all('/-?\d+\.?\d*(?:e[+-]?\d+)?/i', $m[1], $n);
        echo "$name: " . count($n[0]) . " values\n";
    } else {
        echo "$name: NOT FOUND\n";
    }
}
