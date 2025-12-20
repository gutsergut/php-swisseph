<?php
/**
 * Test getBarycentricSun directly
 */
require __DIR__ . '/vendor/autoload.php';

$ephePath = realpath(__DIR__ . '/../eph/ephe');
\Swisseph\State::setEphePath($ephePath);
$swed = \Swisseph\SwephFile\SwedState::getInstance();
$swed->setEphePath($ephePath);

putenv('DEBUG_OSCU=1');

echo "Calling getBarycentricSun directly...\n";
$xsun = \Swisseph\BarycentricPositions::getBarycentricSun(2451545.0, 0x102);
echo "Result: xsun = [" . implode(', ', array_map(fn($x)=>sprintf('%.10f', $x), $xsun)) . "]\n";

echo "\nExpected (from C): xsun ≈ [-0.0071, -0.0026, -0.0009]\n";
