<?php
/**
 * Базовый тест систем домов
 */

require __DIR__ . '/../tests/bootstrap.php';

use Swisseph\Constants;

// Test basic house calculation - Placidus
$jd = 2451545.0; // J2000.0
$lat = 51.5;     // London
$lon = 0.0;
$hsys = 'P';     // Placidus

$cusps = [];
$ascmc = [];
$ret = swe_houses($jd, $lat, $lon, $hsys, $cusps, $ascmc);

if ($ret < 0) {
    fwrite(STDERR, "swe_houses failed\n");
    exit(1);
}

if (count($cusps) !== 13) {
    fwrite(STDERR, "Expected 13 cusps, got " . count($cusps) . "\n");
    exit(2);
}

// Cusps should exist and be in valid range
for ($i = 1; $i <= 12; $i++) {
    if ($cusps[$i] < 0.0 || $cusps[$i] >= 360.0) {
        fwrite(STDERR, "Cusp $i out of range: {$cusps[$i]}\n");
        exit(3);
    }
}

// Test ASC/MC presence
if (!isset($ascmc[0]) || !isset($ascmc[1])) {
    fwrite(STDERR, "ASC or MC missing\n");
    exit(4);
}

// Test multiple house systems
$systems = ['P', 'K', 'R', 'C', 'E', 'W'];
foreach ($systems as $sys) {
    $cusps = [];
    $ascmc = [];
    $ret = swe_houses($jd, $lat, $lon, $sys, $cusps, $ascmc);

    if ($ret < 0) {
        fwrite(STDERR, "System $sys failed\n");
        exit(5);
    }

    if (count($cusps) !== 13) {
        fwrite(STDERR, "System $sys returned wrong cusp count\n");
        exit(6);
    }
}

echo "OK\n";
