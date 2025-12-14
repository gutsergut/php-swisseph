<?php
require 'c:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/php-swisseph/vendor/autoload.php';
$t = Swisseph\Moshier\Tables\MercuryTable::get();
echo "lonTbl[0..11]: " . implode(', ', array_slice($t->lonTbl, 0, 12)) . PHP_EOL;
echo "argTbl[0..15]: " . implode(', ', array_slice($t->argTbl, 0, 16)) . PHP_EOL;
echo "argTbl count: " . count($t->argTbl) . PHP_EOL;
echo "lonTbl count: " . count($t->lonTbl) . PHP_EOL;
echo "latTbl count: " . count($t->latTbl) . PHP_EOL;
echo "radTbl count: " . count($t->radTbl) . PHP_EOL;
echo "maxHarmonic: " . implode(', ', $t->maxHarmonic) . PHP_EOL;
echo "distance: " . $t->distance . PHP_EOL;
